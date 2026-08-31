<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Future Harvest Bookings | AgroLink</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/farmer-bookings.css">

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

                <a href="farmer-bookings.php" class="active-nav">Harvest Bookings</a>

                <a href="farmer-demands.php">Demand Broadcasts</a>

                <a href="farmer-orders.php">Orders</a>

                <a href="farmer-dss.php">DSS</a>

            </nav>


            <div class="farmer-actions" style="display: flex; align-items: center; gap: 14px;">

                <a href="#" style="text-decoration: none; font-size: 16px;">🔔</a>

                <a href="farmer-profile.php" style="text-decoration: none; display: flex; align-items: center; gap: 8px; color: var(--dark); font-weight: 600; font-size: 14px;">
                    <span style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">A</span>
                    Abrar
                </a>

                <a href="logout.php" style="text-decoration: none; padding: 8px 12px; border: 1px solid var(--border); border-radius: 5px; font-size: 13px; font-weight: 600; color: var(--dark);">Logout</a>

            </div>

        </div>

    </header>



    <!-- ================= MAIN ================= -->

    <main class="bookings-page">

        <div class="container">


            <div class="bookings-header">

                <div>

                    <span style="color: var(--primary); font-size: 11px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">
                        SRS MODULE 3.4
                    </span>

                    <h1>
                        Future Harvest Bookings & Progress
                    </h1>

                    <p style="color: var(--light-text); font-size: 14px;">
                        Manage pre-booked crop batches, track advance deposits, and update cultivation stages.
                    </p>

                </div>


                <a href="add-product.php" style="padding: 10px 18px; background: var(--primary); color: white; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none;">
                    + Publish Future Harvest Batch
                </a>

            </div>



            <!-- METRIC CARDS -->

            <div class="bookings-stats-grid">

                <div class="booking-stat-box">

                    <span>ACTIVE HARVEST BATCHES</span>

                    <strong>3 Crops</strong>

                </div>

                <div class="booking-stat-box">

                    <span>TOTAL PRE-BOOKED QTY</span>

                    <strong>1,850 kg</strong>

                </div>

                <div class="booking-stat-box">

                    <span>ADVANCE DEPOSITS RECEIVED</span>

                    <strong style="color: #2e7d32;">৳24,500</strong>

                </div>

                <div class="booking-stat-box">

                    <span>EST. BALANCE UPON DELIVERY</span>

                    <strong style="color: #f57c00;">৳98,200</strong>

                </div>

            </div>



            <!-- HARVEST BATCH 1 -->

            <div class="harvest-manage-card">

                <div class="harvest-top">

                    <div>

                        <h3>Winter Diamond Potatoes (ডায়মন্ড আলু) - Batch #FB-104</h3>

                        <span style="font-size: 13px; color: var(--light-text);">
                            Field: North Acre 2 • Expected Harvest Date: <strong>05 Dec 2026</strong>
                        </span>

                    </div>

                    <span class="status-tag vegetative">
                        Stage: Vegetative Growth 🌿
                    </span>

                </div>


                <!-- Cultivation Stage Progress Tracker -->

                <div class="stage-tracker">

                    <div class="stage-step active">

                        <div class="stage-dot">✓</div>

                        <span class="stage-label">Sown & Germinated</span>

                    </div>

                    <div class="stage-step active">

                        <div class="stage-dot">02</div>

                        <span class="stage-label">Vegetative Growth</span>

                    </div>

                    <div class="stage-step">

                        <div class="stage-dot">03</div>

                        <span class="stage-label">Tuber Maturation</span>

                    </div>

                    <div class="stage-step">

                        <div class="stage-dot">04</div>

                        <span class="stage-label">Harvested & Packaged</span>

                    </div>

                    <div class="stage-step">

                        <div class="stage-dot">05</div>

                        <span class="stage-label">Ready for Dispatch</span>

                    </div>

                </div>


                <div style="display: flex; justify-content: space-between; align-items: center; background: #fafcf9; padding: 12px 18px; border-radius: 8px; border: 1px solid var(--border); font-size: 13px; margin-bottom: 18px;">

                    <div>
                        <span>Total Batch: <strong>2,000 kg</strong></span> |
                        <span>Pre-Booked: <strong style="color: #2e7d32;">1,200 kg (60%)</strong></span> |
                        <span>Price: <strong>৳36/kg</strong></span>
                    </div>

                    <div style="display: flex; gap: 10px;">

                        <select style="padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 12px; background: white;">
                            <option>Update Stage: Tuber Maturation</option>
                            <option>Update Stage: Harvested</option>
                            <option>Update Stage: Ready for Delivery</option>
                        </select>

                        <button type="button" onclick="alert('Stage updated! Notified 8 pre-booking consumers.')" style="padding: 6px 14px; background: var(--primary); color: white; border: none; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">
                            Save & Notify Buyers
                        </button>

                    </div>

                </div>


                <!-- Consumer Reservations List -->

                <h4 style="font-size: 14px; color: var(--dark); margin-bottom: 8px;">
                    Consumer Pre-Bookings for this Batch (8 Buyers)
                </h4>

                <table class="reservations-table">

                    <thead>

                        <tr>

                            <th>Booking ID</th>

                            <th>Consumer</th>

                            <th>Quantity Booked</th>

                            <th>Total Deal Value</th>

                            <th>Advance Paid (15%)</th>

                            <th>Delivery Address</th>

                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody>

                        <tr>

                            <td><strong>#BK-9021</strong></td>

                            <td>MD Abrar Jawad</td>

                            <td>100 kg</td>

                            <td><strong>৳3,600</strong></td>

                            <td><strong style="color: #2e7d32;">৳540 Paid</strong></td>

                            <td>Dhanmondi, Dhaka</td>

                            <td><span class="status-tag vegetative">Confirmed</span></td>

                        </tr>

                        <tr>

                            <td><strong>#BK-9014</strong></td>

                            <td>Green Garden Supermarket</td>

                            <td>500 kg</td>

                            <td><strong>৳18,000</strong></td>

                            <td><strong style="color: #2e7d32;">৳2,700 Paid</strong></td>

                            <td>Uttara, Dhaka</td>

                            <td><span class="status-tag vegetative">Confirmed</span></td>

                        </tr>

                    </tbody>

                </table>

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
