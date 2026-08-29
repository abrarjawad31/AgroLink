<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Demand Broadcasts & Offers | AgroLink</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/consumer-demands.css">

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

            <a href="consumer-dashboard.php" class="logo">

                <span class="logo-icon">
                    🌱
                </span>

                <span>
                    Agro<span>Link</span>
                </span>

            </a>


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

                <a href="consumer-demands.php" class="active-nav">
                    My Demands
                </a>

            </nav>


            <div class="consumer-actions">

                <a href="cart.php" class="cart-link">

                    <span>
                        🛒
                    </span>

                    Cart

                    <span class="cart-count">
                        2
                    </span>

                </a>

                <a href="consumer-profile.php" class="profile-link active-profile">

                    <span class="profile-avatar">
                        A
                    </span>

                    <span class="profile-name">
                        Abrar
                    </span>

                </a>

                <a href="index.php" class="logout-btn">
                    Logout
                </a>

            </div>

        </div>

    </header>



    <!-- ================= MAIN ================= -->

    <main class="demands-page">

        <div class="container">

            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 28px;">

                <div>

                    <span style="color: var(--primary); font-size: 11px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">
                        SRS MODULE 3.5 & 3.6
                    </span>

                    <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--dark); margin-bottom: 4px;">
                        My Demand Broadcasts & Farmer Bids
                    </h1>

                    <p style="color: var(--light-text); font-size: 14px;">
                        Review incoming offers from local farmers, negotiate pricing, and accept terms.
                    </p>

                </div>


                <a href="post-demand.php" style="padding: 10px 18px; background: var(--primary); color: white; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none;">
                    + Broadcast New Demand Request
                </a>

            </div>



            <!-- DEMAND BROADCAST 1 -->

            <div class="demand-card">

                <div class="demand-top">

                    <div>

                        <h3>Bulk Organic Red Tomatoes (500 kg) — Request #DR-408</h3>

                        <span style="font-size: 13px; color: var(--light-text);">
                            Broadcasted: 24 Aug 2026 • Target Price: <strong>৳70/kg</strong> • Delivery By: <strong>15 Sep 2026</strong>
                        </span>

                    </div>

                    <span style="background: #e8f5e9; color: #2e7d32; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700;">
                        3 Farmer Offers Received 🔥
                    </span>

                </div>


                <!-- List of Farmer Responses -->

                <div class="offers-container">

                    <h4 style="font-size: 14px; color: var(--dark); margin-bottom: 10px;">
                        Compare Farmer Fulfillment Offers (FR-23):
                    </h4>

                    <div class="offers-list">


                        <!-- OFFER A -->

                        <div class="offer-item">

                            <div class="farmer-badge">

                                <div class="farmer-avatar">👨‍🌾</div>

                                <div>

                                    <strong style="font-size: 14px; color: var(--dark);">Rahim Agro Farm</strong>

                                    <span style="display: block; font-size: 12px; color: var(--light-text);">
                                        Gazipur • Farmer Trust Score: ⭐ 4.8 (98% On-time)
                                    </span>

                                </div>

                            </div>

                            <div>

                                <span style="font-size: 12px; color: var(--light-text); display: block;">Offer Price:</span>

                                <strong style="font-size: 16px; color: var(--primary);">৳68 / kg</strong>

                                <span style="font-size: 11px; color: #2e7d32;">(৳2 below target!)</span>

                            </div>

                            <div>

                                <span style="font-size: 12px; color: var(--light-text); display: block;">Available Quantity:</span>

                                <strong style="font-size: 14px; color: var(--dark);">500 kg (Full Batch)</strong>

                            </div>

                            <div style="display: flex; gap: 8px;">

                                <a href="negotiation.php?offer=1" style="padding: 8px 14px; background: white; border: 1px solid var(--border); color: var(--dark); border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none;">
                                    Negotiate / Counter 💬
                                </a>

                                <a href="negotiation.php?offer=1&action=accept" style="padding: 8px 14px; background: var(--primary); color: white; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none;">
                                    Accept Offer ✓
                                </a>

                            </div>

                        </div>



                        <!-- OFFER B -->

                        <div class="offer-item">

                            <div class="farmer-badge">

                                <div class="farmer-avatar">🌾</div>

                                <div>

                                    <strong style="font-size: 14px; color: var(--dark);">Bogura Organic Growers</strong>

                                    <span style="display: block; font-size: 12px; color: var(--light-text);">
                                        Bogura • Farmer Trust Score: ⭐ 4.9 (100% On-time)
                                    </span>

                                </div>

                            </div>

                            <div>

                                <span style="font-size: 12px; color: var(--light-text); display: block;">Offer Price:</span>

                                <strong style="font-size: 16px; color: var(--dark);">৳72 / kg</strong>

                            </div>

                            <div>

                                <span style="font-size: 12px; color: var(--light-text); display: block;">Available Quantity:</span>

                                <strong style="font-size: 14px; color: var(--dark);">500 kg (Full Batch)</strong>

                            </div>

                            <div style="display: flex; gap: 8px;">

                                <a href="negotiation.php?offer=2" style="padding: 8px 14px; background: white; border: 1px solid var(--border); color: var(--dark); border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none;">
                                    Negotiate / Counter 💬
                                </a>

                                <a href="negotiation.php?offer=2&action=accept" style="padding: 8px 14px; background: var(--primary); color: white; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none;">
                                    Accept Offer ✓
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>



    <!-- ================= FOOTER ================= -->

    <footer class="footer">

        <div class="container footer-grid">

            <div class="footer-about">

                <a href="consumer.php" class="logo footer-logo">

                    <span class="logo-icon">🌱</span>

                    <span>Agro<span>Link</span></span>

                </a>

                <p>
                    Connecting farmers and consumers through a smarter agricultural marketplace.
                </p>

            </div>


            <div class="footer-column">

                <h3>Consumer</h3>

                <a href="consumer.php">Dashboard</a>

                <a href="marketplace.php">Marketplace</a>

                <a href="future-harvests.php">Future Harvests</a>

                <a href="consumer-demands.php">Demand Broadcasts</a>

            </div>


            <div class="footer-column">

                <h3>Account</h3>

                <a href="consumer-profile.php">My Profile</a>

                <a href="my-orders.php">My Orders</a>

                <a href="index.php">Logout</a>

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
