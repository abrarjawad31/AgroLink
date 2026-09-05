<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "auth.php";
requireConsumer();

require_once "config.php";

/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatBDT($amount)
{
    return '৳' . number_format((float)$amount, 0);
}

function orderStatusLabel($status)
{
    $labels = [
        'pending'    => 'Pending',
        'confirmed'  => 'Confirmed',
        'processing' => 'Processing',
        'shipped'    => 'Shipped',
        'delivered'  => 'Delivered',
        'cancelled'  => 'Cancelled'
    ];

    return $labels[$status] ?? ucfirst($status);
}

function orderStatusClass($status)
{
    $classes = [
        'pending'    => 'pending',
        'confirmed'  => 'processing',
        'processing' => 'processing',
        'shipped'    => 'shipped',
        'delivered'  => 'delivered',
        'cancelled'  => 'cancelled'
    ];

    return $classes[$status] ?? 'pending';
}

/* =========================================================
   CURRENT CONSUMER
========================================================= */

$consumerId = (int)($_SESSION['user_id'] ?? 0);

if ($consumerId <= 0) {
    header("Location: login.php");
    exit;
}

/* Get fresh user information from database */

$user = null;

$stmt = $conn->prepare("
    SELECT 
        id,
        name,
        email,
        phone,
        address,
        role,
        status,
        created_at
    FROM users
    WHERE id = ?
      AND role = 'consumer'
    LIMIT 1
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();

$stmt->close();

if (!$user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

$userName = $user['name'] ?: 'Consumer';
$avatarLetter = strtoupper(substr(trim($userName), 0, 1));

/* =========================================================
   CART COUNT
========================================================= */

$cartCount = 0;

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(ci.quantity), 0) AS cart_count
    FROM cart c
    LEFT JOIN cart_items ci 
        ON ci.cart_id = c.id
    WHERE c.consumer_id = ?
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $cartCount = (int)$row['cart_count'];
}

$stmt->close();

/* =========================================================
   BASIC ORDER STATISTICS
========================================================= */

/* Total orders */

$totalOrders = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_orders
    FROM orders
    WHERE consumer_id = ?
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $totalOrders = (int)$row['total_orders'];
}

$stmt->close();

/* Delivered orders */

$completedOrders = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS completed_orders
    FROM orders
    WHERE consumer_id = ?
      AND status = 'delivered'
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $completedOrders = (int)$row['completed_orders'];
}

$stmt->close();

/* Active orders */

$activeOrders = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS active_orders
    FROM orders
    WHERE consumer_id = ?
      AND status IN ('pending', 'confirmed', 'processing', 'shipped')
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $activeOrders = (int)$row['active_orders'];
}

$stmt->close();

/* =========================================================
   TOTAL SPENT
   Delivered orders only
========================================================= */

$totalSpent = 0;

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(total_amount), 0) AS total_spent
    FROM orders
    WHERE consumer_id = ?
      AND status = 'delivered'
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $totalSpent = (float)$row['total_spent'];
}

$stmt->close();

/* =========================================================
   FARMERS SUPPORTED
========================================================= */

$farmersSupported = 0;

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT p.farmer_id) AS farmers_supported
    FROM orders o
    INNER JOIN order_items oi
        ON oi.order_id = o.id
    INNER JOIN products p
        ON p.id = oi.product_id
    WHERE o.consumer_id = ?
      AND o.status = 'delivered'
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $farmersSupported = (int)$row['farmers_supported'];
}

$stmt->close();

/* =========================================================
   MOST USED PAYMENT METHOD
========================================================= */

$preferredPayment = 'Not set';

$stmt = $conn->prepare("
    SELECT 
        payment_method,
        COUNT(*) AS payment_count
    FROM orders
    WHERE consumer_id = ?
      AND payment_method IS NOT NULL
      AND payment_method <> ''
    GROUP BY payment_method
    ORDER BY payment_count DESC
    LIMIT 1
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $preferredPayment = $row['payment_method'];
}

$stmt->close();

/* =========================================================
   MOST PURCHASED CATEGORY
========================================================= */

$topCategory = 'Not available';

$stmt = $conn->prepare("
    SELECT
        p.category,
        SUM(oi.quantity) AS total_quantity
    FROM orders o
    INNER JOIN order_items oi
        ON oi.order_id = o.id
    INNER JOIN products p
        ON p.id = oi.product_id
    WHERE o.consumer_id = ?
      AND o.status <> 'cancelled'
    GROUP BY p.category
    ORDER BY total_quantity DESC
    LIMIT 1
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $topCategory = $row['category'];
}

$stmt->close();

/* =========================================================
   TOP FARMER
========================================================= */

$topFarmer = 'Not available';

$stmt = $conn->prepare("
    SELECT
        u.name AS farmer_name,
        SUM(oi.quantity) AS purchased_quantity
    FROM orders o
    INNER JOIN order_items oi
        ON oi.order_id = o.id
    INNER JOIN products p
        ON p.id = oi.product_id
    INNER JOIN users u
        ON u.id = p.farmer_id
    WHERE o.consumer_id = ?
      AND o.status <> 'cancelled'
      AND u.role = 'farmer'
    GROUP BY p.farmer_id, u.name
    ORDER BY purchased_quantity DESC
    LIMIT 1
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $topFarmer = $row['farmer_name'];
}

$stmt->close();

/* =========================================================
   AVERAGE ORDER VALUE
========================================================= */

$averageOrderValue = 0;

$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(total_amount), 0) AS total_value,
        COUNT(*) AS order_count
    FROM orders
    WHERE consumer_id = ?
      AND status <> 'cancelled'
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    $orderValue = (float)$row['total_value'];
    $orderCount = (int)$row['order_count'];

    if ($orderCount > 0) {
        $averageOrderValue = $orderValue / $orderCount;
    }
}

$stmt->close();

/* =========================================================
   THIS MONTH'S ACTIVITY
========================================================= */

$currentMonthName = date('F Y');

$monthlySpent = 0;
$monthlyOrders = 0;

$monthStart = date('Y-m-01 00:00:00');
$nextMonthStart = date('Y-m-01 00:00:00', strtotime('+1 month'));

$stmt = $conn->prepare("
    SELECT
        COALESCE(SUM(total_amount), 0) AS monthly_spent,
        COUNT(*) AS monthly_orders
    FROM orders
    WHERE consumer_id = ?
      AND status <> 'cancelled'
      AND created_at >= ?
      AND created_at < ?
");

$stmt->bind_param(
    "iss",
    $consumerId,
    $monthStart,
    $nextMonthStart
);

$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    $monthlySpent = (float)$row['monthly_spent'];
    $monthlyOrders = (int)$row['monthly_orders'];
}

$stmt->close();

/* =========================================================
   DELIVERY COMPLETION
========================================================= */

$nonCancelledOrders = 0;
$deliveryCompletionRate = 0;

$stmt = $conn->prepare("
    SELECT COUNT(*) AS order_count
    FROM orders
    WHERE consumer_id = ?
      AND status <> 'cancelled'
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $nonCancelledOrders = (int)$row['order_count'];
}

$stmt->close();

if ($nonCancelledOrders > 0) {
    $deliveryCompletionRate = round(
        ($completedOrders / $nonCancelledOrders) * 100
    );
}

/* =========================================================
   CATEGORY BREAKDOWN
========================================================= */

$categories = [];

$stmt = $conn->prepare("
    SELECT
        p.category,
        COUNT(DISTINCT o.id) AS order_count,
        COALESCE(SUM(oi.subtotal), 0) AS category_amount
    FROM orders o
    INNER JOIN order_items oi
        ON oi.order_id = o.id
    INNER JOIN products p
        ON p.id = oi.product_id
    WHERE o.consumer_id = ?
      AND o.status <> 'cancelled'
    GROUP BY p.category
    ORDER BY category_amount DESC
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $categoryAmount = (float)$row['category_amount'];

    $percentage = 0;

    if ($totalSpent > 0) {
        $percentage = ($categoryAmount / $totalSpent) * 100;
    }

    $row['percentage'] = round($percentage, 1);

    $categories[] = $row;
}

$stmt->close();

/* =========================================================
   RECENT ORDERS
========================================================= */

$recentOrders = [];

$stmt = $conn->prepare("
    SELECT
        o.id,
        o.total_amount,
        o.status,
        o.payment_method,
        o.created_at,

        COUNT(oi.id) AS item_count,

        GROUP_CONCAT(
            DISTINCT u.name
            ORDER BY u.name
            SEPARATOR ', '
        ) AS farmer_names

    FROM orders o

    LEFT JOIN order_items oi
        ON oi.order_id = o.id

    LEFT JOIN products p
        ON p.id = oi.product_id

    LEFT JOIN users u
        ON u.id = p.farmer_id

    WHERE o.consumer_id = ?

    GROUP BY
        o.id,
        o.total_amount,
        o.status,
        o.payment_method,
        o.created_at

    ORDER BY o.created_at DESC

    LIMIT 5
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recentOrders[] = $row;
}

$stmt->close();

/* =========================================================
   MEMBER SINCE
========================================================= */

$memberSince = 'N/A';

if (!empty($user['created_at'])) {
    $memberSince = date('F Y', strtotime($user['created_at']));
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Profile | AgroLink</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/consumer-profile.css">

</head>

<body>

<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar">

    <div class="nav-container">

        <a href="consumer-dashboard.php" class="logo">
            <span class="logo-icon">🌱</span>
            <span>AgroLink</span>
        </a>

        <div class="nav-links">

            <a href="consumer-dashboard.php">
                Home
            </a>

            <a href="marketplace.php">
                Marketplace
            </a>

            <a href="my-orders.php">
                My Orders
            </a>

            <a href="my-demands.php">
                My Demands
            </a>

        </div>

        <div class="nav-actions">

            <a href="cart.php" class="cart-link">

                🛒

                <span class="cart-count">
                    <?= $cartCount ?>
                </span>

            </a>

            <div class="profile-dropdown">

                <div class="profile-mini">

                    <span class="profile-avatar">
                        <?= e($avatarLetter) ?>
                    </span>

                    <span class="profile-name">
                        <?= e($userName) ?>
                    </span>

                </div>

                <div class="dropdown-menu">

                    <a href="consumer-profile.php">
                        👤 My Profile
                    </a>

                    <a href="edit-profile.php">
                        ✏️ Edit Profile
                    </a>

                    <a href="logout.php">
                        🚪 Logout
                    </a>

                </div>

            </div>

        </div>

    </div>

</nav>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="profile-page">

    <!-- =================================================
         PROFILE HEADER
    ================================================== -->

    <section class="profile-header">

        <div class="profile-header-left">

            <div class="large-profile-avatar">
                <?= e($avatarLetter) ?>
            </div>

            <div class="profile-heading">

                <span class="verified-badge">
                    ✓ VERIFIED CONSUMER
                </span>

                <h1>
                    <?= e($userName) ?>
                </h1>

                <p class="profile-location">
                    📍
                    <?= !empty($user['address']) ? e($user['address']) : 'Delivery address not provided' ?>
                </p>

                <p class="member-since">
                    Member since <?= e($memberSince) ?>
                </p>

            </div>

        </div>

        <div class="profile-header-actions">

            <a href="edit-profile.php" class="edit-profile-btn">
                ✏️ Edit Profile
            </a>

        </div>

    </section>


    <!-- =================================================
         PROFILE STATS
    ================================================== -->

    <section class="profile-stats">

        <div class="stat-card">

            <div class="stat-icon">
                📦
            </div>

            <div class="stat-content">

                <span class="stat-value">
                    <?= $totalOrders ?>
                </span>

                <span class="stat-label">
                    Total Orders
                </span>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                💰
            </div>

            <div class="stat-content">

                <span class="stat-value">
                    <?= formatBDT($totalSpent) ?>
                </span>

                <span class="stat-label">
                    Total Spent
                </span>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                🚜
            </div>

            <div class="stat-content">

                <span class="stat-value">
                    <?= $farmersSupported ?>
                </span>

                <span class="stat-label">
                    Farmers Supported
                </span>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                ⭐
            </div>

            <div class="stat-content">

                <span class="stat-value">
                    N/A
                </span>

                <span class="stat-label">
                    Buyer Rating
                </span>

            </div>

        </div>

    </section>


    <!-- =================================================
         PROFILE INFORMATION
    ================================================== -->

    <section class="profile-grid">

        <!-- PERSONAL INFORMATION -->

        <div class="profile-card">

            <div class="card-header">

                <div>

                    <h2>
                        Personal Information
                    </h2>

                    <p>
                        Your account and delivery information
                    </p>

                </div>

            </div>


            <div class="info-list">

                <div class="info-row">

                    <span class="info-label">
                        Full Name
                    </span>

                    <span class="info-value">
                        <?= e($user['name']) ?>
                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Email
                    </span>

                    <span class="info-value">
                        <?= e($user['email']) ?>
                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Phone
                    </span>

                    <span class="info-value">

                        <?= !empty($user['phone'])
                            ? e($user['phone'])
                            : 'Not provided'
                        ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Account Type
                    </span>

                    <span class="info-value">
                        Direct Consumer
                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Delivery Address
                    </span>

                    <span class="info-value">

                        <?= !empty($user['address'])
                            ? e($user['address'])
                            : 'Not provided'
                        ?>

                    </span>

                </div>


                <div class="info-row">

                    <span class="info-label">
                        Preferred Payment
                    </span>

                    <span class="info-value">
                        <?= e($preferredPayment) ?>
                    </span>

                </div>

            </div>

        </div>


        <!-- SHOPPING OVERVIEW -->

        <div class="profile-card">

            <div class="card-header">

                <div>

                    <h2>
                        Shopping Overview
                    </h2>

                    <p>
                        Information calculated from your purchases
                    </p>

                </div>

            </div>


            <div class="preferences-list">

                <div class="preference-item">

                    <div class="preference-icon">
                        🥬
                    </div>

                    <div class="preference-content">

                        <span class="preference-label">
                            Most Purchased Category
                        </span>

                        <strong>
                            <?= e($topCategory) ?>
                        </strong>

                    </div>

                </div>


                <div class="preference-item">

                    <div class="preference-icon">
                        🚜
                    </div>

                    <div class="preference-content">

                        <span class="preference-label">
                            Most Purchased From
                        </span>

                        <strong>
                            <?= e($topFarmer) ?>
                        </strong>

                    </div>

                </div>


                <div class="preference-item">

                    <div class="preference-icon">
                        💳
                    </div>

                    <div class="preference-content">

                        <span class="preference-label">
                            Most Used Payment
                        </span>

                        <strong>
                            <?= e($preferredPayment) ?>
                        </strong>

                    </div>

                </div>


                <div class="preference-item">

                    <div class="preference-icon">
                        🚚
                    </div>

                    <div class="preference-content">

                        <span class="preference-label">
                            Delivery Time
                        </span>

                        <strong>
                            Not set
                        </strong>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- =================================================
         ACTIVITY
    ================================================== -->

    <section class="activity-section">

        <div class="section-title">

            <div>

                <h2>
                    Purchase Activity
                </h2>

                <p>
                    Your AgroLink purchasing history
                </p>

            </div>

        </div>


        <!-- ACTIVITY METRICS -->

        <div class="activity-metrics">

            <div class="activity-metric">

                <span class="metric-label">
                    COMPLETED ORDERS
                </span>

                <strong class="metric-value">
                    <?= $completedOrders ?>
                </strong>

                <span class="metric-sub">
                    Delivered purchases
                </span>

            </div>


            <div class="activity-metric">

                <span class="metric-label">
                    THIS MONTH
                </span>

                <strong class="metric-value">
                    <?= formatBDT($monthlySpent) ?>
                </strong>

                <span class="metric-sub">
                    <?= $monthlyOrders ?> order<?= $monthlyOrders == 1 ? '' : 's' ?>
                    · <?= e($currentMonthName) ?>
                </span>

            </div>


            <div class="activity-metric">

                <span class="metric-label">
                    AVG. ORDER VALUE
                </span>

                <strong class="metric-value">
                    <?= formatBDT($averageOrderValue) ?>
                </strong>

                <span class="metric-sub">
                    Based on non-cancelled orders
                </span>

            </div>


            <div class="activity-metric">

                <span class="metric-label">
                    ACTIVE ORDERS
                </span>

                <strong class="metric-value">
                    <?= $activeOrders ?>
                </strong>

                <span class="metric-sub">
                    Currently in progress
                </span>

            </div>

        </div>


        <!-- DELIVERY STATUS -->

        <div class="delivery-performance">

            <div class="delivery-performance-header">

                <div>

                    <span class="metric-label">
                        DELIVERY COMPLETION
                    </span>

                    <strong>
                        <?= $deliveryCompletionRate ?>%
                    </strong>

                </div>

                <div class="delivery-description">

                    <?= $completedOrders ?> delivered /
                    <?= $activeOrders ?> active

                </div>

            </div>

            <div class="progress-bar">

                <div
                    class="progress-fill"
                    style="width: <?= min(100, $deliveryCompletionRate) ?>%;">
                </div>

            </div>

            <p class="data-note">
                Based on orders currently stored in the AgroLink system.
            </p>

        </div>


        <!-- CATEGORY BREAKDOWN -->

        <div class="category-breakdown">

            <div class="breakdown-header">

                <h3>
                    Spending by Category
                </h3>

                <span>
                    Non-cancelled orders
                </span>

            </div>


            <?php if (!empty($categories)): ?>

                <div class="category-list">

                    <?php foreach ($categories as $category): ?>

                        <div class="category-row">

                            <div class="category-info">

                                <span class="category-name">
                                    <?= e($category['category']) ?>
                                </span>

                                <span class="category-amount">
                                    <?= formatBDT($category['category_amount']) ?>
                                </span>

                            </div>


                            <div class="category-progress">

                                <div
                                    class="category-progress-fill"
                                    style="width: <?= min(100, $category['percentage']) ?>%;">
                                </div>

                            </div>


                            <div class="category-meta">

                                <span>
                                    <?= $category['order_count'] ?>
                                    order<?= $category['order_count'] == 1 ? '' : 's' ?>
                                </span>

                                <span>
                                    <?= $category['percentage'] ?>%
                                </span>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="empty-activity">

                    <span>
                        🛒
                    </span>

                    <p>
                        No purchase activity yet.
                    </p>

                    <a href="marketplace.php">
                        Explore Marketplace
                    </a>

                </div>

            <?php endif; ?>

        </div>


        <!-- RECENT ORDERS -->

        <div class="recent-activity">

            <div class="breakdown-header">

                <div>

                    <h3>
                        Recent Purchases
                    </h3>

                    <span>
                        Your latest AgroLink orders
                    </span>

                </div>

                <a href="my-orders.php" class="view-all-link">
                    View All Orders →
                </a>

            </div>


            <?php if (!empty($recentOrders)): ?>

                <div class="recent-table-wrapper">

                    <table class="recent-table">

                        <thead>

                            <tr>

                                <th>
                                    Order
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Farmer
                                </th>

                                <th>
                                    Items
                                </th>

                                <th>
                                    Amount
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($recentOrders as $order): ?>

                                <tr>

                                    <td>

                                        <a
                                            href="order-details.php?id=<?= (int)$order['id'] ?>"
                                            class="order-id">

                                            #AGL-<?= str_pad((int)$order['id'], 5, '0', STR_PAD_LEFT) ?>

                                        </a>

                                    </td>


                                    <td>

                                        <?= date(
                                            'd M Y',
                                            strtotime($order['created_at'])
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= !empty($order['farmer_names'])
                                            ? e($order['farmer_names'])
                                            : 'N/A'
                                        ?>

                                    </td>


                                    <td>

                                        <?= (int)$order['item_count'] ?>

                                    </td>


                                    <td>

                                        <strong>
                                            <?= formatBDT($order['total_amount']) ?>
                                        </strong>

                                    </td>


                                    <td>

                                        <span
                                            class="status <?= e(orderStatusClass($order['status'])) ?>">

                                            <?= e(orderStatusLabel($order['status'])) ?>

                                        </span>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-activity">

                    <span>
                        📦
                    </span>

                    <p>
                        You have not placed any orders yet.
                    </p>

                    <a href="marketplace.php">
                        Start Shopping
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </section>


    <!-- =================================================
         HIGHLIGHTS
    ================================================== -->

    <section class="highlights-section">

        <div class="section-title">

            <div>

                <h2>
                    Your AgroLink Highlights
                </h2>

                <p>
                    Your contribution to the AgroLink marketplace
                </p>

            </div>

        </div>


        <div class="highlights-grid">


            <div class="highlight-card">

                <div class="highlight-icon">
                    🚜
                </div>

                <div>

                    <strong>
                        <?= $farmersSupported ?>
                    </strong>

                    <span>
                        Farmers Supported
                    </span>

                </div>

            </div>


            <div class="highlight-card">

                <div class="highlight-icon">
                    📦
                </div>

                <div>

                    <strong>
                        <?= $completedOrders ?>
                    </strong>

                    <span>
                        Completed Orders
                    </span>

                </div>

            </div>


            <div class="highlight-card">

                <div class="highlight-icon">
                    🥬
                </div>

                <div>

                    <strong>
                        <?= e($topCategory) ?>
                    </strong>

                    <span>
                        Top Category
                    </span>

                </div>

            </div>


            <div class="highlight-card">

                <div class="highlight-icon">
                    💳
                </div>

                <div>

                    <strong>
                        <?= e($preferredPayment) ?>
                    </strong>

                    <span>
                        Preferred Payment
                    </span>

                </div>

            </div>

        </div>

    </section>

</main>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer class="footer">

    <div class="footer-container">

        <div class="footer-brand">

            <a href="consumer-dashboard.php" class="footer-logo">
                🌱 AgroLink
            </a>

            <p>
                Connecting consumers directly with farmers.
            </p>

        </div>


        <div class="footer-links">

            <a href="consumer-dashboard.php">
                Home
            </a>

            <a href="marketplace.php">
                Marketplace
            </a>

            <a href="my-orders.php">
                My Orders
            </a>

            <a href="my-demands.php">
                My Demands
            </a>

        </div>

    </div>

    <div class="footer-bottom">

        <p>
            © <?= date('Y') ?> AgroLink. All rights reserved.
        </p>

    </div>

</footer>


</body>
</html>