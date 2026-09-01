<?php

require_once "auth.php";
require_once "db.php";

// Only logged-in consumers can access product details
requireConsumer();


// ============================================================
// CURRENT CONSUMER
// ============================================================

$consumer_id = (int) $_SESSION["user_id"];


// ============================================================
// GET PRODUCT ID
// ============================================================

$product_id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($product_id <= 0) {
    header("Location: marketplace.php");
    exit;
}


// ============================================================
// GET PRODUCT
// ============================================================

$product = null;

$product_stmt = $conn->prepare("
    SELECT
        p.id,
        p.farmer_id,
        p.name,
        p.category,
        p.description,
        p.price,
        p.unit,
        p.quantity,
        p.location,
        p.image,
        p.status,
        p.created_at,
        u.name AS farmer_name
    FROM products p
    INNER JOIN users u
        ON u.id = p.farmer_id
    WHERE p.id = ?
      AND p.status = 'available'
      AND p.quantity > 0
      AND u.role = 'farmer'
      AND u.status = 'active'
    LIMIT 1
");

if ($product_stmt) {

    $product_stmt->bind_param("i", $product_id);
    $product_stmt->execute();

    $product_result = $product_stmt->get_result();

    if ($product_row = $product_result->fetch_assoc()) {
        $product = $product_row;
    }

    $product_stmt->close();
}


// Product not found
if (!$product) {
    header("Location: marketplace.php");
    exit;
}


// ============================================================
// ADD TO CART
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = isset($_POST["action"])
        ? $_POST["action"]
        : "";

    $requested_quantity = isset($_POST["quantity"])
        ? (int) $_POST["quantity"]
        : 1;

    if ($requested_quantity < 1) {
        $requested_quantity = 1;
    }


    // --------------------------------------------------------
    // Validate stock
    // --------------------------------------------------------

    if ($requested_quantity > (int) $product["quantity"]) {

        header(
            "Location: product-details.php?id=" .
            $product_id .
            "&error=stock"
        );

        exit;
    }


    // --------------------------------------------------------
    // Add to cart
    // --------------------------------------------------------

    if ($action === "add_to_cart") {

        /*
         * Find existing cart for this consumer.
         * If no cart exists, create one.
         */

        $cart_id = 0;

        $cart_stmt = $conn->prepare("
            SELECT id
            FROM cart
            WHERE consumer_id = ?
            LIMIT 1
        ");

        if ($cart_stmt) {

            $cart_stmt->bind_param("i", $consumer_id);
            $cart_stmt->execute();

            $cart_result = $cart_stmt->get_result();

            if ($cart_row = $cart_result->fetch_assoc()) {
                $cart_id = (int) $cart_row["id"];
            }

            $cart_stmt->close();
        }


        // ----------------------------------------------------
        // Create cart if necessary
        // ----------------------------------------------------

        if ($cart_id <= 0) {

            $create_cart_stmt = $conn->prepare("
                INSERT INTO cart (consumer_id)
                VALUES (?)
            ");

            if ($create_cart_stmt) {

                $create_cart_stmt->bind_param(
                    "i",
                    $consumer_id
                );

                if ($create_cart_stmt->execute()) {
                    $cart_id = (int) $conn->insert_id;
                }

                $create_cart_stmt->close();
            }
        }


        // ----------------------------------------------------
        // Add / update cart item
        // ----------------------------------------------------

        if ($cart_id > 0) {

            /*
             * Check whether this product is already in cart.
             */

            $item_id = 0;
            $existing_quantity = 0;

            $item_stmt = $conn->prepare("
                SELECT
                    id,
                    quantity
                FROM cart_items
                WHERE cart_id = ?
                  AND product_id = ?
                LIMIT 1
            ");

            if ($item_stmt) {

                $item_stmt->bind_param(
                    "ii",
                    $cart_id,
                    $product_id
                );

                $item_stmt->execute();

                $item_result = $item_stmt->get_result();

                if ($item_row = $item_result->fetch_assoc()) {

                    $item_id = (int) $item_row["id"];

                    $existing_quantity =
                        (int) $item_row["quantity"];
                }

                $item_stmt->close();
            }


            // ------------------------------------------------
            // Existing product -> increase quantity
            // ------------------------------------------------

            if ($item_id > 0) {

                $new_quantity =
                    $existing_quantity +
                    $requested_quantity;


                // Do not exceed available stock
                if ($new_quantity > (int) $product["quantity"]) {

                    $new_quantity =
                        (int) $product["quantity"];
                }


                $update_item_stmt = $conn->prepare("
                    UPDATE cart_items
                    SET quantity = ?
                    WHERE id = ?
                ");

                if ($update_item_stmt) {

                    $update_item_stmt->bind_param(
                        "ii",
                        $new_quantity,
                        $item_id
                    );

                    $update_item_stmt->execute();

                    $update_item_stmt->close();
                }

            }

            // ------------------------------------------------
            // New product -> insert cart item
            // ------------------------------------------------

            else {

                $insert_item_stmt = $conn->prepare("
                    INSERT INTO cart_items
                        (cart_id, product_id, quantity)
                    VALUES
                        (?, ?, ?)
                ");

                if ($insert_item_stmt) {

                    $insert_item_stmt->bind_param(
                        "iii",
                        $cart_id,
                        $product_id,
                        $requested_quantity
                    );

                    $insert_item_stmt->execute();

                    $insert_item_stmt->close();
                }
            }


            // ------------------------------------------------
            // Go to cart
            // ------------------------------------------------

            header("Location: cart.php");
            exit;
        }
    }
}


// ============================================================
// CART COUNT
// ============================================================

$cart_count = 0;

$cart_count_stmt = $conn->prepare("
    SELECT COALESCE(SUM(ci.quantity), 0) AS total_items
    FROM cart c
    INNER JOIN cart_items ci
        ON ci.cart_id = c.id
    WHERE c.consumer_id = ?
");

if ($cart_count_stmt) {

    $cart_count_stmt->bind_param(
        "i",
        $consumer_id
    );

    $cart_count_stmt->execute();

    $cart_count_result =
        $cart_count_stmt->get_result();

    if ($cart_count_row =
        $cart_count_result->fetch_assoc()
    ) {

        $cart_count =
            (int) $cart_count_row["total_items"];
    }

    $cart_count_stmt->close();
}


// ============================================================
// CONSUMER INFORMATION
// ============================================================

$consumer_name = "Consumer";

$user_stmt = $conn->prepare("
    SELECT name
    FROM users
    WHERE id = ?
      AND role = 'consumer'
    LIMIT 1
");

if ($user_stmt) {

    $user_stmt->bind_param(
        "i",
        $consumer_id
    );

    $user_stmt->execute();

    $user_result = $user_stmt->get_result();

    if ($user_row = $user_result->fetch_assoc()) {
        $consumer_name = $user_row["name"];
    }

    $user_stmt->close();
}


// ============================================================
// PRODUCT IMAGE
// ============================================================

$image = trim($product["image"] ?? "");

if ($image !== "") {

    if (
        strpos($image, "http://") === 0 ||
        strpos($image, "https://") === 0
    ) {

        $image_url = $image;

    } else {

        $image_url =
            "uploads/products/" . $image;
    }

} else {

    $image_url =
        "https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=80";
}


// ============================================================
// QUANTITY
// ============================================================

$available_quantity =
    (int) $product["quantity"];


// ============================================================
// CATEGORY
// ============================================================

$category_name =
    htmlspecialchars($product["category"]);


// ============================================================
// PRODUCT TITLE
// ============================================================

$product_name =
    htmlspecialchars($product["name"]);


// ============================================================
// ERROR MESSAGE
// ============================================================

$error_message = "";

if (
    isset($_GET["error"]) &&
    $_GET["error"] === "stock"
) {

    $error_message =
        "The requested quantity is greater than the available stock.";
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

    <title>
        <?= $product_name ?> | AgroLink
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/product-details.css"
    >

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
            href="consumer-dashboard.php"
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

            <a href="consumer-dashboard.php">
                Home
            </a>

            <a
                href="marketplace.php"
                class="active"
            >
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


        <!-- CONSUMER ACTIONS -->

        <div class="consumer-actions">

            <a
                href="cart.php"
                class="cart-link"
            >

                <span>
                    🛒
                </span>

                Cart

                <span class="cart-count">
                    <?= $cart_count ?>
                </span>

            </a>


            <a
                href="consumer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $consumer_name,
                                0,
                                1
                            )
                        )
                    ) ?>

                </span>

                <span class="profile-name">

                    <?= htmlspecialchars(
                        $consumer_name
                    ) ?>

                </span>

            </a>


            <a
                href="logout.php"
                class="logout-btn"
            >
                Logout
            </a>

        </div>

    </div>

</header>


<!-- ================= BREADCRUMB ================= -->

<section class="product-breadcrumb">

    <div class="container">

        <a href="consumer-dashboard.php">
            Home
        </a>

        <span>
            ›
        </span>

        <a href="marketplace.php">
            Marketplace
        </a>

        <span>
            ›
        </span>

        <span>
            <?= $product_name ?>
        </span>

    </div>

</section>


<!-- ================= PRODUCT DETAILS ================= -->

<main>

<section class="product-details">

<div class="container product-details-grid">


<!-- ================= PRODUCT IMAGE ================= -->

<div class="product-gallery">

    <div class="main-product-image">

        <img
            src="<?= htmlspecialchars($image_url) ?>"
            alt="<?= $product_name ?>"
        >

        <span class="details-badge">

            <?php

            $category_lower =
                strtolower($product["category"]);

            if (
                strpos(
                    $category_lower,
                    "organic"
                ) !== false
            ) {

                echo "Organic";

            } else {

                echo "Fresh Product";
            }

            ?>

        </span>

    </div>

</div>


<!-- ================= PRODUCT INFORMATION ================= -->

<div class="product-details-info">


    <!-- CATEGORY -->

    <span class="details-category">

        <?= strtoupper(
            htmlspecialchars(
                $product["category"]
            )
        ) ?>

    </span>


    <!-- NAME -->

    <h1>

        <?= $product_name ?>

    </h1>


    <!-- PRICE -->

    <div class="details-price">

        <strong>

            ৳<?= number_format(
                (float) $product["price"],
                2
            ) ?>

        </strong>

        <span>

            /
            <?= htmlspecialchars(
                $product["unit"]
            ) ?>

        </span>

    </div>


    <!-- DESCRIPTION -->

    <p class="product-description">

        <?= nl2br(
            htmlspecialchars(
                $product["description"] ?? ""
            )
        ) ?>

    </p>


    <!-- PRODUCT META -->

    <div class="product-meta">


        <div class="meta-item">

            <span>
                Availability
            </span>

            <strong class="available">

                ✓ In Stock

            </strong>

        </div>


        <div class="meta-item">

            <span>
                Available Quantity
            </span>

            <strong>

                <?= $available_quantity ?>

                <?= htmlspecialchars(
                    $product["unit"]
                ) ?>

            </strong>

        </div>


        <div class="meta-item">

            <span>
                Minimum Order
            </span>

            <strong>
                1 <?= htmlspecialchars(
                    $product["unit"]
                ) ?>
            </strong>

        </div>


        <div class="meta-item">

            <span>
                Location
            </span>

            <strong>

                <?= htmlspecialchars(
                    $product["location"] ?? "Not specified"
                ) ?>

            </strong>

        </div>


    </div>


    <!-- FARMER -->

    <div class="seller-card">


        <div class="seller-avatar">
            👨‍🌾
        </div>


        <div class="seller-info">

            <span>
                SOLD BY
            </span>

            <strong>

                <?= htmlspecialchars(
                    $product["farmer_name"]
                ) ?>

            </strong>

            <small>

                📍

                <?= htmlspecialchars(
                    $product["location"] ?? "Location not specified"
                ) ?>

            </small>

        </div>


    </div>


    <!-- ERROR -->

    <?php if ($error_message !== ""): ?>

        <div
            style="
                margin-top: 15px;
                padding: 12px 15px;
                border-radius: 6px;
                background: #fce8e8;
                color: #b33a3a;
                font-size: 12px;
            "
        >

            <?= htmlspecialchars(
                $error_message
            ) ?>

        </div>

    <?php endif; ?>


    <!-- PURCHASE -->

    <form
        method="POST"
        action="product-details.php?id=<?= $product_id ?>"
        class="purchase-section"
        id="cartForm"
    >

        <input
            type="hidden"
            name="action"
            value="add_to_cart"
        >


        <div class="quantity-control">

            <button
                type="button"
                id="decreaseBtn"
            >
                −
            </button>


            <span id="quantityDisplay">
                1
            </span>


            <button
                type="button"
                id="increaseBtn"
            >
                +
            </button>

        </div>


        <input
            type="hidden"
            name="quantity"
            id="quantityInput"
            value="1"
        >


        <button
            type="submit"
            class="details-cart-btn"
        >

            🛒 Add to Cart

        </button>


        <button
            type="button"
            class="details-wishlist-btn"
            title="Add to wishlist"
        >

            ♡

        </button>


    </form>


    <!-- DELIVERY -->

    <div class="delivery-info">


        <div>

            <span class="delivery-icon">
                🚚
            </span>

            <div>

                <strong>
                    Delivery Available
                </strong>

                <p>
                    Delivery available across selected areas.
                </p>

            </div>

        </div>


        <div>

            <span class="delivery-icon">
                🌱
            </span>

            <div>

                <strong>
                    Direct From Farmer
                </strong>

                <p>
                    No unnecessary middlemen.
                </p>

            </div>

        </div>


    </div>


</div>

</div>

</section>


<!-- ================= DESCRIPTION ================= -->

<section class="product-information">

<div class="container">


<div class="information-tabs">

    <button
        type="button"
        class="info-tab active-tab"
    >
        Description
    </button>

    <button
        type="button"
        class="info-tab"
        onclick="document.getElementById('reviews').scrollIntoView({behavior:'smooth'})"
    >
        Reviews
    </button>

    <button
        type="button"
        class="info-tab"
    >
        Farmer Information
    </button>

</div>


<div class="information-content">

    <h2>
        Product Description
    </h2>


    <p>

        <?= nl2br(
            htmlspecialchars(
                $product["description"] ?? "No description available."
            )
        ) ?>

    </p>


    <h3>
        Product Information
    </h3>


    <ul>

        <li>
            Category:
            <?= htmlspecialchars(
                $product["category"]
            ) ?>
        </li>

        <li>
            Available quantity:
            <?= $available_quantity ?>
            <?= htmlspecialchars(
                $product["unit"]
            ) ?>
        </li>

        <li>
            Location:
            <?= htmlspecialchars(
                $product["location"] ?? "Not specified"
            ) ?>
        </li>

        <li>
            Directly sourced from farmer
        </li>

    </ul>

</div>

</div>

</section>


<!-- ================= REVIEWS ================= -->

<section
    class="reviews-section"
    id="reviews"
>

<div class="container">


<div class="reviews-heading">

    <div>

        <span class="section-tag">
            CUSTOMER FEEDBACK
        </span>

        <h2>
            What Customers Say
        </h2>

    </div>


    <div class="overall-rating">

        <strong>
            —
        </strong>

        <div>

            <div class="stars">
                ★★★★★
            </div>

            <span>
                Customer reviews
            </span>

        </div>

    </div>

</div>


<div class="review-grid">


<div class="review-card">

    <div class="review-top">

        <div class="review-user">

            <div class="review-avatar">
                A
            </div>

            <div>

                <strong>
                    AgroLink Customer
                </strong>

                <span>
                    Verified Buyer
                </span>

            </div>

        </div>

        <span class="review-date">
            Recent
        </span>

    </div>


    <div class="stars">
        ★★★★★
    </div>


    <p>
        Customer reviews for this product will appear here.
    </p>

</div>


</div>

</div>

</section>

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


<div class="footer-column">

    <h3>
        Marketplace
    </h3>

    <a href="marketplace.php">
        All Products
    </a>

    <a href="marketplace.php?category=Vegetables">
        Vegetables
    </a>

    <a href="marketplace.php?category=Fruits">
        Fruits
    </a>

    <a href="marketplace.php?category=Grains">
        Grains
    </a>

    <a href="marketplace.php?category=Dairy">
        Dairy
    </a>

</div>


<div class="footer-column">

    <h3>
        Consumer
    </h3>

    <a href="my-orders.php">
        My Orders
    </a>

    <a href="cart.php">
        Cart
    </a>

    <a href="future-harvests.php">
        Pre Bookings
    </a>

    <a href="consumer-demands.php">
        My Demands
    </a>

</div>


<div class="footer-column">

    <h3>
        Support
    </h3>

    <a href="about.html">
        About Us
    </a>

    <a href="contact.html">
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


<!-- ================= QUANTITY SCRIPT ================= -->

<script>

const maxQuantity = <?= $available_quantity ?>;

let quantity = 1;

const decreaseBtn =
    document.getElementById("decreaseBtn");

const increaseBtn =
    document.getElementById("increaseBtn");

const quantityDisplay =
    document.getElementById("quantityDisplay");

const quantityInput =
    document.getElementById("quantityInput");


function updateQuantity() {

    if (quantity < 1) {
        quantity = 1;
    }

    if (quantity > maxQuantity) {
        quantity = maxQuantity;
    }

    quantityDisplay.textContent =
        quantity;

    quantityInput.value =
        quantity;
}


decreaseBtn.addEventListener(
    "click",
    function () {

        if (quantity > 1) {

            quantity--;

            updateQuantity();
        }

    }
);


increaseBtn.addEventListener(
    "click",
    function () {

        if (quantity < maxQuantity) {

            quantity++;

            updateQuantity();
        }

    }
);


updateQuantity();

</script>


</body>

</html>