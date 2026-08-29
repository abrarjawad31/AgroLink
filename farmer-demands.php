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

                <a href="farmer-demands.php" class="active-nav">Demand Broadcasts</a>

                <a href="farmer-orders.php">Orders</a>

                <a href="farmer-dss.php">DSS</a>

            </nav>


            <div class="farmer-actions" style="display: flex; align-items: center; gap: 14px;">

                <a href="#" style="text-decoration: none; font-size: 16px;">🔔</a>

                <a href="farmer-profile.php" style="text-decoration: none; display: flex; align-items: center; gap: 8px; color: var(--dark); font-weight: 600; font-size: 14px;">
                    <span style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">A</span>
                    Abrar
                </a>

                <a href="index.php" style="text-decoration: none; padding: 8px 12px; border: 1px solid var(--border); border-radius: 5px; font-size: 13px; font-weight: 600; color: var(--dark);">Logout</a>

            </div>

        </div>

    </header>



    <!-- ================= MAIN ================= -->

    <main class="farmer-demands-page">

        <div class="container">

            <div>

                <span style="color: var(--primary); font-size: 11px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">
                    SRS MODULE 3.5: DEMAND-BASED TRADING (FR-21 & FR-22)
                </span>

                <h1 style="font-family: 'Playfair Display', serif; font-size: 28px; color: var(--dark); margin: 4px 0;">
                    Active Consumer Purchasing Demands
                </h1>

                <p style="color: var(--light-text); font-size: 14px;">
                    Consumers and bulk buyers in your region have posted these immediate crop purchase requests. Submit an offer to secure orders directly.
                </p>

            </div>



            <div class="demand-market-grid">


                <!-- DEMAND 1 -->

                <div class="demand-market-card">

                    <div>

                        <div class="demand-market-top">

                            <div>

                                <h3>Bulk Organic Red Tomatoes</h3>

                                <span style="font-size: 12px; color: var(--light-text);">
                                    Posted by: 👤 Abrar Jawad • Dhanmondi, Dhaka
                                </span>

                            </div>

                            <span class="demand-tag">Active Request</span>

                        </div>

                        <div class="demand-specs">

                            <div>
                                <span style="color: var(--light-text);">Required Qty:</span>
                                <strong>500 kg</strong>
                            </div>

                            <div>
                                <span style="color: var(--light-text);">Buyer's Budget:</span>
                                <strong style="color: #2e7d32;">৳70 / kg</strong>
                            </div>

                            <div>
                                <span style="color: var(--light-text);">Delivery Window:</span>
                                <strong>15 Sep 2026</strong>
                            </div>

                            <div>
                                <span style="color: var(--light-text);">Category:</span>
                                <strong>Vegetables</strong>
                            </div>

                        </div>

                        <p style="font-size: 13px; color: var(--text); line-height: 1.4; margin-bottom: 12px;">
                            <em>"Looking for 100% organic, pesticide-free fresh ripe tomatoes for our restaurant chain."</em>
                        </p>

                    </div>


                    <div class="submit-offer-box">

                        <form style="display: flex; gap: 8px;" onsubmit="event.preventDefault(); alert('Offer submitted to buyer! You will be notified when they counter or accept.');">

                            <input type="number" placeholder="Your Offer (৳/kg)" value="68" required style="width: 140px; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">

                            <button type="submit" style="flex: 1; padding: 8px 12px; background: var(--primary); color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                Submit Offer ➔
                            </button>

                        </form>

                    </div>

                </div>



                <!-- DEMAND 2 -->

                <div class="demand-market-card">

                    <div>

                        <div class="demand-market-top">

                            <div>

                                <h3>Diamond Potatoes (A-Grade)</h3>

                                <span style="font-size: 12px; color: var(--light-text);">
                                    Posted by: 🛒 City Fresh Mart • Uttara, Dhaka
                                </span>

                            </div>

                            <span class="demand-tag">Active Request</span>

                        </div>

                        <div class="demand-specs">

                            <div>
                                <span style="color: var(--light-text);">Required Qty:</span>
                                <strong>2,000 kg (2 Tons)</strong>
                            </div>

                            <div>
                                <span style="color: var(--light-text);">Buyer's Budget:</span>
                                <strong style="color: #2e7d32;">৳38 / kg</strong>
                            </div>

                            <div>
                                <span style="color: var(--light-text);">Delivery Window:</span>
                                <strong>01 Dec 2026</strong>
                            </div>

                            <div>
                                <span style="color: var(--light-text);">Category:</span>
                                <strong>Tubers</strong>
                            </div>

                        </div>

                        <p style="font-size: 13px; color: var(--text); line-height: 1.4; margin-bottom: 12px;">
                            <em>"Cleaned and sorted Diamond potatoes for wholesale retail. Northern growers preferred."</em>
                        </p>

                    </div>


                    <div class="submit-offer-box">

                        <form style="display: flex; gap: 8px;" onsubmit="event.preventDefault(); alert('Offer submitted to buyer! You will be notified when they counter or accept.');">

                            <input type="number" placeholder="Your Offer (৳/kg)" value="36" required style="width: 140px; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">

                            <button type="submit" style="flex: 1; padding: 8px 12px; background: var(--primary); color: white; border: none; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                Submit Offer ➔
                            </button>

                        </form>

                    </div>

                </div>

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
                    Connecting farmers and consumers through a smarter agricultural marketplace.
                </p>

            </div>


            <div class="footer-column">

                <h3>Farmer</h3>

                <a href="farmer.php">Dashboard</a>

                <a href="farmer-products.php">My Products</a>

                <a href="farmer-bookings.php">Harvest Bookings</a>

                <a href="farmer-orders.php">Orders</a>

                <a href="farmer-dss.php">Decision Support</a>

            </div>


            <div class="footer-column">

                <h3>Account</h3>

                <a href="farmer-profile.php">My Profile</a>

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
