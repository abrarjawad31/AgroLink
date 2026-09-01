<?php

// ============================================================
// CHECKOUT PAGE
// ============================================================

require_once "auth.php";
require_once "db.php";

// Only logged-in consumers can access checkout
requireConsumer();


// ============================================================
// CURRENT CONSUMER
// ============================================================

$consumer_id = (int) $_SESSION["user_id"];


// ============================================================
// HELPER
// ============================================================

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}


// ============================================================
// CONSTANTS
// ============================================================

$delivery_fee = 60.00;
$discount = 0.00;


// ============================================================
// SUCCESS / ERROR MESSAGE
// ============================================================

$error_message = "";


// ============================================================
// GET CONSUMER INFORMATION
// ============================================================

$consumer = [
    "name" => "",
    "email" => "",
    "phone" => "",
    "address" => ""
];

$user_stmt = $conn->prepare("
    SELECT
        name,
        email,
        phone,
        address
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

        $consumer = $user_row;

    }

    $user_stmt->close();
}


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

    $cart_stmt->bind_param("i", $consumer_id);

    $cart_stmt->execute();

    $cart_result = $cart_stmt->get_result();

    if ($cart_row = $cart_result->fetch_assoc()) {

        $cart_id = (int) $cart_row["id"];

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
            p.price,
            p.unit,
            p.quantity AS stock_quantity,
            p.image,
            p.status

        FROM cart_items ci

        INNER JOIN products p
            ON p.id = ci.product_id

        WHERE ci.cart_id = ?

        ORDER BY ci.id ASC
    ");

    if ($items_stmt) {

        $items_stmt->bind_param("i", $cart_id);

        $items_stmt->execute();

        $items_result = $items_stmt->get_result();

        while ($item = $items_result->fetch_assoc()) {

            $item["cart_quantity"] = (float) $item["cart_quantity"];
            $item["price"] = (float) $item["price"];
            $item["stock_quantity"] = (float) $item["stock_quantity"];

            $item["subtotal"] =
                $item["cart_quantity"] * $item["price"];

            $cart_items[] = $item;
        }

        $items_stmt->close();
    }
}


// ============================================================
// CHECK EMPTY CART
// ============================================================

if (empty($cart_items)) {

    header("Location: cart.php");

    exit;
}


// ============================================================
// CALCULATE SUBTOTAL
// ============================================================

$subtotal = 0.00;

foreach ($cart_items as $item) {

    $subtotal += $item["subtotal"];
}


// ============================================================
// TOTAL
// ============================================================

$total_amount =
    $subtotal
    + $delivery_fee
    - $discount;


// ============================================================
// FORM DEFAULT VALUES
// ============================================================

$first_name = "";
$last_name = "";

$phone = $consumer["phone"] ?? "";
$email = $consumer["email"] ?? "";

$address = $consumer["address"] ?? "";

$district = "";
$postal = "";
$notes = "";

$payment_method = "Cash on Delivery";


// ============================================================
// PLACE ORDER
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {


    // --------------------------------------------------------
    // GET FORM DATA
    // --------------------------------------------------------

    $first_name = trim($_POST["first_name"] ?? "");
    $last_name = trim($_POST["last_name"] ?? "");

    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");

    $address = trim($_POST["address"] ?? "");

    $district = trim($_POST["district"] ?? "");
    $postal = trim($_POST["postal"] ?? "");

    $notes = trim($_POST["notes"] ?? "");

    $payment_method = trim(
        $_POST["payment_method"] ?? "Cash on Delivery"
    );


    // --------------------------------------------------------
    // VALIDATE PAYMENT METHOD
    // --------------------------------------------------------

    $allowed_payment_methods = [
        "Cash on Delivery",
        "Mobile Banking",
        "Card Payment"
    ];

    if (!in_array(
        $payment_method,
        $allowed_payment_methods,
        true
    )) {

        $payment_method = "Cash on Delivery";
    }


    // --------------------------------------------------------
    // VALIDATE REQUIRED FIELDS
    // --------------------------------------------------------

    if (
        $first_name === "" ||
        $last_name === "" ||
        $phone === "" ||
        $email === "" ||
        $address === "" ||
        $district === ""
    ) {

        $error_message =
            "Please fill in all required delivery information.";

    } else {


        // ----------------------------------------------------
        // BUILD DELIVERY ADDRESS
        // ----------------------------------------------------

        $delivery_address = $address;

        if ($district !== "") {

            $delivery_address .=
                ", " . $district;
        }

        if ($postal !== "") {

            $delivery_address .=
                " - " . $postal;
        }

        if ($notes !== "") {

            $delivery_address .=
                "\nOrder Notes: " . $notes;
        }


        // ----------------------------------------------------
        // BEGIN TRANSACTION
        // ----------------------------------------------------

        $conn->begin_transaction();

        try {


            // =================================================
            // RELOAD CART INSIDE TRANSACTION
            // =================================================

            $cart_check_stmt = $conn->prepare("
                SELECT id
                FROM cart
                WHERE consumer_id = ?
                LIMIT 1
                FOR UPDATE
            ");

            if (!$cart_check_stmt) {

                throw new Exception(
                    "Unable to access cart."
                );
            }

            $cart_check_stmt->bind_param(
                "i",
                $consumer_id
            );

            $cart_check_stmt->execute();

            $cart_check_result =
                $cart_check_stmt->get_result();

            $cart_check_row =
                $cart_check_result->fetch_assoc();

            $cart_check_stmt->close();


            if (!$cart_check_row) {

                throw new Exception(
                    "Your cart is empty."
                );
            }


            $transaction_cart_id =
                (int) $cart_check_row["id"];


            // =================================================
            // GET FRESH CART ITEMS
            // =================================================

            $transaction_items = [];

            $transaction_items_stmt =
                $conn->prepare("
                    SELECT
                        ci.product_id,
                        ci.quantity,

                        p.name,
                        p.price,
                        p.unit,
                        p.quantity AS stock_quantity,
                        p.status

                    FROM cart_items ci

                    INNER JOIN products p
                        ON p.id = ci.product_id

                    WHERE ci.cart_id = ?

                    FOR UPDATE
                ");

            if (!$transaction_items_stmt) {

                throw new Exception(
                    "Unable to load cart items."
                );
            }

            $transaction_items_stmt->bind_param(
                "i",
                $transaction_cart_id
            );

            $transaction_items_stmt->execute();

            $transaction_items_result =
                $transaction_items_stmt->get_result();

            while (
                $transaction_item =
                $transaction_items_result->fetch_assoc()
            ) {

                $transaction_items[] =
                    $transaction_item;
            }

            $transaction_items_stmt->close();


            // =================================================
            // CHECK CART
            // =================================================

            if (empty($transaction_items)) {

                throw new Exception(
                    "Your cart is empty."
                );
            }


            // =================================================
            // RE-CALCULATE SUBTOTAL
            // =================================================

            $transaction_subtotal = 0.00;


            foreach ($transaction_items as &$transaction_item) {


                $item_quantity =
                    (float) $transaction_item["quantity"];

                $item_price =
                    (float) $transaction_item["price"];

                $stock_quantity =
                    (float) $transaction_item["stock_quantity"];

                $product_status =
                    $transaction_item["status"];


                // ---------------------------------------------
                // CHECK QUANTITY
                // ---------------------------------------------

                if ($item_quantity <= 0) {

                    throw new Exception(
                        "Invalid quantity for " .
                        $transaction_item["name"] . "."
                    );
                }


                // ---------------------------------------------
                // CHECK PRODUCT STATUS
                // ---------------------------------------------

                if ($product_status !== "available") {

                    throw new Exception(
                        $transaction_item["name"] .
                        " is currently unavailable."
                    );
                }


                // ---------------------------------------------
                // CHECK STOCK
                // ---------------------------------------------

                if ($item_quantity > $stock_quantity) {

                    throw new Exception(
                        "Not enough stock available for " .
                        $transaction_item["name"] .
                        ". Available: " .
                        rtrim(
                            rtrim(
                                number_format(
                                    $stock_quantity,
                                    2,
                                    ".",
                                    ""
                                ),
                                "0"
                            ),
                            "."
                        ) .
                        " " .
                        $transaction_item["unit"] .
                        "."
                    );
                }


                // ---------------------------------------------
                // CALCULATE ITEM SUBTOTAL
                // ---------------------------------------------

                $item_subtotal =
                    $item_quantity * $item_price;


                $transaction_item["item_subtotal"] =
                    $item_subtotal;


                $transaction_subtotal +=
                    $item_subtotal;
            }

            unset($transaction_item);


            // =================================================
            // CALCULATE FINAL TOTAL
            // =================================================

            $transaction_total =
                $transaction_subtotal
                + $delivery_fee
                - $discount;


            // =================================================
            // CREATE ORDER
            // =================================================

            $order_stmt = $conn->prepare("
                INSERT INTO orders (
                    consumer_id,
                    total_amount,
                    status,
                    payment_method,
                    delivery_address,
                    phone
                )
                VALUES (
                    ?,
                    ?,
                    'pending',
                    ?,
                    ?,
                    ?
                )
            ");

            if (!$order_stmt) {

                throw new Exception(
                    "Unable to create order."
                );
            }


            $order_stmt->bind_param(
                "idsss",
                $consumer_id,
                $transaction_total,
                $payment_method,
                $delivery_address,
                $phone
            );


            if (!$order_stmt->execute()) {

                $order_stmt->close();

                throw new Exception(
                    "Unable to place order."
                );
            }


            $order_id =
                (int) $conn->insert_id;


            $order_stmt->close();


            // =================================================
            // INSERT ORDER ITEMS
            // =================================================

            $order_item_stmt = $conn->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    product_name,
                    price,
                    quantity,
                    subtotal
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


            if (!$order_item_stmt) {

                throw new Exception(
                    "Unable to create order items."
                );
            }


            // =================================================
            // UPDATE PRODUCT STOCK STATEMENT
            // =================================================

            $stock_stmt = $conn->prepare("
                UPDATE products

                SET
                    quantity = quantity - ?,

                    status = CASE
                        WHEN quantity - ? <= 0
                        THEN 'out_of_stock'
                        ELSE status
                    END

                WHERE id = ?

                  AND quantity >= ?
            ");


            if (!$stock_stmt) {

                $order_item_stmt->close();

                throw new Exception(
                    "Unable to update product stock."
                );
            }


            // =================================================
            // PROCESS EACH ITEM
            // =================================================

            foreach ($transaction_items as $transaction_item) {


                $product_id =
                    (int) $transaction_item["product_id"];

                $product_name =
                    $transaction_item["name"];

                $price =
                    (float) $transaction_item["price"];

                $quantity =
                    (float) $transaction_item["quantity"];

                $item_subtotal =
                    (float) $transaction_item["item_subtotal"];


                // ------------------------------------------------
                // INSERT ORDER ITEM
                // ------------------------------------------------
                
                $order_item_stmt->bind_param(
                    "iisddd",
                    $order_id,
                    $product_id,
                    $product_name,
                    $price,
                    $quantity,
                    $item_subtotal
                );


                if (!$order_item_stmt->execute()) {

                    throw new Exception(
                        "Unable to save order item."
                    );
                }


                // ------------------------------------------------
                // UPDATE PRODUCT STOCK
                // ------------------------------------------------

                $stock_stmt->bind_param(
                    "ddid",
                    $quantity,
                    $quantity,
                    $product_id,
                    $quantity
                );


                if (!$stock_stmt->execute()) {

                    throw new Exception(
                        "Unable to update stock for " .
                        $product_name . "."
                    );
                }


                if ($stock_stmt->affected_rows === 0) {

                    throw new Exception(
                        "Stock changed for " .
                        $product_name .
                        ". Please try again."
                    );
                }
            }


            $order_item_stmt->close();

            $stock_stmt->close();


            // =================================================
            // CLEAR CART
            // =================================================

            $clear_cart_stmt = $conn->prepare("
                DELETE FROM cart_items
                WHERE cart_id = ?
            ");


            if (!$clear_cart_stmt) {

                throw new Exception(
                    "Unable to clear cart."
                );
            }


            $clear_cart_stmt->bind_param(
                "i",
                $transaction_cart_id
            );


            if (!$clear_cart_stmt->execute()) {

                $clear_cart_stmt->close();

                throw new Exception(
                    "Unable to clear cart."
                );
            }


            $clear_cart_stmt->close();


            // =================================================
            // COMMIT
            // =================================================

            $conn->commit();


            // =================================================
            // REDIRECT TO ORDER CONFIRMATION
            // =================================================

            header(
                "Location: order-confirmation.php?order_id=" .
                $order_id
            );

            exit;


        } catch (Throwable $exception) {


            // ------------------------------------------------
            // ROLLBACK
            // ------------------------------------------------

            $conn->rollback();


            $error_message =
                $exception->getMessage();
        }
    }
}


// ============================================================
// DISPLAY VALUES
// ============================================================

$display_subtotal = $subtotal;
$display_total = $total_amount;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Checkout | AgroLink</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/checkout.css"
    >

    <link rel="preconnect"
        href="https://fonts.googleapis.com">

    <link rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

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

            <a href="consumer-demands.php">
                My Demands
            </a>

        </nav>


        <!-- NAV BUTTONS -->

        <div class="nav-buttons">

            <a
                href="logout.php"
                class="login-btn"
            >
                Logout
            </a>

        </div>


    </div>

</header>


<!-- =========================================================
     CHECKOUT HERO
========================================================= -->

<section class="checkout-hero">

    <div class="container">

        <span class="section-tag">
            SECURE CHECKOUT
        </span>

        <h1>
            Complete Your <span>Order</span>
        </h1>

        <p>
            Provide your delivery and payment information below.
        </p>

    </div>

</section>


<!-- =========================================================
     ERROR MESSAGE
========================================================= -->

<?php if ($error_message !== ""): ?>

<section style="padding-top: 20px;">

    <div class="container">

        <div
            style="
                padding: 14px 18px;
                border: 1px solid #e3b8b8;
                background: #fff5f5;
                color: #a33;
                border-radius: 7px;
                font-size: 12px;
            "
        >

            <?php echo e($error_message); ?>

        </div>

    </div>

</section>

<?php endif; ?>


<!-- =========================================================
     CHECKOUT
========================================================= -->

<main class="checkout-section">

    <div class="container checkout-layout">


        <!-- =================================================
             LEFT SIDE
        ================================================= -->

        <div class="checkout-form">


            <!-- =================================================
                 DELIVERY INFORMATION
            ================================================= -->

            <div class="checkout-card">

                <div class="checkout-card-heading">

                    <div class="checkout-number">
                        01
                    </div>

                    <div>

                        <h2>
                            Delivery Information
                        </h2>

                        <p>
                            Where should we deliver your order?
                        </p>

                    </div>

                </div>


                <form
                    method="POST"
                    action=""
                    id="checkout-form"
                >


                    <!-- FIRST / LAST NAME -->

                    <div class="form-row">

                        <div class="form-group">

                            <label for="first-name">
                                First Name *
                            </label>

                            <input
                                type="text"
                                id="first-name"
                                name="first_name"
                                value="<?php echo e($first_name); ?>"
                                placeholder="Enter your first name"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="last-name">
                                Last Name *
                            </label>

                            <input
                                type="text"
                                id="last-name"
                                name="last_name"
                                value="<?php echo e($last_name); ?>"
                                placeholder="Enter your last name"
                                required
                            >

                        </div>

                    </div>


                    <!-- PHONE / EMAIL -->

                    <div class="form-row">

                        <div class="form-group">

                            <label for="phone">
                                Phone Number *
                            </label>

                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                value="<?php echo e($phone); ?>"
                                placeholder="01XXXXXXXXX"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label for="email">
                                Email Address *
                            </label>

                            <input
                                type="email"
                                id="email"
                                name="email"
                                value="<?php echo e($email); ?>"
                                placeholder="example@email.com"
                                required
                            >

                        </div>

                    </div>


                    <!-- ADDRESS -->

                    <div class="form-group">

                        <label for="address">
                            Delivery Address *
                        </label>

                        <input
                            type="text"
                            id="address"
                            name="address"
                            value="<?php echo e($address); ?>"
                            placeholder="House / Road / Area"
                            required
                        >

                    </div>


                    <!-- DISTRICT / POSTAL -->

                    <div class="form-row">

                        <div class="form-group">

                            <label for="district">
                                District *
                            </label>

                            <select
                                id="district"
                                name="district"
                                required
                            >

                                <option value="">
                                    Select District
                                </option>

                                <option
                                    value="Dhaka"
                                    <?php
                                    echo $district === "Dhaka"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Dhaka
                                </option>

                                <option
                                    value="Gazipur"
                                    <?php
                                    echo $district === "Gazipur"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Gazipur
                                </option>

                                <option
                                    value="Rajshahi"
                                    <?php
                                    echo $district === "Rajshahi"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Rajshahi
                                </option>

                                <option
                                    value="Chattogram"
                                    <?php
                                    echo $district === "Chattogram"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Chattogram
                                </option>

                                <option
                                    value="Mymensingh"
                                    <?php
                                    echo $district === "Mymensingh"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Mymensingh
                                </option>

                                <option
                                    value="Bogura"
                                    <?php
                                    echo $district === "Bogura"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Bogura
                                </option>

                                <option
                                    value="Rangpur"
                                    <?php
                                    echo $district === "Rangpur"
                                        ? "selected"
                                        : "";
                                    ?>
                                >
                                    Rangpur
                                </option>

                            </select>

                        </div>


                        <div class="form-group">

                            <label for="postal">
                                Postal Code
                            </label>

                            <input
                                type="text"
                                id="postal"
                                name="postal"
                                value="<?php echo e($postal); ?>"
                                placeholder="Enter postal code"
                            >

                        </div>

                    </div>


                    <!-- NOTES -->

                    <div class="form-group">

                        <label for="notes">

                            Order Notes

                            <span>
                                (Optional)
                            </span>

                        </label>

                        <textarea
                            id="notes"
                            name="notes"
                            rows="3"
                            placeholder="Any special delivery instructions?"
                        ><?php echo e($notes); ?></textarea>

                    </div>


                    <!-- =================================================
                         PAYMENT METHOD
                    ================================================= -->

                    <div class="checkout-card" style="padding: 0; border: none; margin-bottom: 0;">

                        <div class="checkout-card-heading">

                            <div class="checkout-number">
                                02
                            </div>

                            <div>

                                <h2>
                                    Payment Method
                                </h2>

                                <p>
                                    Choose how you would like to pay.
                                </p>

                            </div>

                        </div>


                        <div class="payment-options">


                            <!-- CASH -->

                            <label
                                class="payment-option <?php echo $payment_method === "Cash on Delivery" ? "active-payment" : ""; ?>"
                            >

                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="Cash on Delivery"
                                    <?php
                                    echo $payment_method === "Cash on Delivery"
                                        ? "checked"
                                        : "";
                                    ?>
                                >

                                <div class="payment-icon">
                                    💵
                                </div>

                                <div>

                                    <strong>
                                        Cash on Delivery
                                    </strong>

                                    <span>
                                        Pay when your order arrives
                                    </span>

                                </div>

                            </label>


                            <!-- MOBILE BANKING -->

                            <label
                                class="payment-option <?php echo $payment_method === "Mobile Banking" ? "active-payment" : ""; ?>"
                            >

                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="Mobile Banking"
                                    <?php
                                    echo $payment_method === "Mobile Banking"
                                        ? "checked"
                                        : "";
                                    ?>
                                >

                                <div class="payment-icon">
                                    📱
                                </div>

                                <div>

                                    <strong>
                                        Mobile Banking
                                    </strong>

                                    <span>
                                        bKash / Nagad / Rocket
                                    </span>

                                </div>

                            </label>


                            <!-- CARD -->

                            <label
                                class="payment-option <?php echo $payment_method === "Card Payment" ? "active-payment" : ""; ?>"
                            >

                                <input
                                    type="radio"
                                    name="payment_method"
                                    value="Card Payment"
                                    <?php
                                    echo $payment_method === "Card Payment"
                                        ? "checked"
                                        : "";
                                    ?>
                                >

                                <div class="payment-icon">
                                    💳
                                </div>

                                <div>

                                    <strong>
                                        Card Payment
                                    </strong>

                                    <span>
                                        Visa / Mastercard
                                    </span>

                                </div>

                            </label>


                        </div>

                    </div>


                    <!-- =================================================
                         PAYMENT DETAILS
                    ================================================= -->

                    <div class="checkout-card payment-details">

                        <div class="checkout-card-heading">

                            <div class="checkout-number">
                                03
                            </div>

                            <div>

                                <h2>
                                    Payment Details
                                </h2>

                                <p>
                                    Payment details will be collected securely.
                                </p>

                            </div>

                        </div>


                        <div class="payment-placeholder">

                            <div class="payment-placeholder-icon">
                                🔒
                            </div>

                            <div>

                                <strong>
                                    Secure Payment
                                </strong>

                                <p>
                                    Your payment information is protected.
                                    For this academic project, payment
                                    processing will be simulated.
                                </p>

                            </div>

                        </div>

                    </div>


                </form>

            </div>


        </div>


        <!-- =================================================
             RIGHT SIDE
        ================================================= -->

        <aside class="checkout-summary">


            <div class="checkout-summary-card">

                <h2>
                    Your Order
                </h2>


                <!-- =================================================
                     PRODUCTS
                ================================================= -->

                <?php foreach ($cart_items as $item): ?>

                <div class="checkout-product">


                    <?php

                    $product_image =
                        trim((string) $item["image"]);

                    if ($product_image === "") {

                        $product_image =
                            "https://images.unsplash.com/photo-1546094096-0df4bcaaa337?auto=format&fit=crop&w=200&q=80";
                    }

                    ?>


                    <img
                        src="<?php echo e($product_image); ?>"
                        alt="<?php echo e($item["name"]); ?>"
                    >


                    <div>

                        <strong>
                            <?php echo e($item["name"]); ?>
                        </strong>

                        <span>

                            <?php
                            echo rtrim(
                                rtrim(
                                    number_format(
                                        (float) $item["cart_quantity"],
                                        2,
                                        ".",
                                        ""
                                    ),
                                    "0"
                                ),
                                "."
                            );
                            ?>

                            <?php echo e($item["unit"]); ?>

                            × ৳<?php
                            echo number_format(
                                (float) $item["price"],
                                2
                            );
                            ?>

                        </span>

                    </div>


                    <b>
                        ৳<?php
                        echo number_format(
                            (float) $item["subtotal"],
                            2
                        );
                        ?>
                    </b>


                </div>

                <?php endforeach; ?>


                <div class="checkout-divider"></div>


                <!-- SUBTOTAL -->

                <div class="checkout-summary-row">

                    <span>
                        Subtotal
                    </span>

                    <strong>
                        ৳<?php
                        echo number_format(
                            $display_subtotal,
                            2
                        );
                        ?>
                    </strong>

                </div>


                <!-- DELIVERY -->

                <div class="checkout-summary-row">

                    <span>
                        Delivery Fee
                    </span>

                    <strong>
                        ৳<?php
                        echo number_format(
                            $delivery_fee,
                            2
                        );
                        ?>
                    </strong>

                </div>


                <!-- DISCOUNT -->

                <div class="checkout-summary-row">

                    <span>
                        Discount
                    </span>

                    <strong class="discount">
                        − ৳<?php
                        echo number_format(
                            $discount,
                            2
                        );
                        ?>
                    </strong>

                </div>


                <div class="checkout-divider"></div>


                <!-- TOTAL -->

                <div class="checkout-total">

                    <span>
                        Total
                    </span>

                    <strong>
                        ৳<?php
                        echo number_format(
                            $display_total,
                            2
                        );
                        ?>
                    </strong>

                </div>


                <!-- PLACE ORDER -->

                <button
                    type="submit"
                    form="checkout-form"
                    class="place-order-btn"
                >
                    Place Order →
                </button>


                <div class="checkout-security">

                    🔒 Secure & Protected

                    <p>
                        Your information is kept private
                        and secure.
                    </p>

                </div>


            </div>


            <a
                href="cart.php"
                class="back-cart"
            >
                ← Back to Cart
            </a>


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

            <a href="marketplace.php?category=vegetables">
                Vegetables
            </a>

            <a href="marketplace.php?category=fruits">
                Fruits
            </a>

            <a href="marketplace.php?category=grains">
                Grains
            </a>

            <a href="marketplace.php?category=dairy">
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


    <!-- FOOTER BOTTOM -->

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
     PAYMENT OPTION UI
========================================================= -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const paymentOptions =
            document.querySelectorAll(
                ".payment-option"
            );


        paymentOptions.forEach(
            function (option) {

                const radio =
                    option.querySelector(
                        "input[type='radio']"
                    );


                radio.addEventListener(
                    "change",
                    function () {

                        paymentOptions.forEach(
                            function (item) {

                                item.classList.remove(
                                    "active-payment"
                                );

                            }
                        );


                        if (radio.checked) {

                            option.classList.add(
                                "active-payment"
                            );

                        }

                    }
                );

            }
        );

    }
);

</script>


</body>

</html>