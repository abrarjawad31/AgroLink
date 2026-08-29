<?php

require_once "auth.php";
require_once "db.php";

// Only logged-in consumers can access cart
requireConsumer();


// ============================================================
// CURRENT CONSUMER
// ============================================================

$consumer_id = (int) $_SESSION["user_id"];


// ============================================================
// GET CONSUMER INFORMATION
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

    $user_stmt->bind_param("i", $consumer_id);
    $user_stmt->execute();

    $user_result = $user_stmt->get_result();

    if ($user_row = $user_result->fetch_assoc()) {
        $consumer_name = $user_row["name"];
    }

    $user_stmt->close();
}


// First letter for profile avatar
$avatar_letter = strtoupper(
    substr(trim($consumer_name), 0, 1)
);


// ============================================================
// GET CART ITEMS
// ============================================================

$cart_items = [];

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


// ============================================================
// FETCH PRODUCTS IN CART
// ============================================================

if ($cart_id > 0) {

    $items_stmt = $conn->prepare("
        SELECT
            ci.id AS cart_item_id,
            ci.product_id,
            ci.quantity AS cart_quantity,

            p.name,
            p.category,
            p.price,
            p.unit,
            p.quantity AS available_quantity,
            p.location,
            p.image,
            p.status,

            u.name AS farmer_name

        FROM cart_items ci

        INNER JOIN products p
            ON p.id = ci.product_id

        INNER JOIN users u
            ON u.id = p.farmer_id

        WHERE ci.cart_id = ?

        ORDER BY ci.created_at DESC
    ");

    if ($items_stmt) {

        $items_stmt->bind_param("i", $cart_id);
        $items_stmt->execute();

        $items_result = $items_stmt->get_result();

        while ($item = $items_result->fetch_assoc()) {

            $cart_items[] = $item;
        }

        $items_stmt->close();
    }
}


// ============================================================
// CART CALCULATIONS
// ============================================================

$total_items = 0;
$subtotal = 0;

foreach ($cart_items as $item) {

    $quantity = (float) $item["cart_quantity"];
    $price = (float) $item["price"];

    $total_items += $quantity;

    $subtotal += ($quantity * $price);
}


// Delivery charge
$delivery_charge = 0;

// Free delivery for empty cart
if ($subtotal > 0) {
    $delivery_charge = 60;
}


// Discount
$discount = 0;


// Final total
$grand_total = $subtotal + $delivery_charge - $discount;


// ============================================================
// HELPER: PRODUCT IMAGE
// ============================================================

function getProductImage($image)
{
    if (!empty($image)) {

        // If database already contains a full URL
        if (
            strpos($image, "http://") === 0 ||
            strpos($image, "https://") === 0
        ) {
            return $image;
        }

        return $image;
    }

    // Default image
    return "images/product-placeholder.jpg";
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

    <title>Shopping Cart | AgroLink</title>

    <!-- Main website CSS -->
    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <!-- Cart page CSS -->
    <link
        rel="stylesheet"
        href="css/cart.css"
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


<!-- =========================================================
     NAVBAR
========================================================= -->

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

            <a href="marketplace.php">
                Marketplace
            </a>

            <a href="my-orders.php">
                My Orders
            </a>

            <a href="consumer-bookings.php">
                My Bookings
            </a>

            <a href="consumer-demands.php">
                My Demands
            </a>

        </nav>


        <!-- CONSUMER ACTIONS -->

        <div class="consumer-actions">


            <!-- CART -->

            <a
                href="cart.php"
                class="cart-link"
            >

                <span>
                    🛒
                </span>

                Cart

                <span class="cart-count">
                    <?php echo (int) $total_items; ?>
                </span>

            </a>


            <!-- PROFILE -->

            <a
                href="consumer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">
                    <?php echo htmlspecialchars($avatar_letter); ?>
                </span>

                <span class="profile-name">
                    <?php echo htmlspecialchars($consumer_name); ?>
                </span>

            </a>


            <!-- LOGOUT -->

            <a
                href="index.php"
                class="logout-btn"
            >
                Logout
            </a>


        </div>

    </div>

</header>



<!-- =========================================================
     CART HERO
========================================================= -->

<section class="cart-hero">

    <div class="container">

        <span class="section-tag">
            YOUR SHOPPING CART
        </span>

        <h1>
            Review Your
            <span>Cart</span>
        </h1>

        <p>
            Check your selected products before proceeding
            to checkout.
        </p>

    </div>

</section>



<!-- =========================================================
     CART SECTION
========================================================= -->

<main class="cart-section">

    <div class="container cart-layout">


        <!-- =====================================================
             CART ITEMS
        ====================================================== -->

        <section class="cart-items-section">


            <!-- CART HEADER -->

            <div class="cart-items-header">

                <div>

                    <h2>
                        Shopping Cart
                    </h2>

                    <p>
                        <?php echo count($cart_items); ?>
                        product<?php echo count($cart_items) !== 1 ? "s" : ""; ?>
                        in your cart
                    </p>

                </div>


                <a href="marketplace.php">
                    ← Continue Shopping
                </a>

            </div>



            <!-- =================================================
                 EMPTY CART
            ================================================== -->

            <?php if (empty($cart_items)): ?>

                <div class="cart-empty">

                    <div
                        style="
                            text-align:center;
                            padding:60px 20px;
                            border:1px solid var(--border);
                            border-radius:10px;
                        "
                    >

                        <div
                            style="
                                font-size:45px;
                                margin-bottom:15px;
                            "
                        >
                            🛒
                        </div>

                        <h2>
                            Your cart is empty
                        </h2>

                        <p
                            style="
                                color:var(--light-text);
                                font-size:12px;
                                margin:10px 0 20px;
                            "
                        >
                            You haven't added any products
                            to your cart yet.
                        </p>

                        <a
                            href="marketplace.php"
                            class="checkout-btn details-cart-btn"
                            style="
                                display:inline-flex;
                                width:auto;
                                padding:0 25px;
                                text-decoration:none;
                            "
                        >
                            Browse Marketplace
                        </a>

                    </div>

                </div>


            <?php else: ?>


                <!-- =================================================
                     CART PRODUCTS
                ================================================== -->

                <?php foreach ($cart_items as $item): ?>

                    <?php

                    $item_quantity =
                        (float) $item["cart_quantity"];

                    $item_price =
                        (float) $item["price"];

                    $item_total =
                        $item_quantity * $item_price;

                    ?>

                    <div class="cart-item">


                        <!-- PRODUCT IMAGE -->

                        <div class="cart-product-image">

                            <img
                                src="<?php
                                    echo htmlspecialchars(
                                        getProductImage(
                                            $item["image"]
                                        )
                                    );
                                ?>"
                                alt="<?php
                                    echo htmlspecialchars(
                                        $item["name"]
                                    );
                                ?>"
                            >

                        </div>


                        <!-- PRODUCT INFORMATION -->

                        <div class="cart-product-info">

                            <span>
                                <?php
                                    echo htmlspecialchars(
                                        $item["category"]
                                    );
                                ?>
                            </span>

                            <h3>
                                <?php
                                    echo htmlspecialchars(
                                        $item["name"]
                                    );
                                ?>
                            </h3>

                            <p>
                                👨‍🌾
                                <?php
                                    echo htmlspecialchars(
                                        $item["farmer_name"]
                                    );
                                ?>
                            </p>

                            <?php if (!empty($item["location"])): ?>

                                <p>
                                    📍
                                    <?php
                                        echo htmlspecialchars(
                                            $item["location"]
                                        );
                                    ?>
                                </p>

                            <?php endif; ?>

                            <small>
                                ৳<?php
                                    echo number_format(
                                        $item_price,
                                        2
                                    );
                                ?>
                                /
                                <?php
                                    echo htmlspecialchars(
                                        $item["unit"]
                                    );
                                ?>
                            </small>

                        </div>



                        <!-- QUANTITY -->

                        <div class="cart-quantity">

                            <button
                                type="button"
                                onclick="changeQuantity(
                                    <?php echo (int) $item["cart_item_id"]; ?>,
                                    -1
                                )"
                            >
                                −
                            </button>

                            <span>
                                <?php
                                    echo rtrim(
                                        rtrim(
                                            number_format(
                                                $item_quantity,
                                                2,
                                                ".",
                                                ""
                                            ),
                                            "0"
                                        ),
                                        "."
                                    );
                                ?>
                            </span>

                            <button
                                type="button"
                                onclick="changeQuantity(
                                    <?php echo (int) $item["cart_item_id"]; ?>,
                                    1
                                )"
                            >
                                +
                            </button>

                        </div>



                        <!-- ITEM PRICE -->

                        <div class="cart-price">

                            <strong>
                                ৳<?php
                                    echo number_format(
                                        $item_total,
                                        2
                                    );
                                ?>
                            </strong>

                            <span>
                                <?php
                                    echo number_format(
                                        $item_quantity,
                                        2
                                    );
                                ?>
                                ×
                                ৳<?php
                                    echo number_format(
                                        $item_price,
                                        2
                                    );
                                ?>
                            </span>

                        </div>



                        <!-- REMOVE -->

                        <button
                            type="button"
                            class="remove-item"
                            title="Remove item"
                            onclick="removeCartItem(
                                <?php
                                    echo (int) $item["cart_item_id"];
                                ?>
                            )"
                        >
                            ×
                        </button>


                    </div>

                <?php endforeach; ?>


            <?php endif; ?>


        </section>



        <!-- =====================================================
             ORDER SUMMARY
        ====================================================== -->

        <aside class="order-summary">

            <h2>
                Order Summary
            </h2>


            <div class="summary-row">

                <span>
                    Subtotal
                </span>

                <strong>
                    ৳<?php
                        echo number_format(
                            $subtotal,
                            2
                        );
                    ?>
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Delivery
                </span>

                <strong>
                    <?php if ($delivery_charge > 0): ?>

                        ৳<?php
                            echo number_format(
                                $delivery_charge,
                                2
                            );
                        ?>

                    <?php else: ?>

                        ৳0.00

                    <?php endif; ?>
                </strong>

            </div>


            <?php if ($discount > 0): ?>

                <div class="summary-row">

                    <span>
                        Discount
                    </span>

                    <strong class="discount">
                        −৳<?php
                            echo number_format(
                                $discount,
                                2
                            );
                        ?>
                    </strong>

                </div>

            <?php endif; ?>


            <div class="summary-divider"></div>


            <div class="summary-total">

                <span>
                    Total
                </span>

                <strong>
                    ৳<?php
                        echo number_format(
                            $grand_total,
                            2
                        );
                    ?>
                </strong>

            </div>


            <?php if (!empty($cart_items)): ?>

                <a
                    href="checkout.php"
                    class="checkout-btn details-cart-btn"
                >
                    Proceed to Checkout
                </a>

            <?php else: ?>

                <button
                    type="button"
                    class="checkout-btn"
                    disabled
                    style="opacity:0.5; cursor:not-allowed;"
                >
                    Proceed to Checkout
                </button>

            <?php endif; ?>


            <div class="secure-checkout">

                🔒 Secure Checkout

                <p>
                    Your order information is protected.
                </p>

            </div>


        </aside>


    </div>

</main>



<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    <div class="container footer-grid">


        <!-- ABOUT -->

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



        <!-- MARKETPLACE -->

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



        <!-- CONSUMER -->

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

            <a href="consumer-bookings.php">
                My Bookings
            </a>

            <a href="consumer-demands.php">
                My Demands
            </a>

        </div>



        <!-- SUPPORT -->

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



<!-- =========================================================
     CART JAVASCRIPT
========================================================= -->

<script>

function changeQuantity(cartItemId, change) {

    const formData = new FormData();

    formData.append("cart_item_id", cartItemId);
    formData.append("change", change);

    fetch("update-cart.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(data => {

        window.location.reload();

    })
    .catch(error => {

        console.error(error);

        alert("Unable to update cart.");

    });

}


function removeCartItem(cartItemId) {

    if (!confirm("Remove this product from your cart?")) {
        return;
    }

    const formData = new FormData();

    formData.append("cart_item_id", cartItemId);

    fetch("remove-cart-item.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(data => {

        window.location.reload();

    })
    .catch(error => {

        console.error(error);

        alert("Unable to remove item.");

    });

}

</script>


</body>

</html>