<?php
session_start();

require_once "auth.php";
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
   CURRENT CONSUMER
   ===================================================== */

$consumerId = (int) $_SESSION["user_id"];
$consumerName = $_SESSION["user_name"] ?? "Consumer";


// ============================================================
// CART COUNT
// ============================================================

$cart_count = 0;

$cart_stmt = $conn->prepare("
    SELECT COALESCE(SUM(ci.quantity), 0) AS total_items
    FROM cart c
    INNER JOIN cart_items ci ON ci.cart_id = c.id
    WHERE c.consumer_id = ?
");

if ($cart_stmt) {

    $cart_stmt->bind_param("i", $consumerId);
    $cart_stmt->execute();

    $cart_result = $cart_stmt->get_result();

    if ($cart_row = $cart_result->fetch_assoc()) {
        $cart_count = (int) $cart_row["total_items"];
    }

    $cart_stmt->close();
}


// ============================================================
// CONSUMER INFORMATION
// ============================================================

$consumer_name = "Consumer";

$user_stmt = $conn->prepare("
    SELECT name
    FROM users
    WHERE id = ?
      AND role = 'consumer'
    LIMIT 1
");

if ($user_stmt) {

    $user_stmt->bind_param("i", $consumerId);
    $user_stmt->execute();

    $user_result = $user_stmt->get_result();

    if ($user_row = $user_result->fetch_assoc()) {
        $consumer_name = $user_row["name"];
    }

    $user_stmt->close();
}

/* =====================================================
   STATUS FILTER
   ===================================================== */

$filter = $_GET["status"] ?? "all";

$validFilters = [
    "all",
    "pending",
    "processing",
    "shipped",
    "delivered",
    "cancelled"
];

if (!in_array($filter, $validFilters, true)) {
    $filter = "all";
}

/* =====================================================
   GET ORDER COUNT
   ===================================================== */

$countSql = "
    SELECT COUNT(*) AS total
    FROM orders
    WHERE consumer_id = ?
";

$countStmt = $conn->prepare($countSql);
$countStmt->bind_param("i", $consumerId);
$countStmt->execute();

$countResult = $countStmt->get_result();
$orderCountRow = $countResult->fetch_assoc();

$totalOrders = (int) ($orderCountRow["total"] ?? 0);

$countStmt->close();

/* =====================================================
   GET ORDERS
   ===================================================== */

$orderSql = "
    SELECT
        o.id,
        o.total_amount,
        o.status,
        o.payment_method,
        o.delivery_address,
        o.phone,
        o.created_at
    FROM orders o
    WHERE o.consumer_id = ?
";

$params = [$consumerId];
$types = "i";

/*
 * Status filtering
 *
 * "processing" filter includes both:
 * pending
 * confirmed
 * processing
 *
 * This keeps the filter useful for the consumer while
 * preserving the actual database statuses.
 */

if ($filter === "processing") {

    $orderSql .= "
        AND o.status IN ('pending', 'confirmed', 'processing')
    ";

} elseif ($filter !== "all") {

    $orderSql .= "
        AND o.status = ?
    ";

    $params[] = $filter;
    $types .= "s";
}

$orderSql .= "
    ORDER BY o.created_at DESC
";

$orderStmt = $conn->prepare($orderSql);

$orderStmt->bind_param($types, ...$params);

$orderStmt->execute();

$orderResult = $orderStmt->get_result();

/* =====================================================
   STORE ORDERS
   ===================================================== */

$orders = [];

while ($order = $orderResult->fetch_assoc()) {

    $orderId = (int) $order["id"];

    $orders[$orderId] = [
        "id" => $orderId,
        "total_amount" => $order["total_amount"],
        "status" => $order["status"],
        "payment_method" => $order["payment_method"],
        "delivery_address" => $order["delivery_address"],
        "phone" => $order["phone"],
        "created_at" => $order["created_at"],
        "items" => []
    ];
}

$orderStmt->close();

/* =====================================================
   GET ORDER ITEMS
   ===================================================== */

if (!empty($orders)) {

    $orderIds = array_keys($orders);

    $placeholders = implode(
        ",",
        array_fill(0, count($orderIds), "?")
    );

    $itemSql = "
        SELECT
            oi.order_id,
            oi.product_id,
            oi.product_name,
            oi.price,
            oi.quantity,
            oi.subtotal,
            p.image,
            p.unit
        FROM order_items oi
        LEFT JOIN products p
            ON p.id = oi.product_id
        WHERE oi.order_id IN ($placeholders)
        ORDER BY oi.id ASC
    ";

    $itemStmt = $conn->prepare($itemSql);

    $itemTypes = str_repeat("i", count($orderIds));

    $itemStmt->bind_param(
        $itemTypes,
        ...$orderIds
    );

    $itemStmt->execute();

    $itemResult = $itemStmt->get_result();

    while ($item = $itemResult->fetch_assoc()) {

        $orderId = (int) $item["order_id"];

        if (isset($orders[$orderId])) {

            $orders[$orderId]["items"][] = $item;
        }
    }

    $itemStmt->close();
}

/* =====================================================
   STATUS DISPLAY HELPER
   ===================================================== */

function getStatusLabel($status)
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

/* =====================================================
   STATUS CSS CLASS
   ===================================================== */

function getStatusClass($status)
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
   IMAGE HELPER
   ===================================================== */

function getProductImage($image)
{
    if (empty($image)) {
        return "images/product-placeholder.jpg";
    }

    /*
     * If database already contains a complete URL,
     * use it directly.
     */

    if (
        strpos($image, "http://") === 0 ||
        strpos($image, "https://") === 0
    ) {
        return $image;
    }

    /*
     * Otherwise use the stored image path.
     */

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

    <title>My Orders | AgroLink</title>

    <!-- Common CSS -->
    <link rel="stylesheet" href="css/style.css">

    <!-- Page CSS -->
    <link rel="stylesheet" href="css/my-orders.css">

    <!-- Google Fonts -->

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

                <a href="consumer-dashboard.php">
                    Home
                </a>

                <a href="marketplace.php">
                    Marketplace
                </a>

                <a href="future-harvests.php">
                    Pre Bookings
                </a>

                <a href="my-orders.php" class="active-nav">
                    My Orders
                </a>

                <a href="consumer-demands.php">
                    My Demands
                </a>

            </nav>


        <!-- CONSUMER ACTIONS -->

        <div class="consumer-actions">

            <a
                href="cart.php"
                class="cart-link"
            >

                <span>
                    🛒
                </span>

                Cart

                <span class="cart-count">
                    <?= $cart_count ?>
                </span>

            </a>


            <a
                href="consumer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">
                    <?= htmlspecialchars(
                        strtoupper(substr($consumer_name, 0, 1))
                    ) ?>
                </span>

                <span class="profile-name">
                    <?= htmlspecialchars($consumer_name) ?>
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


<!-- =====================================================
     PAGE HEADER
     ===================================================== -->

<section class="orders-hero">

    <div class="container">

        <span class="section-tag">
            ORDER HISTORY
        </span>

        <h1>
            My <span>Orders</span>
        </h1>

        <p>
            Track and manage all your AgroLink orders in one place.
        </p>

    </div>

</section>


<!-- =====================================================
     ORDERS
     ===================================================== -->

<main class="orders-section">

    <div class="container">


        <!-- =================================================
             ORDER TOOLBAR
             ================================================= -->

        <div class="orders-toolbar">


            <div class="orders-count">

                <strong>
                    <?= $totalOrders ?>
                    <?= $totalOrders === 1 ? "Order" : "Orders" ?>
                </strong>

                <span>
                    in your order history
                </span>

            </div>


            <!-- FILTERS -->

            <div class="order-filters">

                <a
                    href="my-orders.php?status=all"
                    class="filter-btn <?= $filter === "all" ? "active" : "" ?>"
                >
                    All
                </a>


                <a
                    href="my-orders.php?status=pending"
                    class="filter-btn <?= $filter === "pending" ? "active" : "" ?>"
                >
                    Pending
                </a>


                <a
                    href="my-orders.php?status=processing"
                    class="filter-btn <?= $filter === "processing" ? "active" : "" ?>"
                >
                    Processing
                </a>


                <a
                    href="my-orders.php?status=shipped"
                    class="filter-btn <?= $filter === "shipped" ? "active" : "" ?>"
                >
                    Shipped
                </a>


                <a
                    href="my-orders.php?status=delivered"
                    class="filter-btn <?= $filter === "delivered" ? "active" : "" ?>"
                >
                    Delivered
                </a>


                <a
                    href="my-orders.php?status=cancelled"
                    class="filter-btn <?= $filter === "cancelled" ? "active" : "" ?>"
                >
                    Cancelled
                </a>

            </div>

        </div>


        <!-- =================================================
             ORDERS LIST
             ================================================= -->

        <?php if (empty($orders)): ?>

            <div class="empty-orders">

                <div class="empty-orders-icon">
                    🛒
                </div>

                <h2>
                    No Orders Found
                </h2>

                <p>
                    <?php if ($totalOrders === 0): ?>

                        You haven't placed any orders yet.

                    <?php else: ?>

                        There are no orders matching this status.

                    <?php endif; ?>
                </p>

                <a
                    href="marketplace.php"
                    class="shop-now-btn"
                >
                    Browse Marketplace
                </a>

            </div>

        <?php else: ?>


            <?php foreach ($orders as $order): ?>

                <?php

                $status = $order["status"];

                $statusLabel = getStatusLabel($status);

                $statusClass = getStatusClass($status);

                $orderNumber = "#AGL-" . str_pad(
                    $order["id"],
                    5,
                    "0",
                    STR_PAD_LEFT
                );

                $orderDate = date(
                    "F d, Y",
                    strtotime($order["created_at"])
                );

                ?>

                <div class="order-card">


                    <!-- =================================================
                         ORDER HEADER
                         ================================================= -->

                    <div class="order-header">


                        <div>

                            <span class="order-label">
                                ORDER ID
                            </span>

                            <strong>
                                <?= e($orderNumber) ?>
                            </strong>

                        </div>


                        <div>

                            <span class="order-label">
                                ORDER DATE
                            </span>

                            <strong>
                                <?= e($orderDate) ?>
                            </strong>

                        </div>


                        <span
                            class="status <?= e($statusClass) ?>"
                        >
                            <?= e($statusLabel) ?>
                        </span>

                    </div>


                    <!-- =================================================
                         ORDER PRODUCTS
                         ================================================= -->

                    <div class="order-products">


                        <?php foreach ($order["items"] as $item): ?>

                            <?php

                            $image = getProductImage(
                                $item["image"] ?? ""
                            );

                            $quantity = (float) $item["quantity"];

                            $price = (float) $item["price"];

                            $subtotal = (float) $item["subtotal"];

                            $unit = $item["unit"] ?? "";

                            ?>

                            <div class="order-product">


                                <img
                                    src="<?= e($image) ?>"
                                    alt="<?= e($item["product_name"]) ?>"
                                    onerror="this.src='images/product-placeholder.jpg';"
                                >


                                <div>

                                    <h3>
                                        <?= e($item["product_name"]) ?>
                                    </h3>

                                    <span>

                                        <?= e(rtrim(rtrim(number_format($quantity, 2, ".", ""), "0"), ".")) ?>

                                        <?php if (!empty($unit)): ?>
                                            <?= e($unit) ?>
                                        <?php endif; ?>

                                        × ৳<?= number_format($price, 2) ?>

                                    </span>

                                </div>


                                <strong>
                                    ৳<?= number_format($subtotal, 2) ?>
                                </strong>

                            </div>

                        <?php endforeach; ?>


                    </div>


                    <!-- =================================================
                         ORDER FOOTER
                         ================================================= -->

                    <div class="order-footer">


                        <div class="order-delivery">

                            <span>

                                <?php if ($status === "delivered"): ?>

                                    🚚 Delivered to

                                <?php else: ?>

                                    🚚 Delivery to

                                <?php endif; ?>

                            </span>

                            <strong>
                                <?= e($order["delivery_address"]) ?>
                            </strong>

                        </div>


                        <div class="order-payment">

                            <span>
                                Payment
                            </span>

                            <strong>
                                <?= e($order["payment_method"]) ?>
                            </strong>

                        </div>


                        <div class="order-total">

                            <span>
                                Total
                            </span>

                            <strong>
                                ৳<?= number_format((float)$order["total_amount"], 2) ?>
                            </strong>

                        </div>

                    </div>


                </div>

            <?php endforeach; ?>


        <?php endif; ?>


        <!-- =================================================
             CONTINUE SHOPPING
             ================================================= -->

        <div class="orders-bottom">

            <a href="marketplace.php">
                ← Continue Shopping
            </a>

        </div>


    </div>

</main>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<footer class="footer">

    <div class="container footer-grid">


        <div class="footer-about">


            <a
                href="index.php"
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


            <div class="social-links">

                <a href="#">
                    f
                </a>

                <a href="#">
                    in
                </a>

                <a href="#">
                    𝕏
                </a>

            </div>


        </div>


        <div class="footer-column">

            <h3>
                Marketplace
            </h3>

            <a href="marketplace.php">
                All Products
            </a>

            <a href="marketplace.php?category=Vegetables">
                Vegetables
            </a>

            <a href="marketplace.php?category=Fruits">
                Fruits
            </a>

            <a href="marketplace.php?category=Grains">
                Grains
            </a>

            <a href="marketplace.php?category=Dairy">
                Dairy
            </a>

        </div>


        <div class="footer-column">

            <h3>
                For Farmers
            </h3>

            <a href="farmer.php">
                Farmer Dashboard
            </a>

            <a href="farmer-products.php">
                Manage Products
            </a>

            <a href="farmer-orders.php">
                Manage Orders
            </a>

        </div>


        <div class="footer-column">

            <h3>
                Support
            </h3>

            <a href="#">
                About Us
            </a>

            <a href="#">
                Contact Us
            </a>

            <a href="#">
                FAQ
            </a>

            <a href="#">
                Privacy Policy
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