<?php
session_start();
require_once "config.php";

$message = "";
$messageType = "";

// Check if user was redirected here after registration
if (isset($_GET["registered"]) && $_GET["registered"] == "1") {
    $message = "Registration successful! Please login to continue.";
    $messageType = "success";
}


// ================= LOGIN =================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";


    // ================= VALIDATION =================

    if ($email === "" || $password === "") {

        $message = "Please enter your email and password.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "error";

    } else {

        // ================= FIND USER =================

        $stmt = $conn->prepare(
            "SELECT id, name, email, password, role, status
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();


        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();


            // ================= CHECK STATUS =================

            if ($user["status"] === "blocked") {

                $message = "Your account has been blocked. Please contact support.";
                $messageType = "error";

            } elseif ($user["status"] === "pending") {

                $message = "Your account is still pending approval.";
                $messageType = "error";

            } elseif (password_verify($password, $user["password"])) {

                // ================= CREATE SESSION =================

                session_regenerate_id(true);

                $_SESSION["user_id"] = $user["id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["user_email"] = $user["email"];
                $_SESSION["role"] = $user["role"];


                // ================= ROLE REDIRECTION =================

                if ($user["role"] === "consumer") {

                    header("Location: consumer-dashboard.php");
                    exit();

                } elseif ($user["role"] === "farmer") {

                    header("Location: farmer-dashboard.php");
                    exit();

                } elseif ($user["role"] === "admin") {

                    header("Location: admin-dashboard.php");
                    exit();

                } else {

                    // Unknown role
                    session_unset();
                    session_destroy();

                    $message = "Invalid account role.";
                    $messageType = "error";
                }

            } else {

                $message = "Incorrect email or password.";
                $messageType = "error";
            }

        } else {

            $message = "Incorrect email or password.";
            $messageType = "error";
        }

        $stmt->close();
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login | AgroLink</title>


    <!-- Main CSS -->

    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <!-- Register CSS for matching design -->

    <link
        rel="stylesheet"
        href="css/register.css"
    >


    <!-- Google Fonts -->

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


    <!-- ================= NAVBAR ================= -->

    <header class="header">

        <div class="container navbar">


            <!-- LOGO -->

            <a
                href="index.php"
                class="logo"
            >

                <span class="logo-icon">
                    🌱
                </span>

                <span>
                    Agro<span>Link</span>
                </span>

            </a>


            <!-- NAVIGATION -->

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


            <!-- BUTTONS -->

            <div class="nav-buttons">

                <a
                    href="login.php"
                    class="login-btn active-register"
                >
                    Login
                </a>

                <a
                    href="register.php"
                    class="register-btn"
                >
                    Register
                </a>

            </div>

        </div>

    </header>



    <!-- ================= LOGIN SECTION ================= -->

    <main class="register-section">

        <div class="register-container">


            <!-- ================= LEFT SIDE ================= -->

            <div class="register-intro">


                <span class="section-tag">
                    WELCOME BACK
                </span>


                <h1>

                    Welcome Back to
                    <span>AgroLink</span>

                </h1>


                <p>

                    Login to your AgroLink account to access
                    your marketplace, products, orders, and
                    agricultural services.

                </p>


                <div class="register-highlights">


                    <!-- Highlight 1 -->

                    <div class="highlight">

                        <span class="highlight-icon">
                            🛒
                        </span>

                        <div>

                            <strong>
                                Shop Fresh Products
                            </strong>

                            <p>
                                Browse agricultural products
                                directly from farmers.
                            </p>

                        </div>

                    </div>


                    <!-- Highlight 2 -->

                    <div class="highlight">

                        <span class="highlight-icon">
                            🌾
                        </span>

                        <div>

                            <strong>
                                Manage Your Farm
                            </strong>

                            <p>
                                Farmers can manage their products
                                and harvest listings.
                            </p>

                        </div>

                    </div>


                    <!-- Highlight 3 -->

                    <div class="highlight">

                        <span class="highlight-icon">
                            🤝
                        </span>

                        <div>

                            <strong>
                                Connect Through AgroLink
                            </strong>

                            <p>
                                Build direct connections between
                                farmers and consumers.
                            </p>

                        </div>

                    </div>


                </div>

            </div>



            <!-- ================= LOGIN CARD ================= -->

            <div class="register-card">


                <!-- HEADER -->

                <div class="register-card-header">

                    <h2>
                        Login
                    </h2>

                    <p>
                        Login to continue to your AgroLink account
                    </p>

                </div>



                <!-- ================= MESSAGE ================= -->

                <?php if ($message !== ""): ?>

                    <?php if ($messageType === "success"): ?>

                        <div
                            class="login-message success"
                            style="
                                margin-bottom: 20px;
                                padding: 12px 15px;
                                border-radius: 8px;
                                background: #ecfdf3;
                                color: #027a48;
                                font-size: 14px;
                            "
                        >

                            <?php
                            echo htmlspecialchars($message);
                            ?>

                        </div>

                    <?php else: ?>

                        <div
                            class="login-message error"
                            style="
                                margin-bottom: 20px;
                                padding: 12px 15px;
                                border-radius: 8px;
                                background: #fff1f1;
                                color: #b42318;
                                font-size: 14px;
                            "
                        >

                            <?php
                            echo htmlspecialchars($message);
                            ?>

                        </div>

                    <?php endif; ?>

                <?php endif; ?>



                <!-- ================= FORM ================= -->

                <form
                    method="POST"
                    action="login.php"
                >


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



                    <!-- ================= PASSWORD ================= -->

                    <div class="form-group">

                        <label for="password">
                            Password
                        </label>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                        >

                    </div>



                    <!-- ================= OPTIONS ================= -->

                    <div
                        style="
                            display: flex;
                            justify-content: flex-end;
                            margin-bottom: 20px;
                        "
                    >

                        <a
                            href="#"
                            style="
                                font-size: 14px;
                                text-decoration: none;
                            "
                        >
                            Forgot Password?
                        </a>

                    </div>



                    <!-- ================= LOGIN BUTTON ================= -->

                    <button
                        type="submit"
                        class="register-submit"
                    >

                        Login

                    </button>


                </form>



                <!-- ================= REGISTER PROMPT ================= -->

                <div class="login-prompt">

                    <span>
                        Don't have an account?
                    </span>


                    <a href="register.php">
                        Create Account
                    </a>

                </div>


            </div>

        </div>

    </main>



    <!-- ================= FOOTER ================= -->

    <footer class="footer">

        <div class="container footer-grid">


            <!-- ================= ABOUT ================= -->

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