<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About AgroLink</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/public-pages.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="header">
        <div class="container navbar">
            <a href="index.php" class="logo">
                <span class="logo-icon">🌱</span>
                <span>Agro<span>Link</span></span>
            </a>
            <nav class="nav-menu">
                <a href="index.php">Home</a>
                <a href="about.php" class="active">About</a>
                <a href="contact.php">Contact</a>
            </nav>
            <div class="nav-buttons">
                <a href="login.php" class="login-btn">Login</a>
                <a href="register.php" class="register-btn">Register</a>
            </div>
        </div>
    </header>

    <main>
        <section class="public-hero">
            <div class="container">
                <span class="public-eyebrow">OUR PURPOSE</span>
                <h1>Making the path from <span>farm to table</span> more direct.</h1>
                <p>AgroLink gives farmers and consumers one practical place to discover products, manage demand, plan future harvests, and build better agricultural relationships.</p>
            </div>
        </section>

        <section class="public-main">
            <div class="container">
                <section class="public-section">
                    <span class="public-eyebrow">ABOUT AGROLINK</span>
                    <h2>A marketplace built around trust.</h2>
                    <p>Farmers deserve a clearer route to customers. Consumers deserve better visibility into where their food comes from and how it is priced. AgroLink brings those needs together with tools for product listings, direct orders, demand broadcasting, negotiation, and future-harvest pre-booking.</p>
                    <div class="public-grid">
                        <article class="public-card">
                            <div class="public-card-icon">🌾</div>
                            <h3>For Farmers</h3>
                            <p>List products, manage orders, broadcast available harvests, and connect with serious buyers.</p>
                        </article>
                        <article class="public-card">
                            <div class="public-card-icon">🛒</div>
                            <h3>For Consumers</h3>
                            <p>Compare products, place orders, request what you need, and reserve upcoming harvests early.</p>
                        </article>
                        <article class="public-card">
                            <div class="public-card-icon">🤝</div>
                            <h3>For Better Trade</h3>
                            <p>Keep communication, pricing, quantities, and delivery expectations visible in one shared workflow.</p>
                        </article>
                    </div>
                </section>

                <section class="public-section public-split">
                    <div>
                        <span class="public-eyebrow">HOW IT WORKS</span>
                        <h2>Simple tools for real agricultural work.</h2>
                        <p>AgroLink is designed to make everyday marketplace tasks easier to understand and easier to complete.</p>
                    </div>
                    <ul class="public-list">
                        <li>Farmers publish products and future harvest opportunities.</li>
                        <li>Consumers browse, order, or post a specific demand.</li>
                        <li>Both sides can discuss price, quantity, and delivery terms.</li>
                        <li>Orders and reservations remain visible in each account.</li>
                    </ul>
                </section>

                <section class="public-cta">
                    <h2>Ready to take part?</h2>
                    <p>Join AgroLink as a farmer or consumer and start building a more connected local food marketplace.</p>
                    <a href="register.php" class="public-button">Create an Account</a>
                </section>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="container footer-grid">
            <div class="footer-about">
                <a href="index.php" class="logo footer-logo"><span class="logo-icon">🌱</span><span>Agro<span>Link</span></span></a>
                <p>Connecting farmers and consumers through a smarter agricultural marketplace.</p>
            </div>
            <div class="footer-column"><h3>Marketplace</h3><a href="marketplace.php">All Products</a><a href="future-harvests.php">Future Harvests</a><a href="register.php">Sell Products</a></div>
            <div class="footer-column"><h3>Account</h3><a href="login.php">Login</a><a href="register.php">Register</a></div>
            <div class="footer-column"><h3>Support</h3><a href="about.php">About Us</a><a href="contact.php">Contact Us</a></div>
        </div>
        <div class="footer-bottom"><div class="container"><p>© <?= date("Y") ?> AgroLink. All Rights Reserved.</p><p>Academic Project</p></div></div>
    </footer>
</body>
</html>
