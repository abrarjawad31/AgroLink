<?php

$messageSent = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $topic = trim($_POST["topic"] ?? "");
    $message = trim($_POST["message"] ?? "");

    if (
        $name !== "" &&
        filter_var($email, FILTER_VALIDATE_EMAIL) &&
        $topic !== "" &&
        $message !== ""
    ) {
        $messageSent = true;
    }
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact AgroLink</title>
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
                <a href="about.php">About</a>
                <a href="contact.php" class="active">Contact</a>
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
                <span class="public-eyebrow">GET IN TOUCH</span>
                <h1>Let’s keep your <span>agricultural work</span> moving.</h1>
                <p>Have a question about an order, listing, demand, or future harvest? Send us the details and the AgroLink team can point you in the right direction.</p>
            </div>
        </section>

        <section class="public-main">
            <div class="container">
                <?php if ($messageSent): ?>
                    <div class="booking-message success">
                        <p>Thanks, <?= e($name) ?>. Your message has been received. We will review your request and follow up at <?= e($email) ?>.</p>
                    </div>
                <?php endif; ?>

                <div class="contact-layout">
                    <section class="contact-details">
                        <span class="public-eyebrow">CONTACT DETAILS</span>
                        <h2>We’re here to help.</h2>
                        <p class="contact-note">For order-specific help, include your order or demand details so we can understand the situation quickly.</p>

                        <div class="contact-detail">
                            <span class="contact-detail-icon">✉</span>
                            <div><strong>Email</strong><span>support@agrolink.local</span></div>
                        </div>
                        <div class="contact-detail">
                            <span class="contact-detail-icon">☎</span>
                            <div><strong>Phone</strong><span>+880 1XXX-XXXXXX</span></div>
                        </div>
                        <div class="contact-detail">
                            <span class="contact-detail-icon">⌖</span>
                            <div><strong>Service area</strong><span>Connecting local farmers and consumers</span></div>
                        </div>
                    </section>

                    <section class="contact-form">
                        <span class="public-eyebrow">SEND A MESSAGE</span>
                        <h2>How can we help?</h2>
                        <form method="POST" action="contact.php">
                            <div class="contact-form-grid">
                                <div class="form-field">
                                    <label for="name">Name</label>
                                    <input id="name" name="name" type="text" value="<?= e($_POST["name"] ?? "") ?>" required>
                                </div>
                                <div class="form-field">
                                    <label for="email">Email</label>
                                    <input id="email" name="email" type="email" value="<?= e($_POST["email"] ?? "") ?>" required>
                                </div>
                                <div class="form-field full-width">
                                    <label for="topic">Topic</label>
                                    <select id="topic" name="topic" required>
                                        <option value="">Choose a topic</option>
                                        <option value="Order support">Order support</option>
                                        <option value="Farmer account">Farmer account</option>
                                        <option value="Consumer account">Consumer account</option>
                                        <option value="Demand or negotiation">Demand or negotiation</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="form-field full-width">
                                    <label for="message">Message</label>
                                    <textarea id="message" name="message" required><?= e($_POST["message"] ?? "") ?></textarea>
                                </div>
                            </div>
                            <button type="submit" class="contact-submit">Send Message</button>
                        </form>
                    </section>
                </div>
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
