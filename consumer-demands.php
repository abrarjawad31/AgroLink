<?php

require_once "auth.php";
require_once "db.php";

requireConsumer();


// ============================================================
// CURRENT CONSUMER
// ============================================================

$consumerId = (int) $_SESSION["user_id"];


// ============================================================
// HELPER FUNCTIONS
// ============================================================

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


function formatDate($date)
{
    if (empty($date)) {
        return "Not specified";
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return "Not specified";
    }

    return date("d M Y", $timestamp);
}


function formatDateTime($date)
{
    if (empty($date)) {
        return "";
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return "";
    }

    return date("d M Y", $timestamp);
}


function getDemandStatusClass($status)
{
    switch ($status) {

        case "open":
            return "status-open";

        case "negotiating":
            return "status-negotiating";

        case "fulfilled":
            return "status-fulfilled";

        case "closed":
            return "status-closed";

        case "cancelled":
            return "status-cancelled";

        default:
            return "status-closed";
    }
}


function getDemandStatusText($status)
{
    switch ($status) {

        case "open":
            return "Open";

        case "negotiating":
            return "Negotiating";

        case "fulfilled":
            return "Fulfilled";

        case "closed":
            return "Closed";

        case "cancelled":
            return "Cancelled";

        default:
            return ucfirst((string) $status);
    }
}


function getOfferStatusClass($status)
{
    switch ($status) {

        case "pending":
            return "offer-status-pending";

        case "accepted":
            return "offer-status-accepted";

        case "rejected":
            return "offer-status-rejected";

        case "countered":
            return "offer-status-countered";

        case "withdrawn":
            return "offer-status-withdrawn";

        default:
            return "";
    }
}


// ============================================================
// GET CURRENT CONSUMER
// ============================================================

$consumerName = "Consumer";

$stmt = $conn->prepare("
    SELECT name
    FROM users
    WHERE id = ?
      AND role = 'consumer'
    LIMIT 1
");

if ($stmt) {

    $stmt->bind_param("i", $consumerId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $consumerName = !empty($row["name"])
            ? $row["name"]
            : "Consumer";
    }

    $stmt->close();
}


// ============================================================
// AVATAR INITIAL
// ============================================================

$avatarLetter = strtoupper(
    substr(trim($consumerName), 0, 1)
);

if ($avatarLetter === "") {
    $avatarLetter = "C";
}


// ============================================================
// CART COUNT
// ============================================================

$cartCount = 0;

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(ci.quantity), 0) AS cart_count
    FROM cart c
    LEFT JOIN cart_items ci
        ON ci.cart_id = c.id
    WHERE c.consumer_id = ?
");

if ($stmt) {

    $stmt->bind_param("i", $consumerId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $cartCount = (int) $row["cart_count"];
    }

    $stmt->close();
}


// ============================================================
// STATUS FILTER
// ============================================================

$allowedStatuses = [
    "all",
    "open",
    "negotiating",
    "fulfilled",
    "closed",
    "cancelled"
];

$statusFilter = $_GET["status"] ?? "all";

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = "all";
}


// ============================================================
// FETCH CONSUMER DEMANDS
// ============================================================

$demands = [];

if ($statusFilter === "all") {

    $stmt = $conn->prepare("
        SELECT
            d.id,
            d.crop_name,
            d.category,
            d.quantity,
            d.unit,
            d.target_price,
            d.delivery_by,
            d.location,
            d.description,
            d.status,
            d.created_at,

            COUNT(do.id) AS offer_count

        FROM demands d

        LEFT JOIN demand_offers do
            ON do.demand_id = d.id
           AND do.status <> 'withdrawn'

        WHERE d.consumer_id = ?

        GROUP BY
            d.id,
            d.crop_name,
            d.category,
            d.quantity,
            d.unit,
            d.target_price,
            d.delivery_by,
            d.location,
            d.description,
            d.status,
            d.created_at

        ORDER BY d.created_at DESC
    ");

    if ($stmt) {

        $stmt->bind_param("i", $consumerId);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $demands[] = $row;
        }

        $stmt->close();
    }

} else {

    $stmt = $conn->prepare("
        SELECT
            d.id,
            d.crop_name,
            d.category,
            d.quantity,
            d.unit,
            d.target_price,
            d.delivery_by,
            d.location,
            d.description,
            d.status,
            d.created_at,

            COUNT(do.id) AS offer_count

        FROM demands d

        LEFT JOIN demand_offers do
            ON do.demand_id = d.id
           AND do.status <> 'withdrawn'

        WHERE d.consumer_id = ?
          AND d.status = ?

        GROUP BY
            d.id,
            d.crop_name,
            d.category,
            d.quantity,
            d.unit,
            d.target_price,
            d.delivery_by,
            d.location,
            d.description,
            d.status,
            d.created_at

        ORDER BY d.created_at DESC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "is",
            $consumerId,
            $statusFilter
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $demands[] = $row;
        }

        $stmt->close();
    }
}


// ============================================================
// FETCH OFFERS FOR EACH DEMAND
// ============================================================

foreach ($demands as &$demand) {

    $demand["offers"] = [];

    $demandId = (int) $demand["id"];

    $stmt = $conn->prepare("
        SELECT
            do.id,
            do.offer_price,
            do.quantity,
            do.unit,
            do.delivery_date,
            do.message,
            do.status,
            do.sender_type,
            do.created_at,

            u.name AS farmer_name,
            u.address AS farmer_address

        FROM demand_offers do

        INNER JOIN users u
            ON u.id = do.farmer_id

        WHERE do.demand_id = ?

        ORDER BY do.id ASC
    ");

    if ($stmt) {

        $stmt->bind_param("i", $demandId);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($offer = $result->fetch_assoc()) {
            $demand["offers"][] = $offer;
        }

        $stmt->close();
    }
}

unset($demand);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Demand Broadcasts & Offers | AgroLink</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/consumer-demands.css"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<header class="header">

    <div class="container navbar">

        <!-- LOGO -->

        <a
            href="consumer-dashboard.php"
            class="logo"
        >

            <span class="logo-icon">🌱</span>

            <span>
                Agro<span>Link</span>
            </span>

        </a>


        <!-- NAVIGATION -->

        <nav class="nav-menu">

            <a href="consumer-dashboard.php">
                Home
            </a>

            <a href="marketplace.php">
                Marketplace
            </a>

            <a href="future-harvests.php">
                Pre Bookings
            </a>

            <a href="my-orders.php">
                My Orders
            </a>

            <a
                href="consumer-demands.php"
                class="active-nav"
            >
                My Demands
            </a>

        </nav>


        <!-- CONSUMER ACTIONS -->

        <div class="consumer-actions">

            <a
                href="cart.php"
                class="cart-link"
            >

                <span>🛒</span>

                Cart

                <span class="cart-count">
                    <?= $cartCount ?>
                </span>

            </a>


            <a
                href="consumer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">
                    <?= e($avatarLetter) ?>
                </span>

                <span class="profile-name">
                    <?= e($consumerName) ?>
                </span>

            </a>


            <a
                href="logout.php"
                class="logout-btn"
            >
                Logout
            </a>

        </div>

    </div>

</header>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="demands-page">

    <div class="container">


        <!-- PAGE HEADER -->

        <section class="demands-header">

            <div>

                <span class="page-tag">
                    SRS MODULE 3.5 & 3.6
                </span>

                <h1>
                    My Demand Broadcasts & Farmer Bids
                </h1>

                <p>
                    Review incoming offers from local farmers,
                    negotiate pricing, and accept suitable terms.
                </p>

            </div>


            <a
                href="post-demand.php"
                class="broadcast-btn"
            >
                + Broadcast New Demand Request
            </a>

        </section>


        <!-- FILTER BAR -->

        <section class="demand-filter-bar">

            <div class="filter-title">
                My Demand Requests
            </div>

            <div class="filter-links">

                <a
                    href="consumer-demands.php"
                    class="<?= $statusFilter === 'all' ? 'active-filter' : '' ?>"
                >
                    All
                </a>

                <a
                    href="consumer-demands.php?status=open"
                    class="<?= $statusFilter === 'open' ? 'active-filter' : '' ?>"
                >
                    Open
                </a>

                <a
                    href="consumer-demands.php?status=negotiating"
                    class="<?= $statusFilter === 'negotiating' ? 'active-filter' : '' ?>"
                >
                    Negotiating
                </a>

                <a
                    href="consumer-demands.php?status=fulfilled"
                    class="<?= $statusFilter === 'fulfilled' ? 'active-filter' : '' ?>"
                >
                    Fulfilled
                </a>

                <a
                    href="consumer-demands.php?status=closed"
                    class="<?= $statusFilter === 'closed' ? 'active-filter' : '' ?>"
                >
                    Closed
                </a>

                <a
                    href="consumer-demands.php?status=cancelled"
                    class="<?= $statusFilter === 'cancelled' ? 'active-filter' : '' ?>"
                >
                    Cancelled
                </a>

            </div>

        </section>


        <!-- DEMAND LIST -->

        <?php if (empty($demands)): ?>

            <section class="empty-demand-state">

                <div class="empty-icon">
                    📢
                </div>

                <h2>
                    No demand broadcasts yet
                </h2>

                <p>
                    Tell local farmers what agricultural products
                    you need and let them send you their best offers.
                </p>

                <a
                    href="post-demand.php"
                    class="empty-action"
                >
                    Broadcast Your First Demand
                </a>

            </section>

        <?php else: ?>


            <?php foreach ($demands as $demand): ?>

                <?php

                $demandId = (int) $demand["id"];

                $offerCount = (int) $demand["offer_count"];

                $demandStatus = $demand["status"];

                $targetPrice = null;

                if ($demand["target_price"] !== null) {
                    $targetPrice = (float) $demand["target_price"];
                }

                ?>


                <article class="demand-card">


                    <!-- DEMAND HEADER -->

                    <div class="demand-top">

                        <div class="demand-title-area">

                            <div class="demand-id">
                                Request #DR-<?= str_pad(
                                    $demandId,
                                    4,
                                    "0",
                                    STR_PAD_LEFT
                                ) ?>
                            </div>

                            <h3>

                                <?= e($demand["crop_name"]) ?>

                                <span class="quantity-title">

                                    (<?= e($demand["quantity"]) ?>
                                    <?= e($demand["unit"]) ?>)

                                </span>

                            </h3>


                            <div class="demand-meta">

                                Broadcasted:

                                <strong>
                                    <?= formatDateTime($demand["created_at"]) ?>
                                </strong>


                                <?php if ($targetPrice !== null): ?>

                                    <span class="meta-separator">
                                        •
                                    </span>

                                    Target Price:

                                    <strong>
                                        ৳<?= number_format(
                                            $targetPrice,
                                            2
                                        ) ?>/<?= e($demand["unit"]) ?>
                                    </strong>

                                <?php endif; ?>


                                <?php if (!empty($demand["delivery_by"])): ?>

                                    <span class="meta-separator">
                                        •
                                    </span>

                                    Delivery By:

                                    <strong>
                                        <?= formatDate(
                                            $demand["delivery_by"]
                                        ) ?>
                                    </strong>

                                <?php endif; ?>

                            </div>

                        </div>


                        <div class="demand-status-area">

                            <span
                                class="demand-status <?= getDemandStatusClass($demandStatus) ?>"
                            >
                                <?= getDemandStatusText($demandStatus) ?>
                            </span>

                            <span class="offer-count">

                                <?= $offerCount ?>

                                <?= $offerCount === 1
                                    ? "Farmer Offer"
                                    : "Farmer Offers" ?>

                            </span>

                        </div>

                    </div>


                    <!-- DEMAND DETAILS -->

                    <div class="demand-details">


                        <?php if (!empty($demand["location"])): ?>

                            <div class="detail-box">

                                <span class="detail-icon">
                                    📍
                                </span>

                                <div>

                                    <small>
                                        Delivery Location
                                    </small>

                                    <strong>
                                        <?= e($demand["location"]) ?>
                                    </strong>

                                </div>

                            </div>

                        <?php endif; ?>


                        <div class="detail-box">

                            <span class="detail-icon">
                                📦
                            </span>

                            <div>

                                <small>
                                    Requested Quantity
                                </small>

                                <strong>
                                    <?= e($demand["quantity"]) ?>
                                    <?= e($demand["unit"]) ?>
                                </strong>

                            </div>

                        </div>


                        <?php if ($targetPrice !== null): ?>

                            <div class="detail-box">

                                <span class="detail-icon">
                                    💰
                                </span>

                                <div>

                                    <small>
                                        Target Price
                                    </small>

                                    <strong>
                                        ৳<?= number_format(
                                            $targetPrice,
                                            2
                                        ) ?>/<?= e($demand["unit"]) ?>
                                    </strong>

                                </div>

                            </div>

                        <?php endif; ?>


                        <div class="detail-box">

                            <span class="detail-icon">
                                🌾
                            </span>

                            <div>

                                <small>
                                    Category
                                </small>

                                <strong>
                                    <?= e($demand["category"]) ?>
                                </strong>

                            </div>

                        </div>

                    </div>


                    <!-- DESCRIPTION -->

                    <?php if (!empty($demand["description"])): ?>

                        <div class="demand-description">

                            <strong>
                                Requirements:
                            </strong>

                            <?= nl2br(
                                e($demand["description"])
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <!-- FARMER OFFERS -->

                    <div class="offers-container">

                        <div class="offers-heading">

                            <div>

                                <h4>
                                    Compare Farmer Fulfillment Offers
                                </h4>

                                <span>
                                    Review offers and negotiate the
                                    terms that work best for you.
                                </span>

                            </div>

                            <span class="fr-badge">
                                FR-23
                            </span>

                        </div>


                        <?php if (empty($demand["offers"])): ?>

                            <div class="no-offers">

                                <div class="no-offers-icon">
                                    ⏳
                                </div>

                                <div>

                                    <strong>
                                        Waiting for farmer offers
                                    </strong>

                                    <p>
                                        Your demand has been broadcast.
                                        Farmers can now review it and
                                        submit their offers.
                                    </p>

                                </div>

                            </div>

                        <?php else: ?>


                            <div class="offers-list">

                                <?php foreach ($demand["offers"] as $offer): ?>

                                    <?php

                                    $offerId = (int) $offer["id"];

                                    $offerPrice =
                                        (float) $offer["offer_price"];

                                    $offerQuantity =
                                        (float) $offer["quantity"];

                                    $offerStatus =
                                        $offer["status"];

                                    $difference = null;

                                    if ($targetPrice !== null) {

                                        $difference =
                                            $offerPrice - $targetPrice;
                                    }

                                    ?>


                                    <div class="offer-item">


                                        <!-- NEGOTIATION PARTICIPANT -->

                                        <div class="farmer-badge">

                                            <div class="farmer-avatar">
                                                    <?= $offer["sender_type"] === "consumer"
                                                        ? "💬"
                                                        : "👨‍🌾" ?>
                                            </div>

                                            <div>

                                                <strong>
                                                    <?= $offer["sender_type"] === "consumer"
                                                        ? "Your counter-offer"
                                                        : e($offer["farmer_name"]) ?>
                                                </strong>

                                                <span>

                                                    <?php if (
                                                        !empty(
                                                            $offer[
                                                                "farmer_address"
                                                            ]
                                                        )
                                                    ): ?>

                                                        <?= e(
                                                            $offer[
                                                                "farmer_address"
                                                            ]
                                                        ) ?>

                                                    <?php else: ?>

                                                        <?= $offer["sender_type"] === "consumer"
                                                            ? "Waiting for farmer response"
                                                            : "AgroLink Farmer" ?>

                                                    <?php endif; ?>

                                                </span>

                                            </div>

                                        </div>


                                        <!-- PRICE -->

                                        <div class="offer-data">

                                            <span>
                                                Offer Price
                                            </span>

                                            <strong
                                                class="<?= (
                                                    $difference !== null &&
                                                    $difference <= 0
                                                )
                                                    ? 'price-good'
                                                    : '' ?>"
                                            >

                                                ৳<?= number_format(
                                                    $offerPrice,
                                                    2
                                                ) ?>

                                                /<?= e(
                                                    $offer["unit"]
                                                ) ?>

                                            </strong>


                                            <?php if (
                                                $difference !== null
                                            ): ?>

                                                <?php if (
                                                    $difference < 0
                                                ): ?>

                                                    <small class="price-below">
                                                        ৳<?= number_format(
                                                            abs($difference),
                                                            2
                                                        ) ?>
                                                        below target
                                                    </small>

                                                <?php elseif (
                                                    $difference > 0
                                                ): ?>

                                                    <small class="price-above">
                                                        ৳<?= number_format(
                                                            $difference,
                                                            2
                                                        ) ?>
                                                        above target
                                                    </small>

                                                <?php else: ?>

                                                    <small class="price-equal">
                                                        Matches target price
                                                    </small>

                                                <?php endif; ?>

                                            <?php endif; ?>

                                        </div>


                                        <!-- QUANTITY -->

                                        <div class="offer-data">

                                            <span>
                                                Available Quantity
                                            </span>

                                            <strong>
                                                <?= number_format(
                                                    $offerQuantity,
                                                    2
                                                ) ?>
                                                <?= e(
                                                    $offer["unit"]
                                                ) ?>
                                            </strong>

                                        </div>


                                        <!-- DELIVERY -->

                                        <div class="offer-data">

                                            <span>
                                                Delivery
                                            </span>

                                            <strong>

                                                <?php if (
                                                    !empty(
                                                        $offer[
                                                            "delivery_date"
                                                        ]
                                                    )
                                                ): ?>

                                                    <?= formatDate(
                                                        $offer[
                                                            "delivery_date"
                                                        ]
                                                    ) ?>

                                                <?php else: ?>

                                                    Not specified

                                                <?php endif; ?>

                                            </strong>

                                        </div>


                                        <!-- STATUS -->

                                        <div class="offer-status-area">

                                            <span
                                                class="offer-status <?= getOfferStatusClass(
                                                    $offerStatus
                                                ) ?>"
                                            >
                                                <?= ucfirst(
                                                    $offerStatus
                                                ) ?>
                                            </span>

                                        </div>


                                        <!-- ACTIONS -->

                                        <div class="offer-actions">

                                            <?php if (
                                                $offer["sender_type"] === "farmer" &&
                                                in_array(
                                                    $offerStatus,
                                                    ["pending", "countered"],
                                                    true
                                                )
                                            ): ?>

                                                <a
                                                    href="negotiation.php?offer=<?= $offerId ?>"
                                                    class="negotiate-btn"
                                                >
                                                    Negotiate / Counter 💬
                                                </a>

                                                <a
                                                    href="negotiation.php?offer=<?= $offerId ?>&action=accept"
                                                    class="accept-btn"
                                                >
                                                    Accept Offer ✓
                                                </a>

                                            <?php elseif (
                                                $offerStatus === "accepted"
                                            ): ?>

                                                <span class="accepted-label">
                                                    ✓ Offer Accepted
                                                </span>

                                            <?php elseif (
                                                $offerStatus === "rejected"
                                            ): ?>

                                                <span class="rejected-label">
                                                    Offer Rejected
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- DEMAND FOOTER -->

                    <div class="demand-footer">

                        <span>
                            Request created
                            <?= formatDateTime(
                                $demand["created_at"]
                            ) ?>
                        </span>


                        <?php if (
                            $demandStatus === "open" ||
                            $demandStatus === "negotiating"
                        ): ?>

                            <a
                                href="post-demand.php?edit=<?= $demandId ?>"
                                class="edit-demand-btn"
                            >
                                Edit Demand
                            </a>

                        <?php endif; ?>

                    </div>


                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    <div class="container footer-grid">


        <div class="footer-about">

            <a
                href="consumer-dashboard.php"
                class="logo footer-logo"
            >

                <span class="logo-icon">
                    🌱
                </span>

                <span>
                    Agro<span>Link</span>
                </span>

            </a>

            <p>
                Connecting farmers and consumers through
                a smarter agricultural marketplace.
            </p>

        </div>


        <div class="footer-column">

            <h3>
                Consumer
            </h3>

            <a href="consumer-dashboard.php">
                Dashboard
            </a>

            <a href="marketplace.php">
                Marketplace
            </a>

            <a href="future-harvests.php">
                Future Harvests
            </a>

            <a href="consumer-demands.php">
                Demand Broadcasts
            </a>

        </div>


        <div class="footer-column">

            <h3>
                Account
            </h3>

            <a href="consumer-profile.php">
                My Profile
            </a>

            <a href="my-orders.php">
                My Orders
            </a>

            <a href="cart.php">
                Cart
            </a>

            <a href="logout.php">
                Logout
            </a>

        </div>


        <div class="footer-column">

            <h3>
                Support
            </h3>

            <a href="about.html">
                About Us
            </a>

            <a href="contact.html">
                Contact Us
            </a>

            <a href="#">
                FAQ
            </a>

        </div>

    </div>


    <div class="footer-bottom">

        <div class="container">

            <p>
                © 2026 AgroLink. All Rights Reserved.
            </p>

            <p>
                Academic Project
            </p>

        </div>

    </div>

</footer>


</body>

</html>