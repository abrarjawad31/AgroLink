```php
<?php

// ============================================================
// FARMER DASHBOARD
// ============================================================

// Authentication
require_once "auth.php";
requireFarmer();

// Database connection
require_once "config.php";


// ============================================================
// GET LOGGED-IN FARMER INFORMATION
// ============================================================

$farmerId = (int) $_SESSION["user_id"];
$farmerName = $_SESSION["user_name"] ?? "Farmer";


// ============================================================
// HELPER FUNCTION
// ============================================================

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}


// ============================================================
// 1. TOTAL PRODUCTS
// ============================================================

$totalProducts = 0;

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE farmer_id = ?"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $totalProducts = (int) $row["total"];
}

$stmt->close();


// ============================================================
// 2. ACTIVE PRODUCTS
// ============================================================

$activeProducts = 0;

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE farmer_id = ?
     AND status = 'available'"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $activeProducts = (int) $row["total"];
}

$stmt->close();


// ============================================================
// 3. PENDING ORDERS
// ============================================================

$pendingOrders = 0;

$stmt = $conn->prepare(
    "SELECT COUNT(DISTINCT o.id) AS total
     FROM orders o
     INNER JOIN order_items oi
        ON o.id = oi.order_id
     INNER JOIN products p
        ON oi.product_id = p.id
     WHERE p.farmer_id = ?
     AND o.status = 'pending'"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $pendingOrders = (int) $row["total"];
}

$stmt->close();


// ============================================================
// 4. TOTAL EARNINGS
// ============================================================
// We count delivered orders as actual earnings.
// Cancelled orders are not included.
//
// ============================================================

$totalEarnings = 0;

$stmt = $conn->prepare(
    "SELECT COALESCE(SUM(oi.subtotal), 0) AS earnings
     FROM order_items oi
     INNER JOIN products p
        ON oi.product_id = p.id
     INNER JOIN orders o
        ON oi.order_id = o.id
     WHERE p.farmer_id = ?
     AND o.status = 'delivered'"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $totalEarnings = (float) $row["earnings"];
}

$stmt->close();


// ============================================================
// FORMAT EARNINGS
// ============================================================

if ($totalEarnings >= 1000000) {

    $formattedEarnings = "৳" . number_format(
        $totalEarnings / 1000000,
        1
    ) . "M";

} elseif ($totalEarnings >= 1000) {

    $formattedEarnings = "৳" . number_format(
        $totalEarnings / 1000,
        1
    ) . "K";

} else {

    $formattedEarnings = "৳" . number_format(
        $totalEarnings,
        0
    );
}


// ============================================================
// 5. RECENT ORDERS
// ============================================================
//
// Important:
// orders table does NOT contain farmer_id.
//
// Therefore we connect:
//
// orders
//    ↓
// order_items
//    ↓
// products
//    ↓
// products.farmer_id
//
// This makes sure we only show orders containing this farmer's
// products.
//
// ============================================================

$recentOrders = [];

$stmt = $conn->prepare(
    "SELECT
        o.id AS order_id,
        o.status,
        o.created_at,
        u.name AS consumer_name,
        SUM(oi.subtotal) AS farmer_total,
        GROUP_CONCAT(
            DISTINCT oi.product_name
            ORDER BY oi.product_name
            SEPARATOR ', '
        ) AS product_names

     FROM orders o

     INNER JOIN order_items oi
        ON o.id = oi.order_id

     INNER JOIN products p
        ON oi.product_id = p.id

     INNER JOIN users u
        ON o.consumer_id = u.id

     WHERE p.farmer_id = ?

     GROUP BY
        o.id,
        o.status,
        o.created_at,
        u.name

     ORDER BY o.created_at DESC

     LIMIT 5"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recentOrders[] = $row;
}

$stmt->close();


// ============================================================
// 6. PRODUCT PERFORMANCE
// ============================================================
//
// Shows the farmer's top products based on total sales.
//
// ============================================================

$performanceProducts = [];

$stmt = $conn->prepare(
    "SELECT
        p.id,
        p.name,
        p.category,
        p.image,

        COUNT(DISTINCT oi.order_id) AS order_count,

        COALESCE(SUM(oi.quantity), 0) AS total_quantity,

        COALESCE(SUM(oi.subtotal), 0) AS total_sales

     FROM products p

     LEFT JOIN order_items oi
        ON p.id = oi.product_id

     LEFT JOIN orders o
        ON oi.order_id = o.id
        AND o.status != 'cancelled'

     WHERE p.farmer_id = ?

     GROUP BY
        p.id,
        p.name,
        p.category,
        p.image

     ORDER BY total_sales DESC

     LIMIT 3"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $performanceProducts[] = $row;
}

$stmt->close();


// ============================================================
// FIND HIGHEST SALES FOR PROGRESS BAR
// ============================================================

$maxSales = 0;

foreach ($performanceProducts as $product) {

    $sales = (float) $product["total_sales"];

    if ($sales > $maxSales) {
        $maxSales = $sales;
    }
}


// ============================================================
// ORDER STATUS CLASS
// ============================================================

function getStatusClass($status)
{
    switch ($status) {

        case "pending":
            return "pending";

        case "confirmed":
            return "confirmed";

        case "processing":
            return "processing";

        case "shipped":
            return "shipped";

        case "delivered":
            return "completed";

        case "cancelled":
            return "cancelled";

        default:
            return "pending";
    }
}


// ============================================================
// ORDER STATUS LABEL
// ============================================================

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


// ============================================================
// FARMER INITIAL
// ============================================================

$farmerInitial = strtoupper(
    substr(trim($farmerName), 0, 1)
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Farmer Dashboard | AgroLink</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/farmer.css">

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

        <a href="farmer.php" class="logo">

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
                href="farmer.php"
                class="active-nav"
            >
                Dashboard
            </a>

            <a href="farmer-products.php">
                My Products
            </a>

            <a href="farmer-bookings.php">
                Harvest Bookings
            </a>

            <a href="farmer-demands.php">
                Demand Broadcasts
            </a>

            <a href="farmer-orders.php">
                Orders
            </a>

            <a href="farmer-dss.php">
                DSS
            </a>

        </nav>


        <!-- FARMER AREA -->

        <div class="farmer-actions">


            <!-- NOTIFICATION -->

            <a
                href="#"
                class="notification"
            >

                🔔

                <span class="notification-count">
                    3
                </span>

            </a>


            <!-- PROFILE -->

            <a
                href="farmer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">
                    <?php echo e($farmerInitial); ?>
                </span>

                <span class="profile-name">
                    <?php echo e($farmerName); ?>
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



<!-- =========================================================
     DASHBOARD
========================================================= -->

<main class="dashboard">

    <div class="container">


        <!-- =================================================
             WELCOME
        ================================================= -->

        <section class="welcome-section">

            <div>

                <span class="welcome-tag">
                    FARMER DASHBOARD
                </span>

                <h1>
                    Welcome,
                    <span>
                        <?php echo e($farmerName); ?>!
                    </span>
                    👋
                </h1>

                <p>
                    Manage your products, orders and farm
                    activities from one place.
                </p>

            </div>


            <a
                href="add-product.php"
                class="add-product-button"
            >
                + Add New Product
            </a>

        </section>



        <!-- =================================================
             STAT CARDS
        ================================================= -->

        <section class="stats-grid">


            <!-- TOTAL PRODUCTS -->

            <div class="stat-card">

                <div class="stat-icon">
                    🌾
                </div>

                <div>

                    <span class="stat-label">
                        Total Products
                    </span>

                    <h2>
                        <?php echo $totalProducts; ?>
                    </h2>

                </div>

            </div>


            <!-- ACTIVE PRODUCTS -->

            <div class="stat-card">

                <div class="stat-icon">
                    🟢
                </div>

                <div>

                    <span class="stat-label">
                        Active Products
                    </span>

                    <h2>
                        <?php echo $activeProducts; ?>
                    </h2>

                </div>

            </div>


            <!-- PENDING ORDERS -->

            <div class="stat-card">

                <div class="stat-icon">
                    📦
                </div>

                <div>

                    <span class="stat-label">
                        Pending Orders
                    </span>

                    <h2>
                        <?php echo $pendingOrders; ?>
                    </h2>

                </div>

            </div>


            <!-- TOTAL EARNINGS -->

            <div class="stat-card">

                <div class="stat-icon">
                    💰
                </div>

                <div>

                    <span class="stat-label">
                        Total Earnings
                    </span>

                    <h2>
                        <?php echo e($formattedEarnings); ?>
                    </h2>

                </div>

            </div>


        </section>



        <!-- =================================================
             MAIN GRID
        ================================================= -->

        <section class="dashboard-grid">


            <!-- =================================================
                 RECENT ORDERS
            ================================================= -->

            <div class="dashboard-card">


                <div class="card-header">

                    <div>

                        <h2>
                            Recent Orders
                        </h2>

                        <p>
                            Latest orders containing your products
                        </p>

                    </div>

                    <a href="farmer-orders.php">
                        View All
                    </a>

                </div>


                <?php if (count($recentOrders) > 0): ?>


                    <?php foreach ($recentOrders as $order): ?>

                        <?php

                        $status = $order["status"];

                        $statusClass = getStatusClass($status);

                        $statusLabel = getStatusLabel($status);

                        $productNames = $order["product_names"];

                        ?>


                        <div class="order-item">


                            <div class="order-product">


                                <div class="product-icon">
                                    📦
                                </div>


                                <div>

                                    <strong>
                                        <?php echo e($productNames); ?>
                                    </strong>

                                    <span>
                                        #AG<?php echo str_pad(
                                            $order["order_id"],
                                            4,
                                            "0",
                                            STR_PAD_LEFT
                                        ); ?>

                                        • Consumer:
                                        <?php echo e(
                                            $order["consumer_name"]
                                        ); ?>
                                    </span>

                                </div>


                            </div>


                            <div class="order-info">

                                <strong>
                                    ৳<?php echo number_format(
                                        (float) $order["farmer_total"],
                                        2
                                    ); ?>
                                </strong>

                                <span
                                    class="status <?php echo e($statusClass); ?>"
                                >
                                    <?php echo e($statusLabel); ?>
                                </span>

                            </div>


                        </div>


                    <?php endforeach; ?>


                <?php else: ?>


                    <div
                        style="
                            padding: 30px 10px;
                            text-align: center;
                            color: var(--light-text);
                            font-size: 10px;
                        "
                    >

                        No orders yet.

                    </div>


                <?php endif; ?>


            </div>



            <!-- =================================================
                 QUICK ACTIONS
            ================================================= -->

            <div class="dashboard-card">


                <div class="card-header">

                    <div>

                        <h2>
                            Quick Actions
                        </h2>

                        <p>
                            Manage your farm
                        </p>

                    </div>

                </div>


                <div class="quick-actions">


                    <!-- ADD PRODUCT -->

                    <a
                        href="add-product.php"
                        class="quick-action"
                    >

                        <span>
                            ➕
                        </span>

                        <div>

                            <strong>
                                Add Product
                            </strong>

                            <small>
                                List a new product
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </a>


                    <!-- MY PRODUCTS -->

                    <a
                        href="farmer-products.php"
                        class="quick-action"
                    >

                        <span>
                            🌾
                        </span>

                        <div>

                            <strong>
                                My Products
                            </strong>

                            <small>
                                Manage your listings
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </a>


                    <!-- ORDERS -->

                    <a
                        href="farmer-orders.php"
                        class="quick-action"
                    >

                        <span>
                            📦
                        </span>

                        <div>

                            <strong>
                                Manage Orders
                            </strong>

                            <small>
                                View customer orders
                            </small>

                        </div>

                        <b>
                            →
                        </b>

                    </a>


                    <!-- DSS -->

                    <a
                        href="farmer-dss.php"
                        class="quick-action dss-action"
                    >

                        <span>
                            📊
                        </span>

                        <div>

                            <strong>
                                Decision Support
                            </strong>

                            <small>
                                Get farming insights
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
             PRODUCT PERFORMANCE
        ================================================= -->

        <section class="performance-section">


            <div class="section-heading">

                <div>

                    <span>
                        PRODUCT PERFORMANCE
                    </span>

                    <h2>
                        Your Best Performing Products
                    </h2>

                </div>

                <a href="farmer-products.php">
                    Manage Products →
                </a>

            </div>



            <div class="performance-grid">


                <?php if (count($performanceProducts) > 0): ?>


                    <?php foreach ($performanceProducts as $product): ?>


                        <?php

                        $sales = (float) $product["total_sales"];

                        if ($maxSales > 0) {

                            $progress = ($sales / $maxSales) * 100;

                        } else {

                            $progress = 0;

                        }

                        ?>


                        <div class="performance-card">


                            <div class="performance-icon">

                                <?php

                                $category = strtolower(
                                    $product["category"]
                                );

                                if (
                                    strpos($category, "vegetable") !== false
                                ) {

                                    echo "🥬";

                                } elseif (
                                    strpos($category, "fruit") !== false
                                ) {

                                    echo "🥭";

                                } elseif (
                                    strpos($category, "grain") !== false ||
                                    strpos($category, "rice") !== false
                                ) {

                                    echo "🌾";

                                } elseif (
                                    strpos($category, "dairy") !== false
                                ) {

                                    echo "🥛";

                                } else {

                                    echo "🌱";

                                }

                                ?>

                            </div>


                            <div class="performance-content">


                                <h3>
                                    <?php echo e($product["name"]); ?>
                                </h3>


                                <span>

                                    <?php echo (int) $product["order_count"]; ?>

                                    orders

                                </span>


                                <div class="progress-bar">

                                    <div
                                        class="progress"
                                        style="width: <?php echo round($progress); ?>%;"
                                    ></div>

                                </div>


                            </div>


                            <strong>

                                ৳<?php echo number_format(
                                    $sales,
                                    1
                                ); ?>

                            </strong>


                        </div>


                    <?php endforeach; ?>


                <?php else: ?>


                    <div
                        class="dashboard-card"
                        style="
                            grid-column: 1 / -1;
                            text-align: center;
                            color: var(--light-text);
                            font-size: 10px;
                        "
                    >

                        You haven't added any products yet.

                        <br><br>

                        <a
                            href="add-product.php"
                            style="
                                color: var(--primary);
                                font-weight: 700;
                                text-decoration: none;
                            "
                        >
                            + Add Your First Product
                        </a>

                    </div>


                <?php endif; ?>


            </div>

        </section>



        <!-- =================================================
             DSS BANNER
        ================================================= -->

        <section class="dss-banner">


            <div class="dss-content">


                <span class="dss-icon">
                    📊
                </span>


                <div>

                    <span class="dss-label">
                        DECISION SUPPORT SYSTEM
                    </span>

                    <h2>
                        Make Smarter Farming Decisions
                    </h2>

                    <p>
                        Get recommendations based on crop,
                        soil, weather and market conditions.
                    </p>

                </div>


            </div>


            <a
                href="farmer-dss.php"
                class="dss-button"
            >
                Open DSS →
            </a>


        </section>


    </div>

</main>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">


    <div class="container footer-grid">


        <!-- ABOUT -->

        <div class="footer-about">


            <a
                href="farmer.php"
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



        <!-- FARMER -->

        <div class="footer-column">

            <h3>
                Farmer
            </h3>

            <a href="farmer-products.php">
                My Products
            </a>

            <a href="add-product.php">
                Add Product
            </a>

            <a href="farmer-orders.php">
                Orders
            </a>

            <a href="farmer-dss.php">
                Decision Support
            </a>

        </div>



        <!-- ACCOUNT -->

        <div class="footer-column">

            <h3>
                Account
            </h3>

            <a href="farmer-profile.php">
                My Profile
            </a>

            <a href="#">
                Settings
            </a>

            <a href="logout.php">
                Logout
            </a>

        </div>



        <!-- SUPPORT -->

        <div class="footer-column">

            <h3>
                Support
            </h3>

            <a href="#">
                Help Center
            </a>

            <a href="contact.php">
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
```
