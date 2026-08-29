<?php
require_once "config.php";

$message = "";
$messageType = "";

// Default role when the page first loads
$role = "consumer";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get form data
    $name = trim($_POST["fullname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm-password"] ?? "";
    $role = $_POST["role"] ?? "consumer";
    $terms = isset($_POST["terms"]);


    // ================= VALIDATION =================

    if ($name === "" || $email === "" || $phone === "" || $password === "") {

        $message = "Please fill in all required fields.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "error";

    } elseif ($password !== $confirmPassword) {

        $message = "Passwords do not match.";
        $messageType = "error";

    } elseif (strlen($password) < 6) {

        $message = "Password must be at least 6 characters long.";
        $messageType = "error";

    } elseif (!$terms) {

        $message = "Please agree to the Terms & Conditions and Privacy Policy.";
        $messageType = "error";

    } elseif ($role !== "consumer" && $role !== "farmer") {

        $message = "Invalid account type selected.";
        $messageType = "error";

    } else {

        // ================= CHECK EXISTING EMAIL =================

        $check = $conn->prepare(
            "SELECT id FROM users WHERE email = ?"
        );

        $check->bind_param("s", $email);
        $check->execute();

        $result = $check->get_result();


        if ($result->num_rows > 0) {

            $message = "This email is already registered. Please use another email or login.";
            $messageType = "error";

            $check->close();

        } else {

            // ================= HASH PASSWORD =================

            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );


            // ================= INSERT USER =================

            $stmt = $conn->prepare(
                "INSERT INTO users
                (name, email, phone, password, role, status)
                VALUES (?, ?, ?, ?, ?, 'active')"
            );


            if ($stmt) {

                $stmt->bind_param(
                    "sssss",
                    $name,
                    $email,
                    $phone,
                    $hashedPassword,
                    $role
                );


                if ($stmt->execute()) {

                    $stmt->close();
                    $check->close();

                    // Registration successful
                    header("Location: login.php?registered=1");
                    exit();

                } else {

                    $message = "Registration failed. Please try again.";
                    $messageType = "error";

                    $stmt->close();
                    $check->close();
                }

            } else {

                $message = "Database error. Please try again.";
                $messageType = "error";

                $check->close();
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Register | AgroLink</title>

    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/register.css">

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

            <a href="index.php" class="logo">

                <span class="logo-icon">
                    🌱
                </span>

                <span>
                    Agro<span>Link</span>
                </span>

            </a>


            <nav class="nav-menu">

                <a href="index.php">
                    Home
                </a>

                <a href="about.php">
                    About
                </a>

                <a href="contact.php">
                    Contact
                </a>

            </nav>


            <div class="nav-buttons">

                <a href="login.php" class="login-btn">
                    Login
                </a>

                <a href="register.php" class="register-btn active-register">
                    Register
                </a>

            </div>

        </div>

    </header>



    <!-- ================= REGISTER SECTION ================= -->

    <main class="register-section">

        <div class="register-container">


            <!-- ================= LEFT SIDE ================= -->

            <div class="register-intro">

                <span class="section-tag">
                    JOIN AGROLINK
                </span>


                <h1>

                    Be Part of the
                    <span>AgroLink</span> Community

                </h1>


                <p>

                    Whether you want to buy fresh agricultural
                    products or sell your products directly to
                    consumers, AgroLink connects you with the
                    right people.

                </p>


                <div class="register-highlights">


                    <!-- Highlight 1 -->

                    <div class="highlight">

                        <span class="highlight-icon">
                            🥬
                        </span>

                        <div>

                            <strong>
                                Fresh Products
                            </strong>

                            <p>
                                Discover quality agricultural
                                products directly from farmers.
                            </p>

                        </div>

                    </div>


                    <!-- Highlight 2 -->

                    <div class="highlight">

                        <span class="highlight-icon">
                            🤝
                        </span>

                        <div>

                            <strong>
                                Direct Connection
                            </strong>

                            <p>
                                Connect farmers and consumers
                                through one marketplace.
                            </p>

                        </div>

                    </div>


                    <!-- Highlight 3 -->

                    <div class="highlight">

                        <span class="highlight-icon">
                            🌱
                        </span>

                        <div>

                            <strong>
                                Grow Together
                            </strong>

                            <p>
                                Build a smarter and more connected
                                agricultural community.
                            </p>

                        </div>

                    </div>


                </div>

            </div>



            <!-- ================= REGISTER CARD ================= -->

            <div class="register-card">


                <div class="register-card-header">

                    <h2>
                        Create Account
                    </h2>

                    <p>
                        Choose your account type to get started
                    </p>

                </div>



                <!-- ================= MESSAGE ================= -->

                <?php if ($message !== ""): ?>

                    <div
                        class="register-message <?php echo htmlspecialchars($messageType); ?>"
                        style="
                            margin-bottom: 20px;
                            padding: 12px 15px;
                            border-radius: 8px;
                            background: #fff1f1;
                            color: #b42318;
                            font-size: 14px;
                        "
                    >

                        <?php echo htmlspecialchars($message); ?>

                    </div>

                <?php endif; ?>



                <!-- ================= FORM ================= -->

                <form
                    method="POST"
                    action="register.php"
                >


                    <!-- ================= ROLE SELECTION ================= -->

                    <div class="role-section">

                        <label class="role-title">

                            I want to join AgroLink as a:

                        </label>


                        <div class="role-options">


                            <!-- ================= CONSUMER ================= -->

                            <label class="role-option">

                                <input
                                    type="radio"
                                    name="role"
                                    value="consumer"
                                    <?php echo ($role === "consumer") ? "checked" : ""; ?>
                                >


                                <div class="role-content">

                                    <span class="role-icon">
                                        🛒
                                    </span>


                                    <div>

                                        <strong>
                                            Consumer
                                        </strong>

                                        <span>
                                            Buy fresh products
                                        </span>

                                    </div>

                                </div>

                            </label>



                            <!-- ================= FARMER ================= -->

                            <label class="role-option">

                                <input
                                    type="radio"
                                    name="role"
                                    value="farmer"
                                    <?php echo ($role === "farmer") ? "checked" : ""; ?>
                                >


                                <div class="role-content">

                                    <span class="role-icon">
                                        🌾
                                    </span>


                                    <div>

                                        <strong>
                                            Farmer
                                        </strong>

                                        <span>
                                            Sell your products
                                        </span>

                                    </div>

                                </div>

                            </label>


                        </div>

                    </div>



                    <!-- ================= FULL NAME ================= -->

                    <div class="form-group">

                        <label for="fullname">
                            Full Name
                        </label>


                        <input
                            type="text"
                            id="fullname"
                            name="fullname"
                            placeholder="Enter your full name"
                            value="<?php echo htmlspecialchars($_POST["fullname"] ?? ""); ?>"
                            required
                        >

                    </div>



                    <!-- ================= EMAIL ================= -->

                    <div class="form-group">

                        <label for="email">
                            Email Address
                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email"
                            value="<?php echo htmlspecialchars($_POST["email"] ?? ""); ?>"
                            required
                        >

                    </div>



                    <!-- ================= PHONE ================= -->

                    <div class="form-group">

                        <label for="phone">
                            Phone Number
                        </label>


                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            placeholder="01XXXXXXXXX"
                            value="<?php echo htmlspecialchars($_POST["phone"] ?? ""); ?>"
                            required
                        >

                    </div>



                    <!-- ================= PASSWORD ================= -->

                    <div class="form-row">


                        <div class="form-group">

                            <label for="password">
                                Password
                            </label>


                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Create password"
                                required
                            >

                        </div>



                        <div class="form-group">

                            <label for="confirm-password">
                                Confirm Password
                            </label>


                            <input
                                type="password"
                                id="confirm-password"
                                name="confirm-password"
                                placeholder="Confirm password"
                                required
                            >

                        </div>


                    </div>



                    <!-- ================= TERMS ================= -->

                    <div class="terms-row">

                        <label>

                            <input
                                type="checkbox"
                                name="terms"
                                required
                            >


                            <span>

                                I agree to the

                                <a href="#">
                                    Terms & Conditions
                                </a>

                                and

                                <a href="#">
                                    Privacy Policy
                                </a>

                            </span>

                        </label>

                    </div>



                    <!-- ================= REGISTER BUTTON ================= -->

                    <button
                        type="submit"
                        class="register-submit"
                    >

                        Create Account

                    </button>


                </form>



                <!-- ================= LOGIN PROMPT ================= -->

                <div class="login-prompt">

                    <span>
                        Already have an account?
                    </span>


                    <a href="login.php">
                        Login
                    </a>

                </div>


            </div>

        </div>

    </main>



    <!-- ================= FOOTER ================= -->

    <footer class="footer">


        <div class="container footer-grid">


            <!-- ================= FOOTER ABOUT ================= -->

            <div class="footer-about">


                <a
                    href="index.php"
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


                <div class="social-links">

                    <a href="#">
                        f
                    </a>

                    <a href="#">
                        in
                    </a>

                    <a href="#">
                        𝕏
                    </a>

                </div>


            </div>



            <!-- ================= MARKETPLACE ================= -->

            <div class="footer-column">

                <h3>
                    Marketplace
                </h3>


                <a href="login.php">
                    All Products
                </a>

                <a href="login.php">
                    Vegetables
                </a>

                <a href="login.php">
                    Fruits
                </a>

                <a href="login.php">
                    Grains
                </a>

                <a href="login.php">
                    Dairy
                </a>

            </div>



            <!-- ================= FARMERS ================= -->

            <div class="footer-column">

                <h3>
                    For Farmers
                </h3>


                <a href="register.php">
                    Sell Products
                </a>

                <a href="register.php">
                    Farmer Dashboard
                </a>

                <a href="register.php">
                    Manage Products
                </a>

            </div>



            <!-- ================= SUPPORT ================= -->

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

                <a href="#">
                    Privacy Policy
                </a>

            </div>


        </div>



        <!-- ================= FOOTER BOTTOM ================= -->

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