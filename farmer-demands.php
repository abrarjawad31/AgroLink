<?php

require_once "auth.php";
requireFarmer();

require_once "config.php";

$farmerId = (int) $_SESSION["user_id"];
$farmerName = $_SESSION["user_name"] ?? "Farmer";

$message = "";
$messageType = "";

/*
|--------------------------------------------------------------------------
| Accept Consumer Counter
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["accept_counter"])
) {

    $counterOfferId = (int) ($_POST["counter_offer_id"] ?? 0);

    $acceptStmt = $conn->prepare("
        SELECT
            do.id,
            do.demand_id
        FROM demand_offers do
        INNER JOIN demands d
            ON d.id = do.demand_id
        WHERE do.id = ?
          AND do.farmer_id = ?
          AND (
              (
                  do.sender_type = 'consumer'
                  AND do.status IN ('pending', 'countered')
              )
              OR (
                  do.sender_type = 'farmer'
                  AND do.status = 'countered'
              )
          )
          AND d.status = 'negotiating'
        LIMIT 1
    ");

    $acceptStmt->bind_param("ii", $counterOfferId, $farmerId);
    $acceptStmt->execute();

    $acceptedCounter = $acceptStmt->get_result()->fetch_assoc();
    $acceptStmt->close();

    if (!$acceptedCounter) {

        $message = "This consumer counter-offer is no longer available.";
        $messageType = "error";

    } else {

        $updateOffer = $conn->prepare("
            UPDATE demand_offers
            SET status = 'accepted'
            WHERE id = ?
                            AND (
                                    sender_type = 'consumer'
                                    OR (
                                            sender_type = 'farmer'
                                            AND status = 'countered'
                                    )
                            )
        ");

        $updateOffer->bind_param("i", $counterOfferId);

        if ($updateOffer->execute() && $updateOffer->affected_rows > 0) {

            $updateDemand = $conn->prepare("
                UPDATE demands
                SET status = 'fulfilled'
                WHERE id = ?
            ");

            $updateDemand->bind_param("i", $acceptedCounter["demand_id"]);
            $updateDemand->execute();
            $updateDemand->close();

            $message = "Consumer counter-offer accepted successfully.";
            $messageType = "success";

        } else {

            $message = "Unable to accept this counter-offer.";
            $messageType = "error";
        }

        $updateOffer->close();
    }
}

/*
|--------------------------------------------------------------------------
| Submit Offer
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_offer"])) {

    $demandId = (int) ($_POST["demand_id"] ?? 0);
    $offerPrice = (float) ($_POST["offer_price"] ?? 0);
    $quantity = (float) ($_POST["quantity"] ?? 0);
    $unit = trim($_POST["unit"] ?? "kg");
    $deliveryDate = trim($_POST["delivery_date"] ?? "");
    $offerMessage = trim($_POST["message"] ?? "");

    if ($demandId <= 0 || $offerPrice <= 0 || $quantity <= 0) {

        $message = "Please enter a valid offer price and quantity.";
        $messageType = "error";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check whether demand exists and is still open
        |--------------------------------------------------------------------------
        */

        $demandStmt = $conn->prepare("
            SELECT id, quantity, unit, status
            FROM demands
            WHERE id = ?
            LIMIT 1
        ");

        $demandStmt->bind_param("i", $demandId);
        $demandStmt->execute();

        $demandResult = $demandStmt->get_result();
        $demand = $demandResult->fetch_assoc();

        $demandStmt->close();

        if (!$demand) {

            $message = "The requested demand could not be found.";
            $messageType = "error";

        } elseif ($demand["status"] !== "open" && $demand["status"] !== "negotiating") {

            $message = "This demand is no longer accepting offers.";
            $messageType = "error";

        } elseif ($quantity > (float) $demand["quantity"]) {

            $message = "Your offered quantity cannot exceed the requested quantity.";
            $messageType = "error";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Check for existing active offer from this farmer
            |--------------------------------------------------------------------------
            */

            $checkStmt = $conn->prepare("
                SELECT id, sender_type, status
                FROM demand_offers
                WHERE demand_id = ?
                  AND farmer_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");

            $checkStmt->bind_param("ii", $demandId, $farmerId);
            $checkStmt->execute();

            $checkResult = $checkStmt->get_result();
            $existingOffer = $checkResult->fetch_assoc();

            $checkStmt->close();

            if (
                $existingOffer &&
                $existingOffer["sender_type"] === "farmer" &&
                in_array(
                    $existingOffer["status"],
                    ["pending", "countered"],
                    true
                )
            ) {

                $message = "You already have an active offer for this demand.";
                $messageType = "error";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Insert farmer offer
                |--------------------------------------------------------------------------
                */

                if ($deliveryDate === "") {
                    $deliveryDate = null;
                }

                $insertStmt = $conn->prepare("
                    INSERT INTO demand_offers
                    (
                        demand_id,
                        farmer_id,
                        sender_type,
                        offer_price,
                        quantity,
                        unit,
                        delivery_date,
                        message,
                        status
                    )
                    VALUES (?, ?, 'farmer', ?, ?, ?, ?, ?, 'pending')
                ");

                $insertStmt->bind_param(
                    "iiddsss",
                    $demandId,
                    $farmerId,
                    $offerPrice,
                    $quantity,
                    $unit,
                    $deliveryDate,
                    $offerMessage
                );

                if ($insertStmt->execute()) {

                    if (
                        $existingOffer &&
                        $existingOffer["sender_type"] === "consumer"
                    ) {
                        $previousOfferStmt = $conn->prepare("
                            UPDATE demand_offers
                            SET status = 'countered'
                            WHERE id = ?
                        ");

                        $previousOfferStmt->bind_param(
                            "i",
                            $existingOffer["id"]
                        );
                        $previousOfferStmt->execute();
                        $previousOfferStmt->close();
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Change demand status to negotiating
                    |--------------------------------------------------------------------------
                    */

                    $updateDemand = $conn->prepare("
                        UPDATE demands
                        SET status = 'negotiating'
                        WHERE id = ?
                          AND status = 'open'
                    ");

                    $updateDemand->bind_param("i", $demandId);
                    $updateDemand->execute();
                    $updateDemand->close();

                    $message = "Your offer has been submitted successfully. The consumer can now review and negotiate your offer.";
                    $messageType = "success";

                } else {

                    $message = "Something went wrong while submitting your offer.";
                    $messageType = "error";
                }

                $insertStmt->close();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Fetch Active Consumer Demands
|--------------------------------------------------------------------------
*/

$demands = [];

$demandQuery = "
    SELECT
        d.id,
        d.consumer_id,
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
        u.name AS consumer_name
    FROM demands d
    INNER JOIN users u
        ON d.consumer_id = u.id
    WHERE d.status IN ('open', 'negotiating')
    ORDER BY d.created_at DESC
";

$demandResult = $conn->query($demandQuery);

if ($demandResult) {

    while ($row = $demandResult->fetch_assoc()) {
        $demands[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Check which demands already have an active offer from this farmer
|--------------------------------------------------------------------------
*/

$existingOffers = [];

$offerCheckStmt = $conn->prepare("
    SELECT id, demand_id, sender_type, status
    FROM demand_offers
    WHERE farmer_id = ?
    ORDER BY id DESC
");

$offerCheckStmt->bind_param("i", $farmerId);
$offerCheckStmt->execute();

$offerCheckResult = $offerCheckStmt->get_result();

while ($offerRow = $offerCheckResult->fetch_assoc()) {

    $demandId = (int) $offerRow["demand_id"];

    if (!isset($existingOffers[$demandId])) {
        $existingOffers[$demandId] = $offerRow;
    }
}

$offerCheckStmt->close();


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function formatDemandDate($date)
{
    if (!$date) {
        return "Not specified";
    }

    return date("d M Y", strtotime($date));
}

/*
|--------------------------------------------------------------------------
| Fetch Negotiation History
|--------------------------------------------------------------------------
*/

foreach ($demands as &$demand) {

    $demand["offer_history"] = [];

    $historyStmt = $conn->prepare("
        SELECT
            do.offer_price,
            do.quantity,
            do.unit,
            do.delivery_date,
            do.message,
            do.sender_type,
            do.status,
            do.created_at,
            u.name AS farmer_name
        FROM demand_offers do
        INNER JOIN users u
            ON u.id = do.farmer_id
        WHERE do.demand_id = ?
        ORDER BY do.id ASC
    ");

    $historyDemandId = (int) $demand["id"];
    $historyStmt->bind_param("i", $historyDemandId);
    $historyStmt->execute();

    $historyResult = $historyStmt->get_result();

    while ($historyRow = $historyResult->fetch_assoc()) {
        $demand["offer_history"][] = $historyRow;
    }

    $historyStmt->close();
}

unset($demand);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Consumer Demand Requests | AgroLink</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/farmer-demands.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >

</head>

<body>


<!-- ================= NAVBAR ================= -->

<header class="header">

    <div class="container navbar">

        <a href="farmer.php" class="logo">

            <span class="logo-icon">🌱</span>

            <span>Agro<span>Link</span></span>

        </a>


        <nav class="nav-menu">

            <a href="farmer.php">Dashboard</a>

            <a href="farmer-products.php">My Products</a>

            <a href="farmer-bookings.php">Harvest Bookings</a>

            <a href="farmer-demands.php" class="active-nav">
                Demand Broadcasts
            </a>

            <a href="farmer-orders.php">Orders</a>

            <a href="farmer-dss.php">DSS</a>

        </nav>


        <div class="farmer-actions">

            <a href="#" class="notification-link">🔔</a>

            <a href="farmer-profile.php" class="farmer-profile-link">

                <span class="farmer-avatar">
                    <?= e(strtoupper(substr($farmerName, 0, 1))) ?>
                </span>

                <?= e($farmerName) ?>

            </a>

            <a href="logout.php" class="logout-btn">
                Logout
            </a>

        </div>

    </div>

</header>



<!-- ================= MAIN ================= -->

<main class="farmer-demands-page">

    <div class="container">


        <div class="page-heading">

            <span class="module-label">
                SRS MODULE 3.5: DEMAND-BASED TRADING (FR-21 & FR-22)
            </span>

            <h1>
                Active Consumer Purchasing Demands
            </h1>

            <p>
                Consumers and bulk buyers in your region have posted these
                immediate crop purchase requests. Submit an offer to secure
                orders directly.
            </p>

        </div>


        <?php if ($message !== ""): ?>

            <div class="demand-alert <?= e($messageType) ?>">

                <?= e($message) ?>

            </div>

        <?php endif; ?>


        <div class="demand-market-grid">


            <?php if (empty($demands)): ?>

                <div class="empty-demand-state">

                    <div class="empty-demand-icon">
                        📢
                    </div>

                    <h3>No Active Consumer Demands</h3>

                    <p>
                        There are currently no open purchasing requests from
                        consumers. New demand broadcasts will appear here.
                    </p>

                </div>


            <?php else: ?>


                <?php foreach ($demands as $demand): ?>

                    <?php

                    $demandId = (int) $demand["id"];

                    $latestOffer =
                        $existingOffers[$demandId] ?? null;

                    $hasExistingOffer =
                        $latestOffer &&
                        $latestOffer["sender_type"] === "farmer" &&
                        $latestOffer["status"] === "pending";

                    $hasConsumerCounter =
                        $latestOffer &&
                        $latestOffer["sender_type"] === "consumer";

                    $hasLegacyCounter =
                        $latestOffer &&
                        $latestOffer["sender_type"] === "farmer" &&
                        $latestOffer["status"] === "countered";

                    $statusLabel =
                        $demand["status"] === "negotiating"
                        ? "Negotiating"
                        : "Active Request";

                    ?>

                    <div class="demand-market-card">


                        <div>


                        <?php if (!empty($demand["offer_history"])): ?>

                            <div class="offer-history">

                                <div class="offer-history-heading">
                                    Negotiation History
                                </div>

                                <?php foreach ($demand["offer_history"] as $historyOffer): ?>

                                    <div class="offer-history-item <?= e($historyOffer["sender_type"]) ?>">

                                        <div class="offer-history-meta">
                                            <strong>
                                                <?= $historyOffer["sender_type"] === "consumer"
                                                    ? "Consumer counter-offer"
                                                    : "Your offer" ?>
                                            </strong>

                                            <span>
                                                <?= e(formatDemandDate($historyOffer["created_at"])) ?>
                                            </span>
                                        </div>

                                        <div class="offer-history-values">
                                            <span>
                                                ৳<?= number_format((float) $historyOffer["offer_price"], 2) ?>
                                                / <?= e($historyOffer["unit"]) ?>
                                            </span>

                                            <span>
                                                <?= number_format((float) $historyOffer["quantity"], 2) ?>
                                                <?= e($historyOffer["unit"]) ?>
                                            </span>
                                        </div>

                                        <?php if (!empty($historyOffer["message"])): ?>
                                            <p>
                                                <?= e($historyOffer["message"]) ?>
                                            </p>
                                        <?php endif; ?>

                                    </div>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>


                            <div class="demand-market-top">

                                <div>

                                    <h3>
                                        <?= e($demand["crop_name"]) ?>
                                    </h3>

                                    <span class="demand-poster">
                                        Posted by:
                                        👤
                                        <?= e($demand["consumer_name"]) ?>

                                        <?php if (!empty($demand["location"])): ?>
                                            • <?= e($demand["location"]) ?>
                                        <?php endif; ?>

                                    </span>

                                </div>


                                <span class="demand-tag <?= $demand["status"] === "negotiating" ? "negotiating" : "" ?>">
                                    <?= e($statusLabel) ?>
                                </span>

                            </div>



                            <div class="demand-specs">


                                <div>

                                    <span>
                                        Required Qty:
                                    </span>

                                    <strong>
                                        <?= e($demand["quantity"]) ?>
                                        <?= e($demand["unit"]) ?>
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Buyer's Budget:
                                    </span>

                                    <strong class="budget-price">

                                        <?php if ($demand["target_price"] !== null): ?>

                                            ৳<?= number_format((float) $demand["target_price"], 2) ?>
                                            /
                                            <?= e($demand["unit"]) ?>

                                        <?php else: ?>

                                            Negotiable

                                        <?php endif; ?>

                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Delivery Window:
                                    </span>

                                    <strong>
                                        <?= e(formatDemandDate($demand["delivery_by"])) ?>
                                    </strong>

                                </div>


                                <div>

                                    <span>
                                        Category:
                                    </span>

                                    <strong>
                                        <?= e($demand["category"]) ?>
                                    </strong>

                                </div>


                            </div>



                            <?php if (!empty($demand["description"])): ?>

                                <p class="demand-description">

                                    <em>
                                        "<?= e($demand["description"]) ?>"
                                    </em>

                                </p>

                            <?php endif; ?>


                        </div>



                        <!-- ================= OFFER BOX ================= -->

                        <div class="submit-offer-box">


                            <?php if ($hasExistingOffer): ?>

                                <div class="existing-offer-message">

                                    <strong>✓ Offer Already Submitted</strong>

                                    <span>
                                        Wait for the consumer's response or
                                        counter-offer.
                                    </span>

                                </div>


                            <?php else: ?>

                                <?php if ($hasConsumerCounter): ?>

                                    <div class="counter-response-message">
                                        <strong>
                                            Consumer counter-offer received
                                        </strong>

                                        <span>
                                            Accept the terms or send a new
                                            offer to continue negotiating.
                                        </span>

                                        <div class="counter-response-actions">

                                            <form
                                                method="POST"
                                                action="farmer-demands.php"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="counter_offer_id"
                                                    value="<?= e($latestOffer["id"]) ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="accept_counter"
                                                    class="accept-counter-btn"
                                                >
                                                    Accept Counter-offer
                                                </button>

                                            </form>

                                            <span class="recounter-label">
                                                Or submit a re-counter below
                                            </span>

                                        </div>
                                    </div>

                                <?php elseif ($hasLegacyCounter): ?>

                                    <div class="counter-response-message legacy-counter-message">
                                        <strong>
                                            Counter-offer ready for response
                                        </strong>

                                        <span>
                                            Submit a re-counter below. New negotiations will show each side separately.
                                        </span>

                                        <div class="counter-response-actions">

                                            <form
                                                method="POST"
                                                action="farmer-demands.php"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="counter_offer_id"
                                                    value="<?= e($latestOffer["id"]) ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    name="accept_counter"
                                                    class="accept-counter-btn"
                                                >
                                                    Accept Counter-offer
                                                </button>

                                            </form>

                                            <span class="recounter-label">
                                                Or submit a re-counter below
                                            </span>

                                        </div>
                                    </div>

                                <?php endif; ?>


                                <form
                                    method="POST"
                                    action="farmer-demands.php"
                                    class="offer-form"
                                >

                                    <input
                                        type="hidden"
                                        name="demand_id"
                                        value="<?= $demandId ?>"
                                    >


                                    <div class="offer-form-row">


                                        <div class="offer-field">

                                            <label>
                                                Offer Price
                                            </label>

                                            <input
                                                type="number"
                                                name="offer_price"
                                                min="0.01"
                                                step="0.01"
                                                placeholder="৳ / unit"
                                                required
                                            >

                                        </div>


                                        <div class="offer-field">

                                            <label>
                                                Quantity
                                            </label>

                                            <input
                                                type="number"
                                                name="quantity"
                                                min="0.01"
                                                step="0.01"
                                                max="<?= e($demand["quantity"]) ?>"
                                                placeholder="Quantity"
                                                required
                                            >

                                        </div>


                                        <div class="offer-field">

                                            <label>
                                                Unit
                                            </label>

                                            <select name="unit">

                                                <option value="<?= e($demand["unit"]) ?>">
                                                    <?= e($demand["unit"]) ?>
                                                </option>

                                            </select>

                                        </div>


                                    </div>


                                    <div class="offer-form-row">


                                        <div class="offer-field">

                                            <label>
                                                Delivery Date
                                            </label>

                                            <input
                                                type="date"
                                                name="delivery_date"
                                                value="<?= e($demand["delivery_by"]) ?>"
                                            >

                                        </div>


                                        <div class="offer-field offer-message-field">

                                            <label>
                                                Message
                                            </label>

                                            <input
                                                type="text"
                                                name="message"
                                                placeholder="Add a short message..."
                                                maxlength="500"
                                            >

                                        </div>


                                        <button
                                            type="submit"
                                            name="submit_offer"
                                            class="submit-offer-btn"
                                        >
                                            Submit Offer ➔
                                        </button>

                                    </div>

                                </form>


                            <?php endif; ?>


                        </div>

                    </div>

                <?php endforeach; ?>


            <?php endif; ?>


        </div>

    </div>

</main>



<!-- ================= FOOTER ================= -->

<footer class="footer">

    <div class="container footer-grid">


        <div class="footer-about">

            <a href="farmer.php" class="logo footer-logo">

                <span class="logo-icon">🌱</span>

                <span>Agro<span>Link</span></span>

            </a>

            <p>
                Connecting farmers and consumers through a smarter
                agricultural marketplace.
            </p>

        </div>


        <div class="footer-column">

            <h3>Farmer</h3>

            <a href="farmer.php">Dashboard</a>

            <a href="farmer-products.php">My Products</a>

            <a href="farmer-bookings.php">Harvest Bookings</a>

            <a href="farmer-demands.php">Demand Broadcasts</a>

            <a href="farmer-orders.php">Orders</a>

            <a href="farmer-dss.php">Decision Support</a>

        </div>


        <div class="footer-column">

            <h3>Account</h3>

            <a href="farmer-profile.php">My Profile</a>

            <a href="logout.php">Logout</a>

        </div>


        <div class="footer-column">

            <h3>Support</h3>

            <a href="#">Help Center</a>

            <a href="#">Contact Us</a>

            <a href="#">FAQ</a>

        </div>


    </div>


    <div class="footer-bottom">

        <div class="container">

            <p>© 2026 AgroLink. All Rights Reserved.</p>

            <p>Academic Project</p>

        </div>

    </div>

</footer>


</body>

</html>