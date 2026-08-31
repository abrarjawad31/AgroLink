<?php

require_once "auth.php";

// Only logged-in Consumers can access this page
requireConsumer();

// Get logged-in user's name
$userName = $_SESSION["user_name"] ?? "Consumer";

// Get first letter for profile avatar
$avatarLetter = strtoupper(substr($userName, 0, 1));

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Consumer Dashboard | AgroLink</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/consumer.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet">

</head>


<body>


    <!-- ================= NAVBAR ================= -->

    <header class="header">

        <div class="container navbar">

            <!-- LOGO -->

            <a href="consumer-dashboard.php" class="logo">

                <span class="logo-icon">
                    🌱
                </span>

                <span>
                    Agro<span>Link</span>
                </span>

            </a>


            <!-- NAVIGATION -->

            <nav class="nav-menu">

                <a href="consumer-dashboard.php" class="active-nav">
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

                <a href="cart.php" class="cart-link">

                    <span>
                        🛒
                    </span>

                    Cart

                    <span class="cart-count">
                        0
                    </span>

                </a>


                <a href="consumer-profile.php" class="profile-link">

                    <span class="profile-avatar">
                        <?php echo htmlspecialchars($avatarLetter); ?>
                    </span>

                    <span class="profile-name">
                        <?php echo htmlspecialchars($userName); ?>
                    </span>

                </a>


                <a href="logout.php" class="logout-btn">
                    Logout
                </a>

            </div>

        </div>

    </header>


    <!-- ================= DASHBOARD ================= -->

    <main class="dashboard">

        <div class="container">


            <!-- ================= WELCOME ================= -->

            <section class="welcome-section">

                <div>

                    <span class="welcome-tag">
                        CONSUMER DASHBOARD
                    </span>

                    <h1>
                        Welcome,
                        <span>
                            <?php echo htmlspecialchars($userName); ?>!
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


            <!-- ================= STAT CARDS ================= -->

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
                            0
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
                            0
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
                            0
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
                            0
                        </h2>

                    </div>

                </div>


            </section>


            <!-- ================= MAIN CONTENT ================= -->

            <section class="dashboard-grid">


                <!-- ================= RECENT ORDERS ================= -->

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

                </div>


                <!-- ================= QUICK ACTIONS ================= -->

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


            <!-- ================= FEATURED PRODUCTS ================= -->

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


                    <!-- PRODUCT 1 -->

                    <div class="product-card">

                        <div class="product-image">
                            🥬
                        </div>

                        <div class="product-content">

                            <span class="product-category">
                                Vegetables
                            </span>

                            <h3>
                                Fresh Organic Vegetables
                            </h3>

                            <p class="farmer-name">
                                🌾 Green Valley Farm
                            </p>

                            <div class="product-bottom">

                                <strong>
                                    ৳180
                                    <small>/kg</small>
                                </strong>

                                <a href="marketplace.php">
                                    View
                                </a>

                            </div>

                        </div>

                    </div>


                    <!-- PRODUCT 2 -->

                    <div class="product-card">

                        <div class="product-image">
                            🥭
                        </div>

                        <div class="product-content">

                            <span class="product-category">
                                Fruits
                            </span>

                            <h3>
                                Fresh Mangoes
                            </h3>

                            <p class="farmer-name">
                                🌾 Rajshahi Fresh Farm
                            </p>

                            <div class="product-bottom">

                                <strong>
                                    ৳120
                                    <small>/kg</small>
                                </strong>

                                <a href="marketplace.php">
                                    View
                                </a>

                            </div>

                        </div>

                    </div>


                    <!-- PRODUCT 3 -->

                    <div class="product-card">

                        <div class="product-image">
                            🌾
                        </div>

                        <div class="product-content">

                            <span class="product-category">
                                Grains
                            </span>

                            <h3>
                                Premium Miniket Rice
                            </h3>

                            <p class="farmer-name">
                                🌾 Golden Harvest Farm
                            </p>

                            <div class="product-bottom">

                                <strong>
                                    ৳95
                                    <small>/kg</small>
                                </strong>

                                <a href="marketplace.php">
                                    View
                                </a>

                            </div>

                        </div>

                    </div>


                </div>

            </section>


        </div>

    </main>


    <!-- ================= FOOTER ================= -->

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