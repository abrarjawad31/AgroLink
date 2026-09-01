<?php

// ============================================================
// AUTHENTICATION & DATABASE
// ============================================================

require_once "auth.php";
require_once "db.php";

// Only logged-in consumers can access this page
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
// GET ORDER ID
// ============================================================

// Normally checkout.php should redirect to:
// order-confirmation.php?order_id=123
//
// Session fallback is also supported.

$order_id = 0;

if (isset($_GET["order_id"]) && is_numeric($_GET["order_id"])) {
    $order_id = (int) $_GET["order_id"];
} elseif (
    isset($_SESSION["last_order_id"]) &&
    is_numeric($_SESSION["last_order_id"])
) {
    $order_id = (int) $_SESSION["last_order_id"];
}


// ============================================================
// VALIDATE ORDER ID
// ============================================================

if ($order_id <= 0) {
    header("Location: my-orders.php");
    exit();
}


// ============================================================
// GET ORDER INFORMATION
// ============================================================

$order = null;

$stmt = $conn->prepare(
    "SELECT
        o.id,
        o.consumer_id,
        o.total_amount,
        o.status,
        o.payment_method,
        o.delivery_address,
        o.phone,
        o.created_at,

        u.name AS consumer_name,
        u.email AS consumer_email

     FROM orders o

     INNER JOIN users u
        ON o.consumer_id = u.id

     WHERE o.id = ?
       AND o.consumer_id = ?

     LIMIT 1"
);

if ($stmt) {

    $stmt->bind_param(
        "ii",
        $order_id,
        $consumer_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
    }

    $stmt->close();
}


// ============================================================
// ORDER NOT FOUND
// ============================================================

if (!$order) {
    header("Location: my-orders.php");
    exit();
}


// ============================================================
// GET ORDER ITEMS
// ============================================================

$order_items = [];

$stmt = $conn->prepare(
    "SELECT
        oi.id,
        oi.product_id,
        oi.product_name,
        oi.price,
        oi.quantity,
        oi.subtotal,
        p.image,
        p.unit

     FROM order_items oi

     LEFT JOIN products p
        ON oi.product_id = p.id

     WHERE oi.order_id = ?

     ORDER BY oi.id ASC"
);

if ($stmt) {

    $stmt->bind_param(
        "i",
        $order_id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $order_items[] = $row;
    }

    $stmt->close();
}


// ============================================================
// IF ORDER HAS NO ITEMS
// ============================================================

if (count($order_items) === 0) {
    header("Location: my-orders.php");
    exit();
}


// ============================================================
// CALCULATE ORDER TOTALS
// ============================================================

$subtotal = 0;
$total_quantity = 0;

foreach ($order_items as $item) {

    $subtotal += (float) $item["subtotal"];

    $total_quantity += (float) $item["quantity"];
}


$total_amount = (float) $order["total_amount"];

// Your database does not have a separate delivery_fee column.
// Therefore the difference between total_amount and product
// subtotal is treated as the delivery fee.

$delivery_fee = $total_amount - $subtotal;

if ($delivery_fee < 0) {
    $delivery_fee = 0;
}


// No discount column exists in the current database.
$discount = 0;


// ============================================================
// ORDER DISPLAY VALUES
// ============================================================

$order_status = strtolower(
    trim($order["status"] ?? "pending")
);

$status_labels = [
    "pending"    => "Pending",
    "confirmed"  => "Confirmed",
    "processing" => "Processing",
    "shipped"    => "Shipped",
    "delivered"  => "Delivered",
    "cancelled"  => "Cancelled"
];

$status_label = $status_labels[$order_status]
    ?? ucfirst($order_status);


// ============================================================
// ORDER STATUS CSS CLASS
// ============================================================

$status_class = "processing";

switch ($order_status) {

    case "pending":
        $status_class = "pending";
        break;

    case "confirmed":
        $status_class = "confirmed";
        break;

    case "processing":
        $status_class = "processing";
        break;

    case "shipped":
        $status_class = "shipped";
        break;

    case "delivered":
        $status_class = "completed";
        break;

    case "cancelled":
        $status_class = "cancelled";
        break;
}


// ============================================================
// ORDER ID DISPLAY
// ============================================================

$display_order_id =
    "#AGL-" .
    date("Y", strtotime($order["created_at"])) .
    "-" .
    str_pad(
        $order_id,
        5,
        "0",
        STR_PAD_LEFT
    );


// ============================================================
// ORDER DATE
// ============================================================

$order_date = date(
    "F d, Y",
    strtotime($order["created_at"])
);


// ============================================================
// CONSUMER NAME
// ============================================================

$consumer_name = $order["consumer_name"] ?? "Consumer";


// ============================================================
// DELIVERY ADDRESS
// ============================================================

$delivery_address = trim(
    $order["delivery_address"] ?? ""
);

if ($delivery_address === "") {
    $delivery_address = "Delivery address not provided.";
}


// ============================================================
// PHONE
// ============================================================

$phone = trim(
    $order["phone"] ?? ""
);

if ($phone === "") {
    $phone = "Phone number not provided.";
}


// ============================================================
// ESTIMATED DELIVERY
// ============================================================

if ($order_status === "delivered") {

    $estimated_delivery = "Delivered";

} elseif ($order_status === "cancelled") {

    $estimated_delivery = "Cancelled";

} else {

    $estimated_delivery = "2–4 Days";
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
        Order Confirmed | AgroLink
    </title>


    <!-- Common CSS -->

    <link
        rel="stylesheet"
        href="css/style.css"
    >


    <!-- Page CSS -->

    <link
        rel="stylesheet"
        href="css/order-confirmation.css"
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

                <a
                    href="my-orders.php"
                    class="active"
                >
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

                </a>


                <a
                    href="consumer-profile.php"
                    class="profile-link"
                >

                    <span class="profile-avatar">
                        <?php
                        echo e(
                            strtoupper(
                                substr(
                                    $consumer_name,
                                    0,
                                    1
                                )
                            )
                        );
                        ?>
                    </span>

                    <span class="profile-name">
                        <?php
                        echo e($consumer_name);
                        ?>
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



    <!-- ================= CONFIRMATION ================= -->

    <main class="confirmation-section">

        <div class="container confirmation-container">


            <!-- SUCCESS MESSAGE -->

            <?php if ($order_status !== "cancelled"): ?>

                <div class="success-icon">
                    ✓
                </div>

            <?php else: ?>

                <div
                    class="success-icon"
                    style="
                        background: #b42318;
                    "
                >
                    ×
                </div>

            <?php endif; ?>


            <span class="confirmation-tag">

                <?php if ($order_status === "cancelled"): ?>

                    ORDER CANCELLED

                <?php else: ?>

                    ORDER PLACED SUCCESSFULLY

                <?php endif; ?>

            </span>


            <h1>

                <?php if ($order_status === "cancelled"): ?>

                    Order <span>Cancelled</span>

                <?php else: ?>

                    Thank You for Your <span>Order!</span>

                <?php endif; ?>

            </h1>


            <p class="confirmation-message">

                <?php if ($order_status === "cancelled"): ?>

                    This order has been cancelled.

                <?php else: ?>

                    Your order has been successfully placed.
                    We'll notify you when your order is on its way.

                <?php endif; ?>

            </p>



            <!-- ================= ORDER INFORMATION ================= -->

            <div class="order-info">


                <!-- ORDER ID -->

                <div>

                    <span>
                        ORDER ID
                    </span>

                    <strong>
                        <?php
                        echo e($display_order_id);
                        ?>
                    </strong>

                </div>


                <!-- ORDER DATE -->

                <div>

                    <span>
                        ORDER DATE
                    </span>

                    <strong>
                        <?php
                        echo e($order_date);
                        ?>
                    </strong>

                </div>


                <!-- PAYMENT -->

                <div>

                    <span>
                        PAYMENT
                    </span>

                    <strong>
                        <?php
                        echo e(
                            $order["payment_method"]
                            ?: "Cash on Delivery"
                        );
                        ?>
                    </strong>

                </div>


                <!-- STATUS -->

                <div>

                    <span>
                        STATUS
                    </span>

                    <strong
                        class="order-status"
                        style="
                            <?php
                            if ($order_status === "cancelled") {
                                echo "color:#b42318;";
                            } elseif ($order_status === "delivered") {
                                echo "color:var(--primary);";
                            }
                            ?>
                        "
                    >
                        <?php
                        echo e($status_label);
                        ?>
                    </strong>

                </div>

            </div>



            <!-- ================= ORDER DETAILS ================= -->

            <div class="confirmation-card">


                <!-- CARD HEADER -->

                <div class="confirmation-card-header">

                    <div>

                        <h2>
                            Order Details
                        </h2>

                        <p>

                            <?php
                            echo count($order_items);
                            ?>

                            <?php
                            echo count($order_items) === 1
                                ? "product"
                                : "products";
                            ?>

                        </p>

                    </div>


                    <span
                        class="processing-badge"
                        style="
                            <?php

                            if ($order_status === "cancelled") {

                                echo "
                                    background:#fff1f1;
                                    color:#b42318;
                                ";

                            } elseif ($order_status === "delivered") {

                                echo "
                                    background:var(--primary-light);
                                    color:var(--primary);
                                ";

                            }

                            ?>
                        "
                    >
                        <?php
                        echo e($status_label);
                        ?>
                    </span>

                </div>



                <!-- ================= PRODUCTS ================= -->

                <?php foreach ($order_items as $item): ?>

                    <?php

                    $product_image = trim(
                        $item["image"] ?? ""
                    );

                    ?>

                    <div class="confirmation-product">


                        <!-- PRODUCT IMAGE -->

                        <?php if ($product_image !== ""): ?>

                            <img
                                src="<?php echo e($product_image); ?>"
                                alt="<?php echo e($item["product_name"]); ?>"
                            >

                        <?php else: ?>

                            <div
                                style="
                                    width:60px;
                                    height:60px;
                                    display:flex;
                                    align-items:center;
                                    justify-content:center;
                                    border-radius:6px;
                                    background:var(--primary-light);
                                    font-size:25px;
                                "
                            >
                                🌱
                            </div>

                        <?php endif; ?>


                        <!-- PRODUCT INFORMATION -->

                        <div class="confirmation-product-info">

                            <h3>
                                <?php
                                echo e(
                                    $item["product_name"]
                                );
                                ?>
                            </h3>

                            <span>

                                <?php
                                echo rtrim(
                                    rtrim(
                                        number_format(
                                            (float) $item["quantity"],
                                            2
                                        ),
                                        "0"
                                    ),
                                    "."
                                );
                                ?>

                                <?php
                                echo e(
                                    $item["unit"] ?: "kg"
                                );
                                ?>

                                × ৳<?php
                                echo number_format(
                                    (float) $item["price"],
                                    2
                                );
                                ?>

                            </span>

                        </div>


                        <!-- ITEM TOTAL -->

                        <strong>
                            ৳<?php
                            echo number_format(
                                (float) $item["subtotal"],
                                2
                            );
                            ?>
                        </strong>

                    </div>

                <?php endforeach; ?>



                <!-- ================= TOTAL ================= -->

                <div class="confirmation-divider">
                </div>


                <!-- SUBTOTAL -->

                <div class="confirmation-price-row">

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


                <!-- DELIVERY FEE -->

                <div class="confirmation-price-row">

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

                <div class="confirmation-price-row">

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


                <div class="confirmation-divider">
                </div>


                <!-- TOTAL -->

                <div class="confirmation-total">

                    <span>
                        Order Total
                    </span>

                    <strong>
                        ৳<?php
                        echo number_format(
                            $total_amount,
                            2
                        );
                        ?>
                    </strong>

                </div>

            </div>



            <!-- ================= DELIVERY INFORMATION ================= -->

            <div class="delivery-card">


                <div class="delivery-icon">
                    🚚
                </div>


                <div class="delivery-info">

                    <h2>
                        Delivery Information
                    </h2>

                    <p>
                        Your order will be delivered to:
                    </p>


                    <strong>
                        <?php
                        echo e($consumer_name);
                        ?>
                    </strong>


                    <span>
                        <?php
                        echo e($delivery_address);
                        ?>
                    </span>


                    <span>
                        📞
                        <?php
                        echo e($phone);
                        ?>
                    </span>

                </div>


                <div class="estimated-delivery">

                    <span>
                        <?php
                        if ($order_status === "delivered") {
                            echo "DELIVERY STATUS";
                        } elseif ($order_status === "cancelled") {
                            echo "ORDER STATUS";
                        } else {
                            echo "ESTIMATED DELIVERY";
                        }
                        ?>
                    </span>

                    <strong>
                        <?php
                        echo e($estimated_delivery);
                        ?>
                    </strong>

                </div>

            </div>



            <!-- ================= ACTION BUTTONS ================= -->

            <div class="confirmation-actions">


                <a
                    href="marketplace.php"
                    class="continue-shopping-btn"
                >
                    Continue Shopping
                </a>


                <a
                    href="my-orders.php"
                    class="view-orders-btn"
                >
                    View My Orders
                </a>

            </div>



            <!-- ================= SUPPORT ================= -->

            <p class="confirmation-support">

                Need help with your order?

                <a href="#">
                    Contact AgroLink Support
                </a>

            </p>


        </div>

    </main>



    <!-- ================= FOOTER ================= -->

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

                <a href="marketplace.php">
                    Vegetables
                </a>

                <a href="marketplace.php">
                    Fruits
                </a>

                <a href="marketplace.php">
                    Grains
                </a>

                <a href="marketplace.php">
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

                <a href="#">
                    About Us
                </a>

                <a href="#">
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


</body>

</html>