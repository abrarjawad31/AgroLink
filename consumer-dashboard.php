<?php

session_start();

require_once "auth.php";

// Only logged-in Consumers can access this page
requireConsumer();

require_once "config.php";

/* =====================================================
   HELPER
   ===================================================== */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* =====================================================
   LOGGED-IN CONSUMER
   ===================================================== */

$consumerId = (int) $_SESSION["user_id"];

$userName = $_SESSION["user_name"] ?? "Consumer";

$avatarLetter = strtoupper(
    substr($userName, 0, 1)
);


/* =====================================================
   TOTAL ORDERS
   ===================================================== */

$totalOrders = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE consumer_id = ?
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $totalOrders = (int) $row["total"];
}

$stmt->close();


/* =====================================================
   PROCESSING ORDERS
   ===================================================== */

$processingOrders = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE consumer_id = ?
    AND status IN ('confirmed', 'processing')
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $processingOrders = (int) $row["total"];
}

$stmt->close();


/* =====================================================
   COMPLETED ORDERS
   ===================================================== */

$completedOrders = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM orders
    WHERE consumer_id = ?
    AND status = 'delivered'
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $completedOrders = (int) $row["total"];
}

$stmt->close();


/* =====================================================
   CART ITEMS
   ===================================================== */

$cartItemsCount = 0;

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(ci.quantity), 0) AS total
    FROM cart_items ci
    INNER JOIN cart c
        ON c.id = ci.cart_id
    WHERE c.consumer_id = ?
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $cartItemsCount = (int) $row["total"];
}

$stmt->close();


/* =====================================================
   RECENT ORDERS
   ===================================================== */

$recentOrders = [];

$stmt = $conn->prepare("
    SELECT
        o.id,
        o.total_amount,
        o.status,
        o.created_at
    FROM orders o
    WHERE o.consumer_id = ?
    ORDER BY o.created_at DESC
    LIMIT 3
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recentOrders[] = $row;
}

$stmt->close();


/* =====================================================
   RECENT ORDER PRODUCT NAMES
   ===================================================== */

$recentOrderItems = [];

if (!empty($recentOrders)) {

    $recentOrderIds = array_column(
        $recentOrders,
        "id"
    );

    $placeholders = implode(
        ",",
        array_fill(
            0,
            count($recentOrderIds),
            "?"
        )
    );

    $types = str_repeat(
        "i",
        count($recentOrderIds)
    );

    $sql = "
        SELECT
            oi.order_id,
            oi.product_name
        FROM order_items oi
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.id ASC
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        $types,
        ...$recentOrderIds
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $orderId = (int) $row["order_id"];

        if (!isset($recentOrderItems[$orderId])) {
            $recentOrderItems[$orderId] = [];
        }

        $recentOrderItems[$orderId][] =
            $row["product_name"];
    }

    $stmt->close();
}


/* =====================================================
   RECOMMENDED PRODUCTS
   ===================================================== */

$recommendedProducts = [];

$sql = "
    SELECT
        p.id,
        p.name,
        p.category,
        p.price,
        p.unit,
        p.image,
        u.name AS farmer_name
    FROM products p
    INNER JOIN users u
        ON u.id = p.farmer_id
    WHERE p.status = 'active'
    AND u.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 3
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $recommendedProducts[] = $row;
    }
}


/* =====================================================
   STATUS HELPERS
   ===================================================== */

function getOrderStatusLabel($status)
{
    switch ($status) {

        case "pending":
            return "Pending";

        case "confirmed":
            return "Confirmed";

        case "processing":
            return "Processing";

        case "shipped":
            return "Shipped";

        case "delivered":
            return "Delivered";

        case "cancelled":
            return "Cancelled";

        default:
            return ucfirst($status);
    }
}


function getOrderStatusClass($status)
{
    switch ($status) {

        case "pending":
            return "processing";

        case "confirmed":
            return "processing";

        case "processing":
            return "processing";

        case "shipped":
            return "shipped";

        case "delivered":
            return "delivered";

        case "cancelled":
            return "cancelled";

        default:
            return "processing";
    }
}


/* =====================================================
   PRODUCT IMAGE
   ===================================================== */

function getProductImage($image)
{
    if (empty($image)) {
        return "images/product-placeholder.jpg";
    }

    if (
        strpos($image, "http://") === 0 ||
        strpos($image, "https://") === 0
    ) {
        return $image;
    }

    return $image;
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Consumer Dashboard | AgroLink</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/consumer.css"
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


<!-- =====================================================
     NAVBAR
     ===================================================== -->

<header class="header">

    <div class="container navbar">


        <!-- LOGO -->

        <a
            href="consumer-dashboard.php"
            class="logo"
        >

            <span class="logo-icon">
                🌱
            </span>

            <span>
                Agro<span>Link</span>
            </span>

        </a>


        <!-- NAVIGATION -->

        <nav class="nav-menu">

            <a
                href="consumer-dashboard.php"
                class="active-nav"
            >
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

            <a href="consumer-demands.php">
                My Demands
            </a>

        </nav>


        <!-- USER AREA -->

        <div class="consumer-actions">


            <!-- CART -->

            <a
                href="cart.php"
                class="cart-link"
            >

                <span>
                    🛒
                </span>

                Cart

                <span class="cart-count">
                    <?= $cartItemsCount ?>
                </span>

            </a>


            <!-- PROFILE -->

            <a
                href="consumer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">

                    <?= e($avatarLetter) ?>

                </span>

                <span class="profile-name">

                    <?= e($userName) ?>

                </span>

            </a>


            <!-- LOGOUT -->

            <a
                href="logout.php"
                class="logout-btn"
            >
                Logout
            </a>


        </div>

    </div>

</header>


<!-- =====================================================
     DASHBOARD
     ===================================================== -->

<main class="dashboard">

    <div class="container">


        <!-- =================================================
             WELCOME
             ================================================= -->

        <section class="welcome-section">

            <div>

                <span class="welcome-tag">
                    CONSUMER DASHBOARD
                </span>

                <h1>

                    Welcome,

                    <span>
                        <?= e($userName) ?>!
                    </span>

                    👋

                </h1>

                <p>
                    Here's what's happening with your
                    AgroLink account today.
                </p>

            </div>


            <a
                href="marketplace.php"
                class="shop-button"
            >
                Browse Marketplace
            </a>

        </section>


        <!-- =================================================
             STAT CARDS
             ================================================= -->

        <section class="stats-grid">


            <!-- TOTAL ORDERS -->

            <div class="stat-card">

                <div class="stat-icon orders-icon">
                    📦
                </div>

                <div>

                    <span class="stat-label">
                        Total Orders
                    </span>

                    <h2>
                        <?= $totalOrders ?>
                    </h2>

                </div>

            </div>


            <!-- PROCESSING -->

            <div class="stat-card">

                <div class="stat-icon processing-icon">
                    🚚
                </div>

                <div>

                    <span class="stat-label">
                        Processing
                    </span>

                    <h2>
                        <?= $processingOrders ?>
                    </h2>

                </div>

            </div>


            <!-- COMPLETED -->

            <div class="stat-card">

                <div class="stat-icon completed-icon">
                    ✅
                </div>

                <div>

                    <span class="stat-label">
                        Completed
                    </span>

                    <h2>
                        <?= $completedOrders ?>
                    </h2>

                </div>

            </div>


            <!-- CART -->

            <div class="stat-card">

                <div class="stat-icon cart-icon">
                    🛒
                </div>

                <div>

                    <span class="stat-label">
                        Cart Items
                    </span>

                    <h2>
                        <?= $cartItemsCount ?>
                    </h2>

                </div>

            </div>


        </section>


        <!-- =================================================
             MAIN CONTENT
             ================================================= -->

        <section class="dashboard-grid">


            <!-- =================================================
                 RECENT ORDERS
                 ================================================= -->

            <div class="dashboard-card orders-card">


                <div class="card-header">

                    <div>

                        <h2>
                            Recent Orders
                        </h2>

                        <p>
                            Your latest purchases
                        </p>

                    </div>

                    <a href="my-orders.php">
                        View All
                    </a>

                </div>


                <?php if (empty($recentOrders)): ?>


                    <div class="order-item">

                        <div class="order-product">

                            <div class="product-icon">
                                📦
                            </div>

                            <div>

                                <strong>
                                    No orders yet
                                </strong>

                                <span>
                                    Start shopping from the marketplace.
                                </span>

                            </div>

                        </div>

                    </div>


                <?php else: ?>


                    <?php foreach ($recentOrders as $order): ?>

                        <?php

                        $orderId = (int) $order["id"];

                        $orderNumber =
                            "#AGL-" .
                            str_pad(
                                $orderId,
                                5,
                                "0",
                                STR_PAD_LEFT
                            );

                        $status =
                            $order["status"];

                        $statusLabel =
                            getOrderStatusLabel(
                                $status
                            );

                        $statusClass =
                            getOrderStatusClass(
                                $status
                            );

                        $productNames =
                            $recentOrderItems[$orderId]
                            ?? [];

                        if (!empty($productNames)) {

                            $productText =
                                implode(
                                    ", ",
                                    array_slice(
                                        $productNames,
                                        0,
                                        2
                                    )
                                );

                            if (count($productNames) > 2) {

                                $productText .=
                                    " +" .
                                    (count($productNames) - 2) .
                                    " more";
                            }

                        } else {

                            $productText =
                                "Order items";

                        }

                        ?>

                        <div class="order-item">


                            <div class="order-product">


                                <div class="product-icon">
                                    📦
                                </div>


                                <div>

                                    <strong>
                                        <?= e($orderNumber) ?>
                                    </strong>

                                    <span>
                                        <?= e($productText) ?>
                                    </span>

                                </div>


                            </div>


                            <div class="order-info">

                                <span
                                    class="status <?= e($statusClass) ?>"
                                >
                                    <?= e($statusLabel) ?>
                                </span>

                                <strong>
                                    ৳<?= number_format(
                                        (float)$order["total_amount"],
                                        2
                                    ) ?>
                                </strong>

                            </div>


                        </div>

                    <?php endforeach; ?>


                <?php endif; ?>


            </div>


            <!-- =================================================
                 QUICK ACTIONS
                 ================================================= -->

            <div class="dashboard-card quick-card">


                <div class="card-header">

                    <div>

                        <h2>
                            Quick Actions
                        </h2>

                        <p>
                            Manage your account
                        </p>

                    </div>

                </div>


                <div class="quick-actions">


                    <!-- SHOP -->

                    <a
                        href="marketplace.php"
                        class="quick-action"
                    >

                        <span>
                            🛍️
                        </span>

                        <div>

                            <strong>
                                Shop Products
                            </strong>

                            <small>
                                Browse marketplace
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </a>


                    <!-- ORDERS -->

                    <a
                        href="my-orders.php"
                        class="quick-action"
                    >

                        <span>
                            📦
                        </span>

                        <div>

                            <strong>
                                My Orders
                            </strong>

                            <small>
                                Track your orders
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </a>


                    <!-- PROFILE -->

                    <a
                        href="consumer-profile.php"
                        class="quick-action"
                    >

                        <span>
                            👤
                        </span>

                        <div>

                            <strong>
                                My Profile
                            </strong>

                            <small>
                                Update your information
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </a>


                </div>

            </div>


        </section>


        <!-- =================================================
             RECOMMENDED PRODUCTS
             ================================================= -->

        <section class="featured-section">


            <div class="section-heading">

                <div>

                    <span>
                        EXPLORE
                    </span>

                    <h2>
                        Recommended For You
                    </h2>

                </div>

                <a href="marketplace.php">
                    View Marketplace →
                </a>

            </div>


            <div class="product-grid">


                <?php if (empty($recommendedProducts)): ?>


                    <div class="no-products">

                        <p>
                            No products are available right now.
                        </p>

                        <a href="marketplace.php">
                            Browse Marketplace
                        </a>

                    </div>


                <?php else: ?>


                    <?php foreach ($recommendedProducts as $product): ?>

                        <?php

                        $productImage =
                            getProductImage(
                                $product["image"] ?? ""
                            );

                        ?>

                        <div class="product-card">


                            <div class="product-image">

                                <img
                                    src="<?= e($productImage) ?>"
                                    alt="<?= e($product["name"]) ?>"
                                    onerror="this.src='images/product-placeholder.jpg';"
                                >

                            </div>


                            <div class="product-content">


                                <span class="product-category">

                                    <?= e(
                                        $product["category"]
                                    ) ?>

                                </span>


                                <h3>

                                    <?= e(
                                        $product["name"]
                                    ) ?>

                                </h3>


                                <p class="farmer-name">

                                    🌾
                                    <?= e(
                                        $product["farmer_name"]
                                    ) ?>

                                </p>


                                <div class="product-bottom">


                                    <strong>

                                        ৳<?= number_format(
                                            (float)$product["price"],
                                            2
                                        ) ?>

                                        <?php if (!empty($product["unit"])): ?>

                                            <small>
                                                /<?= e(
                                                    $product["unit"]
                                                ) ?>
                                            </small>

                                        <?php endif; ?>

                                    </strong>


                                    <a
                                        href="product-details.php?id=<?= (int)$product["id"] ?>"
                                    >
                                        View
                                    </a>


                                </div>


                            </div>

                        </div>

                    <?php endforeach; ?>


                <?php endif; ?>


            </div>


        </section>


    </div>

</main>


<!-- =====================================================
     FOOTER
     ===================================================== -->

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

            <a href="marketplace.php">
                Marketplace
            </a>

            <a href="my-orders.php">
                My Orders
            </a>

            <a href="cart.php">
                Cart
            </a>

        </div>


        <div class="footer-column">

            <h3>
                Support
            </h3>

            <a href="about.php">
                About Us
            </a>

            <a href="contact.php">
                Contact Us
            </a>

            <a href="#">
                FAQ
            </a>

        </div>


        <div class="footer-column">

            <h3>
                Account
            </h3>

            <a href="consumer-profile.php">
                My Profile
            </a>

            <a href="#">
                Settings
            </a>

            <a href="logout.php">
                Logout
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