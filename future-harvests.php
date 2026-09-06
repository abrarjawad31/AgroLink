<?php

// ============================================================
// CONSUMER FUTURE HARVEST / PRE-BOOKING PAGE
// ============================================================

require_once "auth.php";
requireConsumer();

require_once "db.php";


// ============================================================
// SESSION / CONSUMER
// ============================================================

$consumerId = (int) $_SESSION["user_id"];
$consumerName = $_SESSION["user_name"] ?? "Consumer";


// ============================================================
// HELPER
// ============================================================

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}


// ============================================================
// CONSUMER INFORMATION
// ============================================================

$consumerPhone = "";
$consumerAddress = "";

$stmt = $conn->prepare("
    SELECT name, phone, address
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {

    $consumerName =
        !empty($user["name"])
            ? $user["name"]
            : $consumerName;

    $consumerPhone =
        $user["phone"] ?? "";

    $consumerAddress =
        $user["address"] ?? "";
}

$stmt->close();


// ============================================================
// CART COUNT
// ============================================================

$cartCount = 0;

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(quantity), 0) AS total
    FROM cart_items ci
    INNER JOIN cart c
        ON ci.cart_id = c.id
    WHERE c.consumer_id = ?
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $cartCount = (int) $row["total"];
}

$stmt->close();


// ============================================================
// AVATAR
// ============================================================

$avatarLetter = strtoupper(
    substr(trim($consumerName), 0, 1)
);


// ============================================================
// MESSAGE VARIABLES
// ============================================================

$message = "";
$messageType = "";


// ============================================================
// PRE-BOOK FUTURE HARVEST
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $harvestId = isset($_POST["harvest_id"])
        ? (int) $_POST["harvest_id"]
        : 0;

    $requestedQuantity = isset($_POST["quantity"])
        ? (float) $_POST["quantity"]
        : 0;

    $deliveryAddress = trim(
        $_POST["delivery_address"] ?? ""
    );

    $phone = trim(
        $_POST["phone"] ?? ""
    );

    $note = trim(
        $_POST["note"] ?? ""
    );


    // --------------------------------------------------------
    // BASIC VALIDATION
    // --------------------------------------------------------

    if ($harvestId <= 0) {

        $message = "Invalid future harvest selected.";
        $messageType = "error";

    } elseif ($requestedQuantity <= 0) {

        $message = "Please enter a valid booking quantity.";
        $messageType = "error";

    } elseif ($deliveryAddress === "") {

        $message = "Please provide your delivery address.";
        $messageType = "error";

    } elseif ($phone === "") {

        $message = "Please provide your phone number.";
        $messageType = "error";

    } else {


        // ====================================================
        // TRANSACTION
        // ====================================================

        $conn->begin_transaction();

        try {


            // ------------------------------------------------
            // LOCK THE HARVEST ROW
            // ------------------------------------------------
            // This prevents two consumers from booking the
            // same remaining quantity at the same time.

            $stmt = $conn->prepare("
                SELECT
                    id,
                    farmer_id,
                    product_name,
                    price,
                    unit,
                    expected_quantity,
                    prebook_quantity,
                    remaining_quantity,
                    minimum_booking,
                    harvest_date,
                    status

                FROM future_harvests

                WHERE id = ?

                FOR UPDATE
            ");

            $stmt->bind_param(
                "i",
                $harvestId
            );

            $stmt->execute();

            $result = $stmt->get_result();

            $harvest = $result->fetch_assoc();

            $stmt->close();


            // ------------------------------------------------
            // HARVEST EXISTS?
            // ------------------------------------------------

            if (!$harvest) {

                throw new Exception(
                    "The selected future harvest could not be found."
                );
            }


            // ------------------------------------------------
            // CHECK STATUS
            // ------------------------------------------------

            if ($harvest["status"] !== "open") {

                throw new Exception(
                    "This future harvest is no longer open for booking."
                );
            }


            // ------------------------------------------------
            // CHECK HARVEST DATE
            // ------------------------------------------------

            if (
                strtotime($harvest["harvest_date"])
                < strtotime(date("Y-m-d"))
            ) {

                throw new Exception(
                    "The harvest date has already passed."
                );
            }


            // ------------------------------------------------
            // NUMERIC VALUES
            // ------------------------------------------------

            $remainingQuantity =
                (float) $harvest["remaining_quantity"];

            $minimumBooking =
                (float) $harvest["minimum_booking"];

            $pricePerUnit =
                (float) $harvest["price"];


            // ------------------------------------------------
            // MINIMUM BOOKING
            // ------------------------------------------------

            if ($requestedQuantity < $minimumBooking) {

                throw new Exception(
                    "Minimum booking quantity is "
                    . number_format($minimumBooking, 2)
                    . " "
                    . $harvest["unit"]
                    . "."
                );
            }


            // ------------------------------------------------
            // REMAINING QUANTITY
            // ------------------------------------------------

            if ($requestedQuantity > $remainingQuantity) {

                throw new Exception(
                    "Only "
                    . number_format(
                        $remainingQuantity,
                        2
                    )
                    . " "
                    . $harvest["unit"]
                    . " is available for pre-booking."
                );
            }


            // ------------------------------------------------
            // CALCULATE TOTAL
            // ------------------------------------------------

            $totalAmount =
                $requestedQuantity * $pricePerUnit;


            // ------------------------------------------------
            // INSERT BOOKING
            // ------------------------------------------------

            $stmt = $conn->prepare("
                INSERT INTO harvest_bookings
                (
                    harvest_id,
                    consumer_id,
                    farmer_id,
                    quantity,
                    unit,
                    price_per_unit,
                    total_amount,
                    delivery_address,
                    phone,
                    note,
                    status
                )

                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending'
                )
            ");

            $stmt->bind_param(
                "iiidsddsss",
                $harvestId,
                $consumerId,
                $harvest["farmer_id"],
                $requestedQuantity,
                $harvest["unit"],
                $pricePerUnit,
                $totalAmount,
                $deliveryAddress,
                $phone,
                $note
            );

            if (!$stmt->execute()) {
                $stmt->close();
                throw new Exception("Unable to submit the pre-booking request.");
            }

            $stmt->close();

            $remainingAfterBooking =
                $remainingQuantity - $requestedQuantity;

            $newStatus = $remainingAfterBooking <= 0
                ? "fully_booked"
                : "open";

            $updateStmt = $conn->prepare("
                UPDATE future_harvests
                SET
                    remaining_quantity = ?,
                    status = ?
                WHERE id = ?
            ");

            $updateStmt->bind_param(
                "dsi",
                $remainingAfterBooking,
                $newStatus,
                $harvestId
            );

            if (!$updateStmt->execute()) {
                $updateStmt->close();
                throw new Exception("Unable to update harvest availability.");
            }

            $updateStmt->close();
            $conn->commit();

            $message = "Your pre-booking request was submitted successfully.";
            $messageType = "success";

        } catch (Exception $e) {

            $conn->rollback();

            $message = $e->getMessage();
            $messageType = "error";
        }
    }
}

// ============================================================
// AVAILABLE Pre Booking
// ============================================================

$futureHarvests = [];

$stmt = $conn->prepare("
    SELECT
        fh.*,
        u.name AS farmer_name,
        u.address AS farmer_address
    FROM future_harvests fh
    INNER JOIN users u
        ON u.id = fh.farmer_id
    WHERE fh.status = 'open'
      AND fh.remaining_quantity > 0
      AND fh.harvest_date >= CURDATE()
    ORDER BY fh.harvest_date ASC, fh.created_at DESC
");

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $futureHarvests[] = $row;
}

$stmt->close();

$myBookings = [];

$stmt = $conn->prepare("
    SELECT
        hb.quantity,
        hb.unit,
        hb.total_amount,
        hb.status,
        hb.created_at,
        fh.product_name,
        fh.harvest_date
    FROM harvest_bookings hb
    INNER JOIN future_harvests fh
        ON fh.id = hb.harvest_id
    WHERE hb.consumer_id = ?
    ORDER BY hb.created_at DESC
    LIMIT 8
");

$stmt->bind_param("i", $consumerId);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $myBookings[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre Booking | AgroLink</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/future-harvests.css?v=20260906">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
</head>
<body>

<header class="header">
    <div class="container navbar">
        <a href="consumer-dashboard.php" class="logo">
            <span class="logo-icon">🌱</span>
            <span>Agro<span>Link</span></span>
        </a>

        <nav class="nav-menu">
            <a href="consumer-dashboard.php">Home</a>
            <a href="marketplace.php">Marketplace</a>
            <a href="future-harvests.php" class="active-nav">Pre Booking</a>
            <a href="consumer-demands.php">Demand Hub</a>
            <a href="my-orders.php">My Orders</a>
        </nav>

        <div class="consumer-actions">
            <a href="cart.php" class="cart-link">🛒 Cart <span class="cart-count"><?= $cartCount ?></span></a>
            <a href="consumer-profile.php" class="profile-link">
                <span class="profile-avatar"><?= e($avatarLetter) ?></span>
                <span class="profile-name"><?= e($consumerName) ?></span>
            </a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
</header>

<section class="future-hero">
    <div class="container">
        <span class="section-tag">PLAN AHEAD</span>
        <h1>Book the harvest <span>before it arrives.</span></h1>
        <p>Reserve fresh produce directly from local farmers and secure your quantity ahead of the harvest date.</p>
    </div>
</section>

<main class="future-main">
    <div class="container">
        <?php if ($message !== ""): ?>
            <div class="booking-message <?= e($messageType) ?>">
                <p><?= e($message) ?></p>
            </div>
        <?php endif; ?>

        <div class="future-heading">
            <span class="small-label">AVAILABLE NOW</span>
            <h2>Pre Booking</h2>
            <p>Choose a listing, enter your preferred quantity and delivery details, and submit your pre-booking request.</p>
        </div>

        <?php if (empty($futureHarvests)): ?>
            <div class="empty-future">
                <div class="empty-icon">🌱</div>
                <h2>No Pre Booking Available</h2>
                <p>Farmers have not published any available Pre Booking yet. Please check back soon.</p>
                <a href="marketplace.php" class="empty-button">Browse Marketplace</a>
            </div>
        <?php else: ?>
            <div class="future-grid">
                <?php foreach ($futureHarvests as $harvest): ?>
                    <?php
                    $harvestDate = strtotime($harvest["harvest_date"]);
                    $daysRemaining = max(0, (int) floor(($harvestDate - time()) / 86400));
                    $progress = (float) $harvest["prebook_quantity"] > 0
                        ? 100 - ((float) $harvest["remaining_quantity"] / (float) $harvest["prebook_quantity"] * 100)
                        : 0;
                    $progress = min(100, max(0, $progress));
                    ?>

                    <article class="future-card">
                        <div class="future-image">
                            <?php if (!empty($harvest["image"])): ?>
                                <img src="uploads/products/<?= e($harvest["image"]) ?>" alt="<?= e($harvest["product_name"]) ?>">
                            <?php else: ?>
                                <div class="image-placeholder">🌾</div>
                            <?php endif; ?>
                            <span class="available-badge">Open for pre-booking</span>
                        </div>

                        <div class="future-content">
                            <div class="category"><?= e($harvest["category"]) ?></div>
                            <h3><?= e($harvest["product_name"]) ?></h3>

                            <?php if (!empty($harvest["description"])): ?>
                                <p class="description"><?= e($harvest["description"]) ?></p>
                            <?php endif; ?>

                            <div class="farmer-info">
                                <span class="farmer-icon">👨‍🌾</span>
                                <div>
                                    <span>Harvest from</span>
                                    <strong><?= e($harvest["farmer_name"]) ?></strong>
                                </div>
                            </div>

                            <div class="price-row">
                                <strong>৳<?= number_format((float) $harvest["price"], 2) ?></strong>
                                <span>/ <?= e($harvest["unit"]) ?></span>
                            </div>

                            <div class="info-row"><span>Harvest date</span><strong><?= date("d M Y", $harvestDate) ?></strong></div>
                            <div class="info-row"><span>Location</span><strong><?= e($harvest["location"] ?: "Not specified") ?></strong></div>
                            <div class="days-box">⏳ <?= $daysRemaining === 0 ? "Harvesting today" : $daysRemaining . " days remaining" ?></div>

                            <div class="quantity-block">
                                <div class="quantity-header">
                                    <span>Available to reserve</span>
                                    <strong><?= number_format((float) $harvest["remaining_quantity"], 2) ?> <?= e($harvest["unit"]) ?></strong>
                                </div>
                                <div class="progress-track"><div class="progress-value" style="width: <?= round($progress) ?>%;"></div></div>
                                <div class="quantity-details">
                                    <span>Pre-booked: <?= number_format((float) $harvest["prebook_quantity"] - (float) $harvest["remaining_quantity"], 2) ?></span>
                                    <span>Minimum: <?= number_format((float) $harvest["minimum_booking"], 2) ?></span>
                                </div>
                            </div>

                            <form method="POST" class="booking-form">
                                <div class="form-title">Pre-book this harvest</div>
                                <input type="hidden" name="harvest_id" value="<?= (int) $harvest["id"] ?>">

                                <div class="form-group quantity-input">
                                    <label for="quantity-<?= (int) $harvest["id"] ?>">Quantity <span><?= e($harvest["unit"]) ?></span></label>
                                    <input id="quantity-<?= (int) $harvest["id"] ?>" type="number" name="quantity" min="<?= e($harvest["minimum_booking"]) ?>" max="<?= e($harvest["remaining_quantity"]) ?>" step="0.01" required>
                                    <span><?= e($harvest["unit"]) ?></span>
                                </div>

                                <div class="form-group">
                                    <label for="address-<?= (int) $harvest["id"] ?>">Delivery address</label>
                                    <textarea id="address-<?= (int) $harvest["id"] ?>" name="delivery_address" rows="2" required><?= e($consumerAddress) ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="phone-<?= (int) $harvest["id"] ?>">Phone</label>
                                    <input id="phone-<?= (int) $harvest["id"] ?>" type="tel" name="phone" value="<?= e($consumerPhone) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label for="note-<?= (int) $harvest["id"] ?>">Note <span>optional</span></label>
                                    <textarea id="note-<?= (int) $harvest["id"] ?>" name="note" rows="2"></textarea>
                                </div>

                                <button type="submit" class="prebook-button">Submit Pre-booking Request</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($myBookings)): ?>
            <section class="my-bookings-section">
                <div class="section-heading"><span class="small-label">YOUR ACTIVITY</span><h2>My Pre-bookings</h2></div>
                <div class="booking-table-wrapper">
                    <table class="booking-table">
                        <thead><tr><th>Harvest</th><th>Quantity</th><th>Total</th><th>Harvest date</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($myBookings as $booking): ?>
                                <tr>
                                    <td><?= e($booking["product_name"]) ?></td>
                                    <td><?= number_format((float) $booking["quantity"], 2) ?> <?= e($booking["unit"]) ?></td>
                                    <td><strong class="booking-total">৳<?= number_format((float) $booking["total_amount"], 2) ?></strong></td>
                                    <td><?= date("d M Y", strtotime($booking["harvest_date"])) ?></td>
                                    <td><span class="booking-status status-<?= e($booking["status"]) ?>"><?= e(ucfirst($booking["status"])) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</main>

<footer class="footer">
    <div class="container footer-grid">
        <div class="footer-about">
            <a href="consumer-dashboard.php" class="logo footer-logo"><span class="logo-icon">🌱</span><span>Agro<span>Link</span></span></a>
            <p>Connecting farmers and consumers through a smarter agricultural marketplace.</p>
        </div>
        <div class="footer-column"><h3>Consumer</h3><a href="consumer-dashboard.php">Dashboard</a><a href="marketplace.php">Marketplace</a><a href="future-harvests.php">Pre Booking</a><a href="consumer-demands.php">Demand Hub</a></div>
        <div class="footer-column"><h3>Account</h3><a href="consumer-profile.php">My Profile</a><a href="my-orders.php">My Orders</a><a href="cart.php">Cart</a><a href="logout.php">Logout</a></div>
        <div class="footer-column"><h3>Support</h3><a href="consumer-profile.php">Help Center</a><a href="consumer-demands.php">Contact Demand Hub</a></div>
    </div>
    <div class="footer-bottom"><div class="container"><p>© <?= date("Y") ?> AgroLink. All Rights Reserved.</p><p>Academic Project</p></div></div>
</footer>

</body>
</html>