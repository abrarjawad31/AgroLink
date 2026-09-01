<?php

// ============================================================
// FARMER ORDERS PAGE
// ============================================================

// Authentication
require_once "auth.php";
requireFarmer();

// Database connection
require_once "config.php";

// ============================================================
// LOGGED-IN FARMER
// ============================================================

$farmerId = (int) ($_SESSION["user_id"] ?? 0);
$farmerName = $_SESSION["user_name"] ?? "Farmer";


// ============================================================
// HELPER FUNCTION
// ============================================================

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


// ============================================================
// FARMER INITIAL
// ============================================================

$farmerInitial = strtoupper(
    substr(trim($farmerName), 0, 1)
);

if ($farmerInitial === "") {
    $farmerInitial = "F";
}


// ============================================================
// FILTER VALUES
// ============================================================

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";

$statusFilter = isset($_GET["status"])
    ? trim($_GET["status"])
    : "all";

$sort = isset($_GET["sort"])
    ? trim($_GET["sort"])
    : "recent";


// ============================================================
// VALID STATUS VALUES
// ============================================================

$allowedStatuses = [
    "all",
    "pending",
    "confirmed",
    "processing",
    "shipped",
    "delivered",
    "cancelled"
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = "all";
}


// ============================================================
// VALID SORT VALUES
// ============================================================

$allowedSorts = [
    "recent",
    "oldest",
    "highest",
    "lowest"
];

if (!in_array($sort, $allowedSorts, true)) {
    $sort = "recent";
}


// ============================================================
// STATUS UPDATE
// ============================================================
//
// The orders table contains a single global status for an order.
// Therefore, updating the status here updates the order status
// after verifying that the order contains at least one product
// belonging to the logged-in farmer.
// ============================================================

$updateMessage = "";
$updateError = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["update_status"])
) {

    $orderId = isset($_POST["order_id"])
        ? (int) $_POST["order_id"]
        : 0;

    $newStatus = isset($_POST["new_status"])
        ? trim($_POST["new_status"])
        : "";

    $validUpdateStatuses = [
        "pending",
        "confirmed",
        "processing",
        "shipped",
        "delivered",
        "cancelled"
    ];

    if ($orderId <= 0) {

        $updateError = "Invalid order.";

    } elseif (!in_array($newStatus, $validUpdateStatuses, true)) {

        $updateError = "Invalid order status.";

    } else {

        // Verify this order contains one of the farmer's products
        $verifyStmt = $conn->prepare("
            SELECT oi.order_id
            FROM order_items oi
            INNER JOIN products p
                ON p.id = oi.product_id
            WHERE oi.order_id = ?
              AND p.farmer_id = ?
            LIMIT 1
        ");

        if ($verifyStmt) {

            $verifyStmt->bind_param(
                "ii",
                $orderId,
                $farmerId
            );

            $verifyStmt->execute();

            $verifyResult = $verifyStmt->get_result();

            $orderBelongsToFarmer =
                $verifyResult->num_rows > 0;

            $verifyStmt->close();

            if (!$orderBelongsToFarmer) {

                $updateError =
                    "You are not authorized to update this order.";

            } else {

                $updateStmt = $conn->prepare("
                    UPDATE orders
                    SET status = ?
                    WHERE id = ?
                ");

                if ($updateStmt) {

                    $updateStmt->bind_param(
                        "si",
                        $newStatus,
                        $orderId
                    );

                    if ($updateStmt->execute()) {

                        $updateMessage =
                            "Order status updated successfully.";

                    } else {

                        $updateError =
                            "Unable to update the order status.";
                    }

                    $updateStmt->close();

                } else {

                    $updateError =
                        "Unable to prepare the status update.";
                }
            }

        } else {

            $updateError =
                "Unable to verify the order.";
        }
    }
}


// ============================================================
// FARMER ORDER SUMMARY
// ============================================================

$totalOrders = 0;
$pendingOrders = 0;
$processingOrders = 0;
$completedOrders = 0;


// ------------------------------------------------------------
// Total Orders
// ------------------------------------------------------------

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT oi.order_id) AS total
    FROM order_items oi
    INNER JOIN products p
        ON p.id = oi.product_id
    WHERE p.farmer_id = ?
");

if ($stmt) {

    $stmt->bind_param("i", $farmerId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $totalOrders = (int) $row["total"];
    }

    $stmt->close();
}


// ------------------------------------------------------------
// Pending Orders
// ------------------------------------------------------------

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT oi.order_id) AS total
    FROM order_items oi
    INNER JOIN products p
        ON p.id = oi.product_id
    INNER JOIN orders o
        ON o.id = oi.order_id
    WHERE p.farmer_id = ?
      AND o.status = 'pending'
");

if ($stmt) {

    $stmt->bind_param("i", $farmerId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $pendingOrders = (int) $row["total"];
    }

    $stmt->close();
}


// ------------------------------------------------------------
// Processing Orders
// ------------------------------------------------------------

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT oi.order_id) AS total
    FROM order_items oi
    INNER JOIN products p
        ON p.id = oi.product_id
    INNER JOIN orders o
        ON o.id = oi.order_id
    WHERE p.farmer_id = ?
      AND o.status = 'processing'
");

if ($stmt) {

    $stmt->bind_param("i", $farmerId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $processingOrders = (int) $row["total"];
    }

    $stmt->close();
}


// ------------------------------------------------------------
// Completed Orders
// ------------------------------------------------------------
// "delivered" is the completed state in the actual database.
// ------------------------------------------------------------

$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT oi.order_id) AS total
    FROM order_items oi
    INNER JOIN products p
        ON p.id = oi.product_id
    INNER JOIN orders o
        ON o.id = oi.order_id
    WHERE p.farmer_id = ?
      AND o.status = 'delivered'
");

if ($stmt) {

    $stmt->bind_param("i", $farmerId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $completedOrders = (int) $row["total"];
    }

    $stmt->close();
}


// ============================================================
// BUILD ORDER FILTER
// ============================================================

$where = [
    "p.farmer_id = ?"
];

$params = [
    $farmerId
];

$types = "i";


// ------------------------------------------------------------
// Search
// ------------------------------------------------------------

if ($search !== "") {

    $where[] = "
        (
            CAST(o.id AS CHAR) LIKE ?
            OR u.name LIKE ?
            OR oi.product_name LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sss";
}


// ------------------------------------------------------------
// Status
// ------------------------------------------------------------

if ($statusFilter !== "all") {

    $where[] = "o.status = ?";

    $params[] = $statusFilter;

    $types .= "s";
}


$whereSql = implode(" AND ", $where);


// ============================================================
// SORTING
// ============================================================

switch ($sort) {

    case "oldest":

        $orderSql = "o.created_at ASC";

        break;

    case "highest":

        $orderSql = "farmer_total DESC";

        break;

    case "lowest":

        $orderSql = "farmer_total ASC";

        break;

    case "recent":
    default:

        $orderSql = "o.created_at DESC";

        break;
}


// ============================================================
// GET FARMER ORDERS
// ============================================================
//
// Important:
// One consumer order can contain products from multiple
// farmers. Therefore, farmer_total represents only the
// subtotal belonging to the logged-in farmer.
// ============================================================

$orders = [];

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
        u.address AS customer_address,

        COUNT(DISTINCT oi.id) AS item_count,

        SUM(oi.quantity) AS total_quantity,

        SUM(oi.subtotal) AS farmer_total,

        GROUP_CONCAT(
            DISTINCT oi.product_name
            ORDER BY oi.product_name
            SEPARATOR ', '
        ) AS product_names

    FROM orders o

    INNER JOIN users u
        ON u.id = o.consumer_id

    INNER JOIN order_items oi
        ON oi.order_id = o.id

    INNER JOIN products p
        ON p.id = oi.product_id

    WHERE $whereSql

    GROUP BY
        o.id,
        o.consumer_id,
        o.total_amount,
        o.status,
        o.payment_method,
        o.delivery_address,
        o.phone,
        o.created_at,
        o.updated_at,
        u.name,
        u.address

    ORDER BY $orderSql
";

$stmt = $conn->prepare($orderSql);

if ($stmt) {

    $stmt->bind_param(
        $types,
        ...$params
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $orders[] = $row;
    }

    $stmt->close();
}


// ============================================================
// STATUS DISPLAY HELPER
// ============================================================

function getStatusLabel($status)
{
    switch ($status) {

        case "pending":
            return "Pending";

        case "confirmed":
            return "Confirmed";

        case "processing":
            return "Processing";

        case "shipped":
            return "Shipped";

        case "delivered":
            return "Completed";

        case "cancelled":
            return "Cancelled";

        default:
            return ucfirst($status);
    }
}


// ============================================================
// STATUS CSS CLASS HELPER
// ============================================================

function getStatusClass($status)
{
    switch ($status) {

        case "pending":
            return "pending";

        case "confirmed":
            return "processing";

        case "processing":
            return "processing";

        case "shipped":
            return "shipped";

        case "delivered":
            return "completed";

        case "cancelled":
            return "cancelled";

        default:
            return "pending";
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

    <title>Orders | AgroLink</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/farmer-orders.css"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
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
            href="farmer.php"
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

            <a href="farmer-dss.php">
                DSS
            </a>

        </nav>


        <!-- FARMER ACTIONS -->

        <div class="farmer-actions">


            <!-- NOTIFICATION -->

            <a
                href="#"
                class="notification"
            >

                🔔

                <span class="notification-count">
                    3
                </span>

            </a>


            <!-- PROFILE -->

            <a
                href="farmer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">
                    <?php echo e($farmerInitial); ?>
                </span>

                <span class="profile-name">
                    <?php echo e($farmerName); ?>
                </span>

            </a>


            <!-- LOGOUT -->

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
     MAIN
========================================================= -->

<main class="orders-page">

    <div class="container">


        <!-- =================================================
             PAGE HEADER
        ================================================= -->

        <section class="page-header">

            <div>

                <span class="page-tag">
                    FARM MANAGEMENT
                </span>

                <h1>
                    Customer Orders
                </h1>

                <p>
                    View and manage orders placed by consumers
                    for your products.
                </p>

            </div>

        </section>


        <!-- =================================================
             UPDATE MESSAGE
        ================================================= -->

        <?php if ($updateMessage !== ""): ?>

            <div
                style="
                    margin-bottom:15px;
                    padding:12px 15px;
                    border:1px solid #cfe8d0;
                    border-radius:8px;
                    background:#eef9ef;
                    color:#2e7d32;
                    font-size:10px;
                "
            >

                <?php echo e($updateMessage); ?>

            </div>

        <?php endif; ?>


        <?php if ($updateError !== ""): ?>

            <div
                style="
                    margin-bottom:15px;
                    padding:12px 15px;
                    border:1px solid #f0cccc;
                    border-radius:8px;
                    background:#fff2f2;
                    color:#c54b4b;
                    font-size:10px;
                "
            >

                <?php echo e($updateError); ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             ORDER SUMMARY
        ================================================= -->

        <section class="order-summary">


            <!-- TOTAL -->

            <div class="summary-card">

                <div class="summary-icon">
                    📦
                </div>

                <div>

                    <span>
                        Total Orders
                    </span>

                    <strong>
                        <?php echo $totalOrders; ?>
                    </strong>

                </div>

            </div>


            <!-- PENDING -->

            <div class="summary-card">

                <div class="summary-icon pending-icon">
                    ⏳
                </div>

                <div>

                    <span>
                        Pending
                    </span>

                    <strong>
                        <?php echo $pendingOrders; ?>
                    </strong>

                </div>

            </div>


            <!-- PROCESSING -->

            <div class="summary-card">

                <div class="summary-icon processing-icon">
                    🔄
                </div>

                <div>

                    <span>
                        Processing
                    </span>

                    <strong>
                        <?php echo $processingOrders; ?>
                    </strong>

                </div>

            </div>


            <!-- COMPLETED -->

            <div class="summary-card">

                <div class="summary-icon completed-icon">
                    ✓
                </div>

                <div>

                    <span>
                        Completed
                    </span>

                    <strong>
                        <?php echo $completedOrders; ?>
                    </strong>

                </div>

            </div>


        </section>



        <!-- =================================================
             FILTER BAR
        ================================================= -->

        <section class="filter-bar">


            <!-- SEARCH -->

            <form
                method="GET"
                action="farmer-orders.php"
                class="search-box"
            >

                <span>
                    🔍
                </span>

                <input
                    type="text"
                    name="search"
                    value="<?php echo e($search); ?>"
                    placeholder="Search order ID, customer or product..."
                >

                <input
                    type="hidden"
                    name="status"
                    value="<?php echo e($statusFilter); ?>"
                >

                <input
                    type="hidden"
                    name="sort"
                    value="<?php echo e($sort); ?>"
                >

            </form>


            <!-- STATUS FILTER -->

            <select
                id="statusFilter"
                onchange="applyFilters()"
            >

                <option
                    value="all"
                    <?php echo $statusFilter === "all" ? "selected" : ""; ?>
                >
                    All Status
                </option>

                <option
                    value="pending"
                    <?php echo $statusFilter === "pending" ? "selected" : ""; ?>
                >
                    Pending
                </option>

                <option
                    value="confirmed"
                    <?php echo $statusFilter === "confirmed" ? "selected" : ""; ?>
                >
                    Confirmed
                </option>

                <option
                    value="processing"
                    <?php echo $statusFilter === "processing" ? "selected" : ""; ?>
                >
                    Processing
                </option>

                <option
                    value="shipped"
                    <?php echo $statusFilter === "shipped" ? "selected" : ""; ?>
                >
                    Shipped
                </option>

                <option
                    value="delivered"
                    <?php echo $statusFilter === "delivered" ? "selected" : ""; ?>
                >
                    Completed
                </option>

                <option
                    value="cancelled"
                    <?php echo $statusFilter === "cancelled" ? "selected" : ""; ?>
                >
                    Cancelled
                </option>

            </select>


            <!-- SORT -->

            <select
                id="sortFilter"
                onchange="applyFilters()"
            >

                <option
                    value="recent"
                    <?php echo $sort === "recent" ? "selected" : ""; ?>
                >
                    Newest First
                </option>

                <option
                    value="oldest"
                    <?php echo $sort === "oldest" ? "selected" : ""; ?>
                >
                    Oldest First
                </option>

                <option
                    value="highest"
                    <?php echo $sort === "highest" ? "selected" : ""; ?>
                >
                    Highest Amount
                </option>

                <option
                    value="lowest"
                    <?php echo $sort === "lowest" ? "selected" : ""; ?>
                >
                    Lowest Amount
                </option>

            </select>


        </section>



        <!-- =================================================
             ORDERS CARD
        ================================================= -->

        <section class="orders-card">


            <!-- HEADER -->

            <div class="orders-card-header">

                <div>

                    <h2>
                        Recent Orders
                    </h2>

                    <p>
                        Orders received from consumers
                    </p>

                </div>

                <span>
                    <?php echo $totalOrders; ?> total
                </span>

            </div>



            <!-- TABLE -->

            <div class="orders-table-wrapper">

                <table>


                    <thead>

                        <tr>

                            <th>
                                Order
                            </th>

                            <th>
                                Customer
                            </th>

                            <th>
                                Product
                            </th>

                            <th>
                                Quantity
                            </th>

                            <th>
                                Total
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (!empty($orders)): ?>


                        <?php foreach ($orders as $order): ?>


                            <?php

                            $customerName =
                                trim($order["customer_name"] ?? "");

                            if ($customerName === "") {
                                $customerName = "Customer";
                            }

                            $customerInitial =
                                strtoupper(
                                    substr(
                                        $customerName,
                                        0,
                                        1
                                    )
                                );

                            $statusClass =
                                getStatusClass(
                                    $order["status"]
                                );

                            $statusLabel =
                                getStatusLabel(
                                    $order["status"]
                                );

                            $quantity =
                                (float) $order["total_quantity"];

                            if (
                                floor($quantity) ==
                                $quantity
                            ) {
                                $quantityDisplay =
                                    number_format(
                                        $quantity,
                                        0
                                    );
                            } else {
                                $quantityDisplay =
                                    number_format(
                                        $quantity,
                                        2
                                    );
                            }

                            $orderDate =
                                date(
                                    "M d, Y",
                                    strtotime(
                                        $order["created_at"]
                                    )
                                );

                            ?>


                            <tr>


                                <!-- ORDER ID -->

                                <td>

                                    <strong class="order-id">

                                        #AGL-<?php
                                        echo str_pad(
                                            (int) $order["id"],
                                            4,
                                            "0",
                                            STR_PAD_LEFT
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <!-- CUSTOMER -->

                                <td>

                                    <div class="customer-info">

                                        <span class="customer-avatar">

                                            <?php
                                            echo e(
                                                $customerInitial
                                            );
                                            ?>

                                        </span>


                                        <div>

                                            <strong>
                                                <?php
                                                echo e(
                                                    $customerName
                                                );
                                                ?>
                                            </strong>

                                            <span>

                                                <?php

                                                $customerLocation =
                                                    trim(
                                                        $order[
                                                            "customer_address"
                                                        ] ?? ""
                                                    );

                                                if (
                                                    $customerLocation === ""
                                                ) {
                                                    $customerLocation =
                                                        trim(
                                                            $order[
                                                                "delivery_address"
                                                            ] ?? ""
                                                        );
                                                }

                                                if (
                                                    $customerLocation === ""
                                                ) {
                                                    echo "Delivery address";
                                                } else {

                                                    $locationWords =
                                                        preg_split(
                                                            '/\s+/',
                                                            $customerLocation
                                                        );

                                                    if (
                                                        count(
                                                            $locationWords
                                                        ) > 4
                                                    ) {
                                                        echo e(
                                                            implode(
                                                                " ",
                                                                array_slice(
                                                                    $locationWords,
                                                                    0,
                                                                    4
                                                                )
                                                            )
                                                        );
                                                        echo "...";
                                                    } else {
                                                        echo e(
                                                            $customerLocation
                                                        );
                                                    }
                                                }

                                                ?>

                                            </span>

                                        </div>

                                    </div>

                                </td>


                                <!-- PRODUCT -->

                                <td>

                                    <?php

                                    $productNames =
                                        $order[
                                            "product_names"
                                        ] ?? "";

                                    if (
                                        $productNames === ""
                                    ) {
                                        echo "Product";
                                    } else {

                                        $productList =
                                            explode(
                                                ", ",
                                                $productNames
                                            );

                                        if (
                                            count(
                                                $productList
                                            ) > 1
                                        ) {

                                            echo e(
                                                $productList[0]
                                            );

                                            echo " + ";

                                            echo (
                                                count(
                                                    $productList
                                                ) - 1
                                            );

                                            echo " more";

                                        } else {

                                            echo e(
                                                $productNames
                                            );
                                        }
                                    }

                                    ?>

                                </td>


                                <!-- QUANTITY -->

                                <td>

                                    <?php
                                    echo e(
                                        $quantityDisplay
                                    );
                                    ?>

                                    items

                                </td>


                                <!-- FARMER TOTAL -->

                                <td>

                                    <strong>

                                        ৳<?php
                                        echo number_format(
                                            (float)
                                            $order[
                                                "farmer_total"
                                            ],
                                            2
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <!-- DATE -->

                                <td>

                                    <?php
                                    echo e(
                                        $orderDate
                                    );
                                    ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="status <?php
                                        echo e(
                                            $statusClass
                                        );
                                        ?>"
                                    >

                                        <?php
                                        echo e(
                                            $statusLabel
                                        );
                                        ?>

                                    </span>

                                </td>


                                <!-- ACTION -->

                                <td>

                                    <a
                                        href="farmer-order-details.php?id=<?php echo (int) $order["id"]; ?>"
                                        class="view-btn"
                                    >
                                        View
                                    </a>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <!-- EMPTY STATE -->

                        <tr>

                            <td
                                colspan="8"
                                style="
                                    text-align:center;
                                    padding:50px 20px;
                                "
                            >

                                <div
                                    style="
                                        font-size:30px;
                                        margin-bottom:10px;
                                    "
                                >
                                    📦
                                </div>

                                <strong
                                    style="
                                        display:block;
                                        font-size:12px;
                                        margin-bottom:5px;
                                    "
                                >
                                    No Orders Found
                                </strong>

                                <span
                                    style="
                                        color:var(--light-text);
                                        font-size:9px;
                                    "
                                >
                                    <?php

                                    if (
                                        $search !== "" ||
                                        $statusFilter !== "all"
                                    ) {

                                        echo "Try changing your search or filter.";

                                    } else {

                                        echo "Orders for your products will appear here.";

                                    }

                                    ?>
                                </span>

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

            </div>



            <!-- =================================================
                 PAGINATION / RESULT INFO
            ================================================= -->

            <div class="pagination">

                <span
                    style="
                        margin-right:auto;
                        color:var(--light-text);
                        font-size:8px;
                        align-self:center;
                    "
                >

                    Showing
                    <?php echo count($orders); ?>
                    order<?php echo count($orders) === 1 ? "" : "s"; ?>

                </span>

            </div>


        </section>

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
                href="farmer.php"
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

        </div>



        <!-- FARMER -->

        <div class="footer-column">

            <h3>
                Farmer
            </h3>

            <a href="farmer.php">
                Dashboard
            </a>

            <a href="farmer-products.php">
                My Products
            </a>

            <a href="farmer-orders.php">
                Orders
            </a>

            <a href="farmer-dss.php">
                Decision Support
            </a>

        </div>



        <!-- ACCOUNT -->

        <div class="footer-column">

            <h3>
                Account
            </h3>

            <a href="farmer-profile.php">
                My Profile
            </a>

            <a href="#">
                Settings
            </a>

            <a href="logout.php">
                Logout
            </a>

        </div>



        <!-- SUPPORT -->

        <div class="footer-column">

            <h3>
                Support
            </h3>

            <a href="#">
                Help Center
            </a>

            <a href="contact.php">
                Contact Us
            </a>

            <a href="#">
                FAQ
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
     FILTER SCRIPT
========================================================= -->

<script>

function applyFilters()
{
    const status =
        document.getElementById(
            "statusFilter"
        ).value;

    const sort =
        document.getElementById(
            "sortFilter"
        ).value;

    const searchInput =
        document.querySelector(
            'input[name="search"]'
        );

    const search =
        searchInput
            ? searchInput.value.trim()
            : "";


    const params =
        new URLSearchParams();


    if (search !== "") {

        params.set(
            "search",
            search
        );

    }


    if (status !== "all") {

        params.set(
            "status",
            status
        );

    }


    if (sort !== "recent") {

        params.set(
            "sort",
            sort
        );

    }


    const query =
        params.toString();


    window.location.href =
        "farmer-orders.php" +
        (
            query
                ? "?" + query
                : ""
        );
}

</script>


</body>

</html>