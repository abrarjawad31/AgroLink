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
// HANDLE CART ACTIONS
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
     * --------------------------------------------------------
     * UPDATE QUANTITY
     * --------------------------------------------------------
     */

    if (
        isset($_POST["action"]) &&
        $_POST["action"] === "update_quantity" &&
        isset($_POST["cart_item_id"]) &&
        isset($_POST["change"])
    ) {

        $cart_item_id = (int) $_POST["cart_item_id"];
        $change = (int) $_POST["change"];

        // Only allow +1 or -1
        if ($change !== 1 && $change !== -1) {
            $change = 0;
        }

        if ($cart_item_id > 0 && $change !== 0) {

            /*
             * Make sure this cart item belongs to the
             * currently logged-in consumer.
             *
             * Also retrieve the available product quantity.
             */
            $check_stmt = $conn->prepare("
                SELECT
                    ci.id,
                    ci.quantity AS cart_quantity,
                    ci.product_id,
                    p.quantity AS available_quantity,
                    p.status
                FROM cart_items ci
                INNER JOIN cart c
                    ON c.id = ci.cart_id
                INNER JOIN products p
                    ON p.id = ci.product_id
                WHERE ci.id = ?
                  AND c.consumer_id = ?
                LIMIT 1
            ");

            if ($check_stmt) {

                $check_stmt->bind_param(
                    "ii",
                    $cart_item_id,
                    $consumer_id
                );

                $check_stmt->execute();

                $check_result = $check_stmt->get_result();

                if ($check_row = $check_result->fetch_assoc()) {

                    $current_quantity =
                        (int) $check_row["cart_quantity"];

                    $available_quantity =
                        (int) $check_row["available_quantity"];

                    $new_quantity =
                        $current_quantity + $change;


                    /*
                     * ------------------------------------------------
                     * DECREASE
                     * ------------------------------------------------
                     *
                     * If quantity becomes 0, delete the cart item.
                     */
                    if ($new_quantity <= 0) {

                        $delete_stmt = $conn->prepare("
                            DELETE FROM cart_items
                            WHERE id = ?
                        ");

                        if ($delete_stmt) {

                            $delete_stmt->bind_param(
                                "i",
                                $cart_item_id
                            );

                            $delete_stmt->execute();

                            $delete_stmt->close();
                        }

                    }

                    /*
                     * ------------------------------------------------
                     * INCREASE
                     * ------------------------------------------------
                     *
                     * Never allow cart quantity to exceed
                     * current available product quantity.
                     */
                    elseif ($new_quantity > $available_quantity) {

                        // Keep current quantity unchanged.

                    }

                    /*
                     * ------------------------------------------------
                     * NORMAL UPDATE
                     * ------------------------------------------------
                     */

                    else {

                        $update_stmt = $conn->prepare("
                            UPDATE cart_items
                            SET quantity = ?
                            WHERE id = ?
                        ");

                        if ($update_stmt) {

                            $update_stmt->bind_param(
                                "ii",
                                $new_quantity,
                                $cart_item_id
                            );

                            $update_stmt->execute();

                            $update_stmt->close();
                        }
                    }
                }

                $check_stmt->close();
            }
        }


        /*
         * Return JSON response for JavaScript.
         */
        header("Content-Type: application/json");

        echo json_encode([
            "success" => true
        ]);

        exit;
    }



    /*
     * --------------------------------------------------------
     * REMOVE CART ITEM
     * --------------------------------------------------------
     */

    if (
        isset($_POST["action"]) &&
        $_POST["action"] === "remove_item" &&
        isset($_POST["cart_item_id"])
    ) {

        $cart_item_id = (int) $_POST["cart_item_id"];


        if ($cart_item_id > 0) {

            /*
             * Delete only if the cart item belongs to
             * the current consumer.
             */
            $delete_stmt = $conn->prepare("
                DELETE ci
                FROM cart_items ci
                INNER JOIN cart c
                    ON c.id = ci.cart_id
                WHERE ci.id = ?
                  AND c.consumer_id = ?
            ");

            if ($delete_stmt) {

                $delete_stmt->bind_param(
                    "ii",
                    $cart_item_id,
                    $consumer_id
                );

                $delete_stmt->execute();

                $delete_stmt->close();
            }
        }


        header("Content-Type: application/json");

        echo json_encode([
            "success" => true
        ]);

        exit;
    }
}


// ============================================================
// HANDLE ADD TO CART
// ============================================================

if (
    isset($_GET["add"]) &&
    is_numeric($_GET["add"])
) {

    $product_id = (int) $_GET["add"];


    /*
     * Verify product exists and is available.
     */
    $verify_stmt = $conn->prepare("
        SELECT
            id,
            quantity,
            status
        FROM products
        WHERE id = ?
          AND status = 'available'
          AND quantity > 0
        LIMIT 1
    ");


    if ($verify_stmt) {

        $verify_stmt->bind_param(
            "i",
            $product_id
        );

        $verify_stmt->execute();

        $verify_result =
            $verify_stmt->get_result();


        if ($verify_result->num_rows > 0) {

            $product_row =
                $verify_result->fetch_assoc();

            $available_quantity =
                (int) $product_row["quantity"];


            // ====================================================
            // GET OR CREATE CART
            // ====================================================

            $consumer_cart_id = 0;


            $get_cart_stmt = $conn->prepare("
                SELECT id
                FROM cart
                WHERE consumer_id = ?
                LIMIT 1
            ");


            if ($get_cart_stmt) {

                $get_cart_stmt->bind_param(
                    "i",
                    $consumer_id
                );

                $get_cart_stmt->execute();

                $cart_result =
                    $get_cart_stmt->get_result();


                if (
                    $cart_row =
                    $cart_result->fetch_assoc()
                ) {

                    $consumer_cart_id =
                        (int) $cart_row["id"];

                }

                $get_cart_stmt->close();
            }


            /*
             * Create cart if consumer doesn't have one.
             */
            if ($consumer_cart_id <= 0) {

                $create_cart = $conn->prepare("
                    INSERT INTO cart
                        (consumer_id, created_at)
                    VALUES
                        (?, NOW())
                ");


                if ($create_cart) {

                    $create_cart->bind_param(
                        "i",
                        $consumer_id
                    );

                    if ($create_cart->execute()) {

                        $consumer_cart_id =
                            (int) $conn->insert_id;
                    }

                    $create_cart->close();
                }
            }


            // ====================================================
            // ADD PRODUCT TO CART
            // ====================================================

            if ($consumer_cart_id > 0) {

                $check_item = $conn->prepare("
                    SELECT
                        id,
                        quantity
                    FROM cart_items
                    WHERE cart_id = ?
                      AND product_id = ?
                    LIMIT 1
                ");


                if ($check_item) {

                    $check_item->bind_param(
                        "ii",
                        $consumer_cart_id,
                        $product_id
                    );

                    $check_item->execute();

                    $item_result =
                        $check_item->get_result();


                    if (
                        $item_row =
                        $item_result->fetch_assoc()
                    ) {

                        /*
                         * Product already exists in cart.
                         */
                        $current_quantity =
                            (int) $item_row["quantity"];

                        $new_quantity =
                            $current_quantity + 1;


                        /*
                         * Do not exceed available stock.
                         */
                        if (
                            $new_quantity <=
                            $available_quantity
                        ) {

                            $update_item =
                                $conn->prepare("
                                    UPDATE cart_items
                                    SET quantity = ?
                                    WHERE id = ?
                                ");

                            if ($update_item) {

                                $cart_item_id =
                                    (int) $item_row["id"];

                                $update_item->bind_param(
                                    "ii",
                                    $new_quantity,
                                    $cart_item_id
                                );

                                $update_item->execute();

                                $update_item->close();
                            }
                        }

                    } else {

                        /*
                         * Product does not exist in cart.
                         */
                        $insert_item =
                            $conn->prepare("
                                INSERT INTO cart_items
                                    (
                                        cart_id,
                                        product_id,
                                        quantity,
                                        created_at
                                    )
                                VALUES
                                    (?, ?, 1, NOW())
                            ");

                        if ($insert_item) {

                            $insert_item->bind_param(
                                "ii",
                                $consumer_cart_id,
                                $product_id
                            );

                            $insert_item->execute();

                            $insert_item->close();
                        }
                    }


                    $check_item->close();
                }
            }
        }

        $verify_stmt->close();
    }


    /*
     * Remove ?add from URL.
     */
    header("Location: cart.php");

    exit;
}


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

    $user_stmt->bind_param(
        "i",
        $consumer_id
    );

    $user_stmt->execute();

    $user_result =
        $user_stmt->get_result();


    if (
        $user_row =
        $user_result->fetch_assoc()
    ) {

        $consumer_name =
            $user_row["name"];
    }


    $user_stmt->close();
}


// ============================================================
// AVATAR LETTER
// ============================================================

$avatar_letter = strtoupper(
    substr(
        trim($consumer_name),
        0,
        1
    )
);


// ============================================================
// GET CART
// ============================================================

$cart_id = 0;


$cart_stmt = $conn->prepare("
    SELECT id
    FROM cart
    WHERE consumer_id = ?
    LIMIT 1
");


if ($cart_stmt) {

    $cart_stmt->bind_param(
        "i",
        $consumer_id
    );

    $cart_stmt->execute();

    $cart_result =
        $cart_stmt->get_result();


    if (
        $cart_row =
        $cart_result->fetch_assoc()
    ) {

        $cart_id =
            (int) $cart_row["id"];
    }


    $cart_stmt->close();
}


// ============================================================
// GET CART ITEMS
// ============================================================

$cart_items = [];


if ($cart_id > 0) {

    $items_stmt = $conn->prepare("
        SELECT
            ci.id AS cart_item_id,
            ci.product_id,
            ci.quantity AS cart_quantity,

            p.name,
            p.category,
            p.description,
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

        $items_stmt->bind_param(
            "i",
            $cart_id
        );

        $items_stmt->execute();

        $items_result =
            $items_stmt->get_result();


        while (
            $item =
            $items_result->fetch_assoc()
        ) {

            /*
             * Make sure cart quantity does not exceed
             * current available stock.
             */
            $cart_quantity =
                (int) $item["cart_quantity"];

            $available_quantity =
                (int) $item["available_quantity"];


            /*
             * If product is no longer available,
             * keep it visible so the user knows.
             *
             * Otherwise cap cart quantity at stock.
             */
            if (
                $item["status"] === "available" &&
                $available_quantity > 0 &&
                $cart_quantity > $available_quantity
            ) {

                $cart_quantity =
                    $available_quantity;
            }


            $item["cart_quantity"] =
                $cart_quantity;


            $cart_items[] =
                $item;
        }


        $items_stmt->close();
    }
}


// ============================================================
// CART CALCULATIONS
// ============================================================

$cart_count = 0;

$subtotal = 0;


foreach ($cart_items as $item) {

    $quantity =
        (float) $item["cart_quantity"];

    $price =
        (float) $item["price"];


    $cart_count +=
        $quantity;


    $subtotal +=
        ($quantity * $price);
}


// ============================================================
// DELIVERY CHARGE
// ============================================================

$delivery_charge = 0;


/*
 * Free delivery for empty cart.
 *
 * Flat ৳60 delivery for a non-empty cart.
 */
if ($subtotal > 0) {

    $delivery_charge = 60;
}


// ============================================================
// DISCOUNT
// ============================================================

$discount = 0;


// ============================================================
// GRAND TOTAL
// ============================================================

$grand_total =
    $subtotal +
    $delivery_charge -
    $discount;


// ============================================================
// PRODUCT IMAGE HELPER
// ============================================================

function getProductImage($image)
{
    $image =
        trim((string) $image);


    if ($image !== "") {

        /*
         * Full external URL.
         */
        if (
            strpos($image, "http://") === 0 ||
            strpos($image, "https://") === 0
        ) {

            return $image;
        }


        /*
         * If image already contains a folder path,
         * use it directly.
         */
        if (
            strpos($image, "/") !== false ||
            strpos($image, "\\") !== false
        ) {

            return $image;
        }


        /*
         * Otherwise assume it is stored in
         * uploads/products/.
         */
        return "uploads/products/" . $image;
    }


    /*
     * Default image.
     */
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


    <!-- Main CSS -->

    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <!-- Cart CSS -->

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

            <a
                href="marketplace.php"
                class="active-nav"
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
                    <?= (int) $cart_count ?>
                </span>

            </a>


            <a
                href="consumer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">
                    <?= htmlspecialchars($avatar_letter) ?>
                </span>

                <span class="profile-name">
                    <?= htmlspecialchars($consumer_name) ?>
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
                        <?= count($cart_items) ?>
                        product<?= count($cart_items) !== 1 ? "s" : "" ?>
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

                    $available_quantity =
                        (int) $item["available_quantity"];

                    ?>

                    <div class="cart-item">


                        <!-- PRODUCT IMAGE -->

                        <div class="cart-product-image">

                            <img
                                src="<?= htmlspecialchars(
                                    getProductImage(
                                        $item["image"]
                                    )
                                ) ?>"
                                alt="<?= htmlspecialchars(
                                    $item["name"]
                                ) ?>"
                            >

                        </div>


                        <!-- PRODUCT INFORMATION -->

                        <div class="cart-product-info">

                            <span>
                                <?= htmlspecialchars(
                                    $item["category"]
                                ) ?>
                            </span>

                            <h3>
                                <?= htmlspecialchars(
                                    $item["name"]
                                ) ?>
                            </h3>

                            <p>
                                👨‍🌾
                                <?= htmlspecialchars(
                                    $item["farmer_name"]
                                ) ?>
                            </p>

                            <?php if (!empty($item["location"])): ?>

                                <p>
                                    📍
                                    <?= htmlspecialchars(
                                        $item["location"]
                                    ) ?>
                                </p>

                            <?php endif; ?>

                            <small>
                                ৳<?= number_format(
                                    $item_price,
                                    2
                                ) ?>
                                /
                                <?= htmlspecialchars(
                                    $item["unit"]
                                ) ?>
                            </small>


                            <?php if (
                                $item["status"] !== "available" ||
                                $available_quantity <= 0
                            ): ?>

                                <small
                                    style="
                                        display:block;
                                        color:#d84c4c;
                                        margin-top:5px;
                                    "
                                >
                                    Product currently unavailable
                                </small>

                            <?php endif; ?>

                        </div>


                        <!-- QUANTITY -->

                        <div class="cart-quantity">

                            <button
                                type="button"
                                onclick="changeQuantity(
                                    <?= (int) $item["cart_item_id"] ?>,
                                    -1
                                )"
                                aria-label="Decrease quantity"
                            >
                                −
                            </button>


                            <span>
                                <?= rtrim(
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
                                ) ?>
                            </span>


                            <button
                                type="button"
                                onclick="changeQuantity(
                                    <?= (int) $item["cart_item_id"] ?>,
                                    1
                                )"
                                aria-label="Increase quantity"
                                <?= (
                                    $item["status"] !== "available" ||
                                    $available_quantity <= $item_quantity
                                ) ? "disabled" : "" ?>
                            >
                                +
                            </button>

                        </div>


                        <!-- ITEM PRICE -->

                        <div class="cart-price">

                            <strong>
                                ৳<?= number_format(
                                    $item_total,
                                    2
                                ) ?>
                            </strong>

                            <span>
                                <?= number_format(
                                    $item_quantity,
                                    2
                                ) ?>
                                ×
                                ৳<?= number_format(
                                    $item_price,
                                    2
                                ) ?>
                            </span>

                        </div>


                        <!-- REMOVE -->

                        <button
                            type="button"
                            class="remove-item"
                            title="Remove item"
                            aria-label="Remove <?= htmlspecialchars(
                                $item["name"]
                            ) ?>"
                            onclick="removeCartItem(
                                <?= (int) $item["cart_item_id"] ?>
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
                    ৳<?= number_format(
                        $subtotal,
                        2
                    ) ?>
                </strong>

            </div>


            <div class="summary-row">

                <span>
                    Delivery
                </span>

                <strong>
                    ৳<?= number_format(
                        $delivery_charge,
                        2
                    ) ?>
                </strong>

            </div>


            <?php if ($discount > 0): ?>

                <div class="summary-row">

                    <span>
                        Discount
                    </span>

                    <strong class="discount">
                        −৳<?= number_format(
                            $discount,
                            2
                        ) ?>
                    </strong>

                </div>

            <?php endif; ?>


            <div class="summary-divider"></div>


            <div class="summary-total">

                <span>
                    Total
                </span>

                <strong>
                    ৳<?= number_format(
                        $grand_total,
                        2
                    ) ?>
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
                    style="
                        opacity:0.5;
                        cursor:not-allowed;
                    "
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


/*
 * ============================================================
 * CHANGE QUANTITY
 * ============================================================
 */

function changeQuantity(cartItemId, change) {

    if (!cartItemId) {
        return;
    }


    /*
     * Disable interaction while request is processing.
     */
    const buttons =
        document.querySelectorAll(
            ".cart-quantity button"
        );


    buttons.forEach(function(button) {
        button.disabled = true;
    });


    const formData =
        new FormData();


    formData.append(
        "action",
        "update_quantity"
    );


    formData.append(
        "cart_item_id",
        cartItemId
    );


    formData.append(
        "change",
        change
    );


    fetch("cart.php", {
        method: "POST",
        body: formData
    })

    .then(function(response) {

        if (!response.ok) {
            throw new Error(
                "Server error: " +
                response.status
            );
        }

        return response.json();
    })

    .then(function(data) {

        if (data.success) {

            /*
             * Reload cart so all calculations,
             * quantities and cart count update.
             */
            window.location.reload();

        } else {

            alert(
                "Unable to update cart."
            );

            buttons.forEach(function(button) {
                button.disabled = false;
            });
        }
    })

    .catch(function(error) {

        console.error(error);

        alert(
            "Unable to update cart. Please try again."
        );

        buttons.forEach(function(button) {
            button.disabled = false;
        });
    });

}


/*
 * ============================================================
 * REMOVE CART ITEM
 * ============================================================
 */

function removeCartItem(cartItemId) {

    if (!cartItemId) {
        return;
    }


    if (
        !confirm(
            "Remove this product from your cart?"
        )
    ) {

        return;
    }


    const formData =
        new FormData();


    formData.append(
        "action",
        "remove_item"
    );


    formData.append(
        "cart_item_id",
        cartItemId
    );


    fetch("cart.php", {
        method: "POST",
        body: formData
    })

    .then(function(response) {

        if (!response.ok) {
            throw new Error(
                "Server error: " +
                response.status
            );
        }

        return response.json();
    })

    .then(function(data) {

        if (data.success) {

            window.location.reload();

        } else {

            alert(
                "Unable to remove item."
            );
        }
    })

    .catch(function(error) {

        console.error(error);

        alert(
            "Unable to remove item. Please try again."
        );
    });

}

</script>


</body>

</html>