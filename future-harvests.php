<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Future Harvest Booking | AgroLink</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/future-harvests.css">

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

            <a href="consumer.php" class="logo">

                <span class="logo-icon">🌱</span>

                <span>Agro<span>Link</span></span>

            </a>


            <nav class="nav-menu">

                <a href="consumer-dashboard.php">Home</a>

                <a href="marketplace.php">Marketplace</a>

                <a href="future-harvests.php" class="active">Future Harvests</a>

                <a href="consumer-demands.php">Demand Hub</a>

                <a href="my-orders.php">My Orders</a>

            </nav>


            <div class="consumer-actions" style="display: flex; align-items: center; gap: 16px;">

                <a href="cart.php" style="text-decoration: none; padding: 6px 12px; border-radius: 20px; border: 1px solid var(--border); background: white; font-size: 14px; font-weight: 600; color: var(--dark);">
                    🛒 Cart <span style="background: var(--primary); color: white; padding: 2px 7px; border-radius: 10px; font-size: 11px;">2</span>
                </a>

                <a href="consumer-profile.php" style="text-decoration: none; display: flex; align-items: center; gap: 8px; color: var(--dark); font-weight: 600; font-size: 14px;">
                    <span style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">A</span>
                    Abrar
                </a>

                <a href="index.php" style="text-decoration: none; padding: 8px 14px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; font-weight: 600; color: var(--dark);">Logout</a>

            </div>

        </div>

    </header>



    <!-- ================= HERO ================= -->

    <section class="harvest-hero">

        <div class="container">

            <span style="display: inline-block; padding: 4px 12px; background: rgba(255,255,255,0.15); border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">
                SRS MODULE 3.4
            </span>

            <h1>
                Pre-Book <span>Future Harvests</span> Directly
            </h1>

            <p>
                Reserve upcoming crops before they are harvested. Guarantee fresh farm-to-table deliveries at discounted pre-order prices while providing farmers with cashflow stability.
            </p>

        </div>

    </section>



    <!-- ================= HARVEST BROWSER ================= -->

    <main class="harvest-container">

        <div class="container">


            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px;">

                <div>

                    <h2 style="font-family: 'Playfair Display', serif; font-size: 24px; color: var(--dark); margin-bottom: 4px;">
                        Upcoming Harvest Reservations
                    </h2>

                    <p style="font-size: 14px; color: var(--light-text);">
                        Lock in harvest quantities with a small 20% advance deposit.
                    </p>

                </div>


                <div style="display: flex; gap: 12px;">

                    <a href="post-demand.php" style="padding: 10px 18px; background: white; border: 1px solid var(--border); border-radius: 8px; color: var(--dark); font-size: 13px; font-weight: 600; text-decoration: none;">
                        + Post Custom Crop Demand
                    </a>

                </div>

            </div>



            <div class="harvest-grid">


                <!-- HARVEST 1 -->

                <div class="harvest-card">

                    <div class="harvest-image">

                        <img src="https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=600&q=80" alt="Winter Tomatoes">

                        <span class="harvest-badge">
                            Winter Batch
                        </span>

                        <span class="harvest-countdown">
                            Harvest in 24 Days
                        </span>

                    </div>

                    <div class="harvest-info">

                        <h3>Organic Roma Tomatoes (টমেটো)</h3>

                        <span class="harvest-farmer">
                            👨‍🌾 Rahim Agro Farm • Gazipur
                        </span>

                        <div class="progress-container">

                            <div class="progress-labels">

                                <span>Booked: <strong>650 kg</strong></span>

                                <span>Target: <strong>1,000 kg</strong></span>

                            </div>

                            <div class="progress-track">

                                <div class="progress-bar" style="width: 65%;"></div>

                            </div>

                        </div>

                        <div class="harvest-meta-grid">

                            <div>

                                <span>Expected Harvest:</span>

                                <strong>20 Nov 2026</strong>

                            </div>

                            <div>

                                <span>Deposit Required:</span>

                                <strong>20% (৳14/kg)</strong>

                            </div>

                            <div>

                                <span>Remaining Pool:</span>

                                <strong style="color: #2e7d32;">350 kg Left</strong>

                            </div>

                            <div>

                                <span>Fulfillment Status:</span>

                                <strong style="color: #f57c00;">Germinated 🌱</strong>

                            </div>

                        </div>

                        <div class="harvest-footer">

                            <div class="harvest-price">

                                <strong>৳70</strong>

                                <span>/ kg (Save ৳15)</span>

                            </div>

                            <a href="#" onclick="alert('Booking confirmed! 50kg reserved with 20% advance deposit.')" class="book-btn">
                                Pre-Book Harvest →
                            </a>

                        </div>

                    </div>

                </div>



                <!-- HARVEST 2 -->

                <div class="harvest-card">

                    <div class="harvest-image">

                        <img src="https://images.unsplash.com/photo-1518977676601-b53f82aba655?auto=format&fit=crop&w=600&q=80" alt="Diamond Potatoes">

                        <span class="harvest-badge" style="background: #e65100;">
                            Northern Harvest
                        </span>

                        <span class="harvest-countdown">
                            Harvest in 40 Days
                        </span>

                    </div>

                    <div class="harvest-info">

                        <h3>Diamond Potatoes (ডায়মন্ড আলু)</h3>

                        <span class="harvest-farmer">
                            👨‍🌾 Abrar Agro Farm • Bogura
                        </span>

                        <div class="progress-container">

                            <div class="progress-labels">

                                <span>Booked: <strong>1,200 kg</strong></span>

                                <span>Target: <strong>2,000 kg</strong></span>

                            </div>

                            <div class="progress-track">

                                <div class="progress-bar" style="width: 60%;"></div>

                            </div>

                        </div>

                        <div class="harvest-meta-grid">

                            <div>

                                <span>Expected Harvest:</span>

                                <strong>05 Dec 2026</strong>

                            </div>

                            <div>

                                <span>Deposit Required:</span>

                                <strong>15% (৳5.5/kg)</strong>

                            </div>

                            <div>

                                <span>Remaining Pool:</span>

                                <strong style="color: #2e7d32;">800 kg Left</strong>

                            </div>

                            <div>

                                <span>Fulfillment Status:</span>

                                <strong style="color: #2e7d32;">Vegetative 🌿</strong>

                            </div>

                        </div>

                        <div class="harvest-footer">

                            <div class="harvest-price">

                                <strong>৳36</strong>

                                <span>/ kg (Save ৳6)</span>

                            </div>

                            <a href="#" onclick="alert('Booking confirmed! 100kg reserved with 15% advance deposit.')" class="book-btn">
                                Pre-Book Harvest →
                            </a>

                        </div>

                    </div>

                </div>



                <!-- HARVEST 3 -->

                <div class="harvest-card">

                    <div class="harvest-image">

                        <img src="https://images.unsplash.com/photo-1568584711075-3d021a7c3ca3?auto=format&fit=crop&w=600&q=80" alt="Snowball Cauliflower">

                        <span class="harvest-badge" style="background: #2e7d32;">
                            Early Winter
                        </span>

                        <span class="harvest-countdown">
                            Harvest in 15 Days
                        </span>

                    </div>

                    <div class="harvest-info">

                        <h3>Snowball Cauliflower (ফুলকপি)</h3>

                        <span class="harvest-farmer">
                            👨‍🌾 Green Field Farms • Rajshahi
                        </span>

                        <div class="progress-container">

                            <div class="progress-labels">

                                <span>Booked: <strong>400 pcs</strong></span>

                                <span>Target: <strong>500 pcs</strong></span>

                            </div>

                            <div class="progress-track">

                                <div class="progress-bar" style="width: 80%;"></div>

                            </div>

                        </div>

                        <div class="harvest-meta-grid">

                            <div>

                                <span>Expected Harvest:</span>

                                <strong>10 Nov 2026</strong>

                            </div>

                            <div>

                                <span>Deposit Required:</span>

                                <strong>20% (৳8/pc)</strong>

                            </div>

                            <div>

                                <span>Remaining Pool:</span>

                                <strong style="color: #d32f2f;">100 pcs Left</strong>

                            </div>

                            <div>

                                <span>Fulfillment Status:</span>

                                <strong style="color: #2e7d32;">Maturing 🥦</strong>

                            </div>

                        </div>

                        <div class="harvest-footer">

                            <div class="harvest-price">

                                <strong>৳40</strong>

                                <span>/ piece (Save ৳10)</span>

                            </div>

                            <a href="#" onclick="alert('Booking confirmed! 20 pieces reserved.')" class="book-btn">
                                Pre-Book Harvest →
                            </a>

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
                    Connecting farmers and consumers through a smarter agricultural marketplace with future harvest reservations.
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
