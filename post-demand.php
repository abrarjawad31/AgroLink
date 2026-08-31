<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Post Crop Demand Request | AgroLink</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/post-demand.css">

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

                <span class="logo-icon">🌱</span>

                <span>Agro<span>Link</span></span>

            </a>


            <nav class="nav-menu">

                <a href="consumer-dashboard.php">Home</a>

                <a href="marketplace.php">Marketplace</a>

                <a href="future-harvests.php">Future Harvests</a>

                <a href="consumer-demands.php" class="active">Demand Hub</a>

                <a href="my-orders.php">My Orders</a>

            </nav>


            <div class="consumer-actions" style="display: flex; align-items: center; gap: 16px;">

                <a href="consumer-profile.php" style="text-decoration: none; display: flex; align-items: center; gap: 8px; color: var(--dark); font-weight: 600; font-size: 14px;">
                    <span style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">A</span>
                    Abrar
                </a>

                <a href="logout.php" style="text-decoration: none; padding: 8px 14px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px; font-weight: 600; color: var(--dark);">Logout</a>

            </div>

        </div>

    </header>



    <!-- ================= MAIN ================= -->

    <main class="post-demand-page">

        <div class="container">

            <div class="demand-form-card">

                <div class="demand-header">

                    <span style="color: var(--primary); font-size: 11px; font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase;">
                        SRS MODULE 3.5: DEMAND-BASED TRADING (FR-20)
                    </span>

                    <h1>
                        Broadcast a Purchasing Demand Request
                    </h1>

                    <p style="color: var(--light-text); font-size: 14px;">
                        Specify the agricultural products you need in bulk or customized quantities. Verified local farmers will receive your broadcast and submit competitive fulfillment offers.
                    </p>

                </div>


                <form action="consumer-demands.php" method="GET">

                    <div class="demand-grid">

                        <div class="form-group">

                            <label for="crop-name">Crop / Product Required *</label>

                            <input type="text" id="crop-name" name="crop_name" placeholder="e.g. Organic Red Tomatoes, Diamond Potatoes" required>

                        </div>


                        <div class="form-group">

                            <label for="category">Category *</label>

                            <select id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option>Organic Vegetables</option>
                                <option>Fresh Fruits</option>
                                <option>Grains & Cereals</option>
                                <option>Pulses & Spices</option>
                                <option>Dairy & Honey</option>
                            </select>

                        </div>


                        <div class="form-group">

                            <label for="quantity">Required Quantity *</label>

                            <input type="number" id="quantity" name="quantity" placeholder="e.g. 500" min="1" required>

                        </div>


                        <div class="form-group">

                            <label for="unit">Measurement Unit *</label>

                            <select id="unit" name="unit" required>
                                <option>kg (Kilogram)</option>
                                <option>Mon (40 kg)</option>
                                <option>Ton</option>
                                <option>Pieces / Bundles</option>
                            </select>

                        </div>


                        <div class="form-group">

                            <label for="target-price">Target Budget Price per Unit (৳) *</label>

                            <input type="number" id="target-price" name="target_price" placeholder="e.g. 70" required>

                        </div>


                        <div class="form-group">

                            <label for="deadline">Required Delivery Window *</label>

                            <input type="date" id="deadline" name="deadline" required>

                        </div>

                    </div>


                    <div class="form-group" style="margin-bottom: 20px;">

                        <label for="location">Preferred Delivery Region *</label>

                        <select id="location" name="location" required>
                            <option>Dhanmondi / Dhaka Metro</option>
                            <option>Uttara / Gazipur</option>
                            <option>Chattogram Metro</option>
                            <option>Rajshahi City</option>
                            <option>Sylhet City</option>
                        </select>

                    </div>


                    <div class="form-group" style="margin-bottom: 24px;">

                        <label for="notes">Quality Specifications / Notes (Optional)</label>

                        <textarea id="notes" name="notes" rows="3" placeholder="e.g. Must be 100% pesticide-free, size medium to large, fresh harvest preferred..."></textarea>

                    </div>


                    <button type="submit" class="post-demand-btn">
                        📢 Broadcast Demand Request to Verified Farmers
                    </button>

                </form>

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
