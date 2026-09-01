<?php
require_once "auth.php";
requireFarmer();

require_once "config.php";

$farmerId = (int) $_SESSION["user_id"];
$farmerName = $_SESSION["user_name"] ?? "Farmer";

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/
function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/*
|--------------------------------------------------------------------------
| Get Order ID
|--------------------------------------------------------------------------
*/
$orderId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($orderId <= 0) {
    header("Location: farmer-orders.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Handle Status Update
|--------------------------------------------------------------------------
*/
$successMessage = "";
$errorMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $newStatus = $_POST["status"] ?? "";

    $validStatuses = [
        "pending",
        "confirmed",
        "processing",
        "shipped",
        "delivered",
        "cancelled"
    ];

    if (!in_array($newStatus, $validStatuses, true)) {

        $errorMessage = "Invalid order status.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Verify that this order contains a product belonging to this farmer
        |--------------------------------------------------------------------------
        */
        $verifySql = "
            SELECT o.id, o.status
            FROM orders o
            INNER JOIN order_items oi
                ON o.id = oi.order_id
            INNER JOIN products p
                ON oi.product_id = p.id
            WHERE o.id = ?
              AND p.farmer_id = ?
            LIMIT 1
        ";

        $verifyStmt = $conn->prepare($verifySql);

        if (!$verifyStmt) {
            $errorMessage = "Database error.";
        } else {

            $verifyStmt->bind_param("ii", $orderId, $farmerId);
            $verifyStmt->execute();

            $verifyResult = $verifyStmt->get_result();
            $verifiedOrder = $verifyResult->fetch_assoc();

            $verifyStmt->close();

            if (!$verifiedOrder) {

                $errorMessage = "You are not authorized to update this order.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Prevent invalid backward status changes
                |--------------------------------------------------------------------------
                */
                $currentStatus = $verifiedOrder["status"];

                $statusOrder = [
                    "pending"    => 1,
                    "confirmed"  => 2,
                    "processing" => 3,
                    "shipped"    => 4,
                    "delivered"  => 5
                ];

                /*
                |--------------------------------------------------------------------------
                | Cancelled orders cannot be changed
                |--------------------------------------------------------------------------
                */
                if ($currentStatus === "cancelled") {

                    $errorMessage = "A cancelled order cannot be updated.";

                /*
                |--------------------------------------------------------------------------
                | Delivered orders cannot be moved backward
                |--------------------------------------------------------------------------
                */
                } elseif (
                    $currentStatus === "delivered" &&
                    $newStatus !== "delivered"
                ) {

                    $errorMessage = "A completed order cannot be moved back to another status.";

                /*
                |--------------------------------------------------------------------------
                | Prevent moving backward
                |--------------------------------------------------------------------------
                */
                } elseif (
                    isset($statusOrder[$currentStatus]) &&
                    isset($statusOrder[$newStatus]) &&
                    $statusOrder[$newStatus] < $statusOrder[$currentStatus]
                ) {

                    $errorMessage = "You cannot move an order back to a previous status.";

                } else {

                    $updateSql = "
                        UPDATE orders
                        SET status = ?, updated_at = NOW()
                        WHERE id = ?
                    ";

                    $updateStmt = $conn->prepare($updateSql);

                    if (!$updateStmt) {

                        $errorMessage = "Unable to update order status.";

                    } else {

                        $updateStmt->bind_param(
                            "si",
                            $newStatus,
                            $orderId
                        );

                        if ($updateStmt->execute()) {

                            $successMessage = "Order status updated successfully.";

                        } else {

                            $errorMessage = "Failed to update order status.";
                        }

                        $updateStmt->close();
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch Order Information
|--------------------------------------------------------------------------
*/
$orderSql = "
    SELECT
        o.id,
        o.consumer_id,
        o.total_amount,
        o.status,
        o.payment_method,
        o.delivery_address,
        o.phone,
        o.created_at,
        o.updated_at,

        u.name AS customer_name,
        u.email AS customer_email,
        u.phone AS customer_phone

    FROM orders o

    INNER JOIN users u
        ON o.consumer_id = u.id

    INNER JOIN order_items oi
        ON o.id = oi.order_id

    INNER JOIN products p
        ON oi.product_id = p.id

    WHERE o.id = ?
      AND p.farmer_id = ?

    LIMIT 1
";

$orderStmt = $conn->prepare($orderSql);

if (!$orderStmt) {
    die("Database error.");
}

$orderStmt->bind_param("ii", $orderId, $farmerId);
$orderStmt->execute();

$orderResult = $orderStmt->get_result();
$order = $orderResult->fetch_assoc();

$orderStmt->close();

if (!$order) {
    header("Location: farmer-orders.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Products Belonging to This Farmer
|--------------------------------------------------------------------------
*/
$itemsSql = "
    SELECT
        oi.id,
        oi.product_id,
        oi.product_name,
        oi.price,
        oi.quantity,
        oi.subtotal,

        p.unit,
        p.image

    FROM order_items oi

    INNER JOIN products p
        ON oi.product_id = p.id

    WHERE oi.order_id = ?
      AND p.farmer_id = ?

    ORDER BY oi.id ASC
";

$itemsStmt = $conn->prepare($itemsSql);

if (!$itemsStmt) {
    die("Database error.");
}

$itemsStmt->bind_param("ii", $orderId, $farmerId);
$itemsStmt->execute();

$itemsResult = $itemsStmt->get_result();

$orderItems = [];

while ($item = $itemsResult->fetch_assoc()) {
    $orderItems[] = $item;
}

$itemsStmt->close();

/*
|--------------------------------------------------------------------------
| Calculate Farmer's Total
|--------------------------------------------------------------------------
*/
$farmerSubtotal = 0;

foreach ($orderItems as $item) {
    $farmerSubtotal += (float)$item["subtotal"];
}

/*
|--------------------------------------------------------------------------
| Status Display Helpers
|--------------------------------------------------------------------------
*/
$status = $order["status"];

$statusLabels = [
    "pending"    => "Pending",
    "confirmed"  => "Confirmed",
    "processing" => "Processing",
    "shipped"    => "Shipped",
    "delivered"  => "Completed",
    "cancelled"  => "Cancelled"
];

$statusClasses = [
    "pending"    => "pending",
    "confirmed"  => "processing",
    "processing" => "processing",
    "shipped"    => "shipped",
    "delivered"  => "completed",
    "cancelled"  => "cancelled"
];

$statusLabel = $statusLabels[$status] ?? ucfirst($status);
$statusClass = $statusClasses[$status] ?? "pending";

/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/
$orderDate = date(
    "d M Y, h:i A",
    strtotime($order["created_at"])
);

$updatedDate = !empty($order["updated_at"])
    ? date("d M Y, h:i A", strtotime($order["updated_at"]))
    : $orderDate;

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Order Details - AgroLink</title>

    <link
        rel="stylesheet"
        href="farmer-orders.css"
    >
    <link
        rel="stylesheet"
        href="css/farmer-order-details.css"
    >
    

</head>

<body>

<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <div class="nav-container">

        <a href="farmer.php" class="logo">
            AgroLink
        </a>

        <div class="nav-links">

            <a href="farmer.php">
                Dashboard
            </a>

            <a href="farmer-products.php">
                My Products
            </a>

            <a href="farmer-bookings.php">
                Harvest Bookings
            </a>

            <a href="farmer-demands.php">
                Demand Broadcasts
            </a>

            <a
                href="farmer-orders.php"
                class="active-nav"
            >
                Orders
            </a>

        </div>

        <div class="farmer-actions">

            <div class="notification">
                🔔
                <span class="notification-count">3</span>
            </div>

            <a
                href="farmer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">
                    A
                </span>

                <span>
                    <?= e($farmerName) ?>
                </span>

            </a>

            <a
                href="index.php"
                class="logout-btn"
            >
                Logout
            </a>

        </div>

    </div>

</nav>


<!-- =========================================================
     MAIN
========================================================= -->

<main class="order-details-page">

    <div class="details-top">

        <div>

            <a
                href="farmer-orders.php"
                class="back-btn"
            >
                ← Back to Orders
            </a>

            <div class="order-number">
                Order #AGL-<?= str_pad($order["id"], 4, "0", STR_PAD_LEFT) ?>
            </div>

            <h1 class="details-title">
                Customer Order Details
            </h1>

        </div>

    </div>


    <!-- =====================================================
         ALERTS
    ===================================================== -->

    <?php if ($successMessage): ?>

        <div class="alert alert-success">
            <?= e($successMessage) ?>
        </div>

    <?php endif; ?>


    <?php if ($errorMessage): ?>

        <div class="alert alert-error">
            <?= e($errorMessage) ?>
        </div>

    <?php endif; ?>


    <div class="details-grid">


        <!-- =================================================
             LEFT COLUMN
        ================================================= -->

        <div>


            <!-- Customer -->

            <div class="details-card">

                <h3>
                    Customer Information
                </h3>

                <div class="customer-details">

                    <div class="info-item">

                        <span>
                            Customer Name
                        </span>

                        <strong>
                            <?= e($order["customer_name"]) ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Phone
                        </span>

                        <strong>
                            <?= e($order["phone"] ?: $order["customer_phone"]) ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Email
                        </span>

                        <strong>
                            <?= e($order["customer_email"]) ?>
                        </strong>

                    </div>


                    <div class="info-item">

                        <span>
                            Order Date
                        </span>

                        <strong>
                            <?= e($orderDate) ?>
                        </strong>

                    </div>

                </div>

            </div>


            <!-- Products -->

            <div class="details-card">

                <h3>
                    Ordered Products
                </h3>


                <?php if (empty($orderItems)): ?>

                    <p>
                        No products found for this farmer.
                    </p>

                <?php else: ?>

                    <?php foreach ($orderItems as $item): ?>

                        <div class="product-item">

                            <div class="product-image">

                                <?php if (!empty($item["image"])): ?>

                                    <img
                                        src="<?= e($item["image"]) ?>"
                                        alt="<?= e($item["product_name"]) ?>"
                                    >

                                <?php else: ?>

                                    <div class="product-placeholder">
                                        No Image
                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="product-info">

                                <h4>
                                    <?= e($item["product_name"]) ?>
                                </h4>

                                <p>

                                    <?= number_format(
                                        (float)$item["price"],
                                        2
                                    ) ?>

                                    BDT /

                                    <?= e($item["unit"]) ?>

                                    ×

                                    <?= e($item["quantity"]) ?>

                                </p>

                            </div>


                            <div class="product-total">

                                ৳<?= number_format(
                                    (float)$item["subtotal"],
                                    2
                                ) ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- Delivery Address -->

            <div class="details-card">

                <h3>
                    Delivery Information
                </h3>

                <div class="address-box">

                    <strong>
                        Delivery Address
                    </strong>

                    <br>

                    <?= nl2br(e($order["delivery_address"])) ?>

                    <br><br>

                    <strong>
                        Phone:
                    </strong>

                    <?= e($order["phone"]) ?>

                </div>

            </div>


        </div>


        <!-- =================================================
             RIGHT COLUMN
        ================================================= -->

        <div>


            <!-- Order Status -->

            <div class="details-card">

                <h3>
                    Order Status
                </h3>


                <div class="current-status">

                    <span class="current-status-label">
                        Current Status
                    </span>

                    <span
                        class="status-large <?= e($statusClass) ?>"
                    >
                        <?= e($statusLabel) ?>
                    </span>

                </div>


                <?php if (
                    $status !== "delivered" &&
                    $status !== "cancelled"
                ): ?>

                    <form
                        method="POST"
                        class="status-form"
                    >

                        <label for="status">
                            Change Status
                        </label>

                        <select
                            name="status"
                            id="status"
                        >

                            <?php if ($status === "pending"): ?>

                                <option value="pending">
                                    Pending
                                </option>

                                <option value="confirmed">
                                    Confirmed
                                </option>

                            <?php elseif ($status === "confirmed"): ?>

                                <option value="confirmed">
                                    Confirmed
                                </option>

                                <option value="processing">
                                    Processing
                                </option>

                            <?php elseif ($status === "processing"): ?>

                                <option value="processing">
                                    Processing
                                </option>

                                <option value="shipped">
                                    Shipped
                                </option>

                            <?php elseif ($status === "shipped"): ?>

                                <option value="shipped">
                                    Shipped
                                </option>

                                <option value="delivered">
                                    Completed
                                </option>

                            <?php endif; ?>

                            <option value="cancelled">
                                Cancelled
                            </option>

                        </select>


                        <button
                            type="submit"
                            class="update-status-btn"
                            onclick="return confirmStatusChange();"
                        >
                            Update Status
                        </button>

                    </form>

                <?php elseif ($status === "delivered"): ?>

                    <p style="font-size:14px;color:#666;">
                        This order has been completed.
                    </p>

                <?php elseif ($status === "cancelled"): ?>

                    <p style="font-size:14px;color:#666;">
                        This order has been cancelled and cannot be updated.
                    </p>

                <?php endif; ?>

            </div>


            <!-- Status Timeline -->

            <div class="details-card">

                <h3>
                    Order Progress
                </h3>

                <?php

                $timelineStatuses = [
                    "pending" => "Pending",
                    "confirmed" => "Confirmed",
                    "processing" => "Processing",
                    "shipped" => "Shipped",
                    "delivered" => "Completed"
                ];

                $timelineOrder = [
                    "pending" => 1,
                    "confirmed" => 2,
                    "processing" => 3,
                    "shipped" => 4,
                    "delivered" => 5
                ];

                $currentStep = $timelineOrder[$status] ?? 0;

                ?>

                <div class="status-timeline">

                    <?php foreach (
                        $timelineStatuses as $timelineStatus => $timelineLabel
                    ): ?>

                        <?php

                        $stepNumber =
                            $timelineOrder[$timelineStatus];

                        $isActive =
                            $stepNumber <= $currentStep;

                        $isCurrent =
                            $timelineStatus === $status;

                        ?>

                        <div
                            class="
                                timeline-item
                                <?= $isActive ? "active" : "" ?>
                                <?= $isCurrent ? "current" : "" ?>
                            "
                        >

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <strong>
                                    <?= e($timelineLabel) ?>
                                </strong>

                                <?php if ($isCurrent): ?>

                                    <span>
                                        Current Status
                                    </span>

                                <?php elseif ($isActive): ?>

                                    <span>
                                        Completed
                                    </span>

                                <?php else: ?>

                                    <span>
                                        Waiting
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>


                    <?php if ($status === "cancelled"): ?>

                        <div
                            class="timeline-item active current"
                        >

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <strong>
                                    Cancelled
                                </strong>

                                <span>
                                    Current Status
                                </span>

                            </div>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- Payment / Summary -->

            <div class="details-card">

                <h3>
                    Order Summary
                </h3>


                <div class="summary-row">

                    <span>
                        Payment Method
                    </span>

                    <strong>
                        <?= e($order["payment_method"]) ?>
                    </strong>

                </div>


                <div class="summary-row">

                    <span>
                        Your Products
                    </span>

                    <strong>
                        <?= count($orderItems) ?>
                    </strong>

                </div>


                <div class="summary-row">

                    <span>
                        Last Updated
                    </span>

                    <strong>
                        <?= e($updatedDate) ?>
                    </strong>

                </div>


                <div class="summary-row summary-total">

                    <span>
                        Your Total
                    </span>

                    <strong>
                        ৳<?= number_format(
                            $farmerSubtotal,
                            2
                        ) ?>
                    </strong>

                </div>

            </div>


        </div>

    </div>

</main>


<script>

function confirmStatusChange()
{
    const statusSelect =
        document.getElementById("status");

    const selectedStatus =
        statusSelect.value;

    const labels = {
        pending: "Pending",
        confirmed: "Confirmed",
        processing: "Processing",
        shipped: "Shipped",
        delivered: "Completed",
        cancelled: "Cancelled"
    };

    const selectedLabel =
        labels[selectedStatus] || selectedStatus;

    return confirm(
        "Are you sure you want to change the order status to \"" +
        selectedLabel +
        "\"?"
    );
}

</script>

</body>

</html>