<?php

// ============================================================
// FARMER HARVEST BOOKINGS PAGE
// ============================================================

// Authentication
require_once "auth.php";
requireFarmer();

// Database connection
require_once "config.php";


// ============================================================
// LOGGED-IN FARMER INFORMATION
// ============================================================

$farmerId = (int) $_SESSION["user_id"];
$farmerName = $_SESSION["user_name"] ?? "Farmer";


// ============================================================
// HELPER FUNCTION
// ============================================================

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}


// ============================================================
// FARMER INITIAL
// ============================================================

$farmerInitial = strtoupper(
    substr(trim($farmerName), 0, 1)
);


// ============================================================
// SUMMARY COUNTS
// ============================================================

$totalHarvests = 0;
$openHarvests = 0;
$fullyBookedHarvests = 0;
$totalBookings = 0;


// ------------------------------------------------------------
// Total future harvests
// ------------------------------------------------------------

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM future_harvests
     WHERE farmer_id = ?"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $totalHarvests = (int) $row["total"];
}

$stmt->close();


// ------------------------------------------------------------
// Open future harvests
// ------------------------------------------------------------

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM future_harvests
     WHERE farmer_id = ?
     AND status = 'open'"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $openHarvests = (int) $row["total"];
}

$stmt->close();


// ------------------------------------------------------------
// Fully booked harvests
// ------------------------------------------------------------

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM future_harvests
     WHERE farmer_id = ?
     AND status = 'fully_booked'"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $fullyBookedHarvests = (int) $row["total"];
}

$stmt->close();


// ------------------------------------------------------------
// Total bookings
// ------------------------------------------------------------

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM harvest_bookings
     WHERE farmer_id = ?"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $totalBookings = (int) $row["total"];
}

$stmt->close();


// ============================================================
// GET FUTURE HARVEST LISTINGS
// ============================================================

$futureHarvests = [];

$stmt = $conn->prepare(
    "SELECT
        fh.id,
        fh.farmer_id,
        fh.product_name,
        fh.category,
        fh.description,
        fh.price,
        fh.unit,
        fh.expected_quantity,
        fh.prebook_quantity,
        fh.remaining_quantity,
        fh.minimum_booking,
        fh.harvest_date,
        fh.location,
        fh.image,
        fh.status,
        fh.created_at,
        fh.updated_at,

        COALESCE(
            SUM(
                CASE
                    WHEN hb.status IN ('pending', 'confirmed', 'ready')
                    THEN hb.quantity
                    ELSE 0
                END
            ),
            0
        ) AS booked_quantity,

        COUNT(
            CASE
                WHEN hb.status IN ('pending', 'confirmed', 'ready')
                THEN hb.id
                ELSE NULL
            END
        ) AS booking_count

     FROM future_harvests fh

     LEFT JOIN harvest_bookings hb
        ON fh.id = hb.harvest_id

     WHERE fh.farmer_id = ?

     GROUP BY
        fh.id,
        fh.farmer_id,
        fh.product_name,
        fh.category,
        fh.description,
        fh.price,
        fh.unit,
        fh.expected_quantity,
        fh.prebook_quantity,
        fh.remaining_quantity,
        fh.minimum_booking,
        fh.harvest_date,
        fh.location,
        fh.image,
        fh.status,
        fh.created_at,
        fh.updated_at

     ORDER BY fh.harvest_date ASC, fh.created_at DESC"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $futureHarvests[] = $row;
}

$stmt->close();


// ============================================================
// GET FARMER BOOKINGS
// ============================================================

$bookings = [];

$stmt = $conn->prepare(
    "SELECT
        hb.id,
        hb.harvest_id,
        hb.consumer_id,
        hb.quantity,
        hb.unit,
        hb.price_per_unit,
        hb.total_amount,
        hb.delivery_address,
        hb.phone,
        hb.note,
        hb.status,
        hb.created_at,

        fh.product_name,
        fh.harvest_date,

        u.name AS consumer_name,
        u.email AS consumer_email

     FROM harvest_bookings hb

     INNER JOIN future_harvests fh
        ON hb.harvest_id = fh.id

     INNER JOIN users u
        ON hb.consumer_id = u.id

     WHERE hb.farmer_id = ?

     ORDER BY hb.created_at DESC"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $bookings[] = $row;
}

$stmt->close();


// ============================================================
// BOOKING STATUS CLASS
// ============================================================

function bookingStatusClass($status)
{
    switch ($status) {

        case "confirmed":
            return "status-confirmed";

        case "rejected":
            return "status-rejected";

        case "cancelled":
            return "status-cancelled";

        case "ready":
            return "status-ready";

        case "completed":
            return "status-completed";

        default:
            return "status-pending";
    }
}


// ============================================================
// HARVEST STATUS CLASS
// ============================================================

function harvestStatusClass($status)
{
    switch ($status) {

        case "fully_booked":
            return "status-full";

        case "harvested":
            return "status-harvested";

        case "cancelled":
            return "status-cancelled";

        default:
            return "status-open";
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

    <title>Harvest Bookings | AgroLink</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/farmer-bookings.css?v=20260906"
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

            <a
                href="farmer-bookings.php"
                class="active-nav"
            >
                Harvest Bookings
            </a>

            <a href="farmer-demands.php">
                Demand Broadcasts
            </a>

            <a href="farmer-orders.php">
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

                <?php if ($totalBookings > 0): ?>

                    <span class="notification-count">
                        <?= $totalBookings ?>
                    </span>

                <?php endif; ?>

            </a>


            <!-- PROFILE -->

            <a
                href="farmer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">
                    <?= e($farmerInitial) ?>
                </span>

                <span class="profile-name">
                    <?= e($farmerName) ?>
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

<main class="bookings-page">

    <div class="container">


        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <section class="page-header">

            <div>

                <span class="page-tag">
                    FUTURE HARVEST MANAGEMENT
                </span>

                <h1>
                    Harvest Bookings
                </h1>

                <p>
                    Manage your upcoming harvests and see how much
                    produce has already been reserved by consumers.
                </p>

            </div>


            <a
                href="add-product.php"
                class="add-harvest-btn"
            >
                + Add Future Harvest
            </a>

        </section>



        <!-- =====================================================
             SUMMARY CARDS
        ====================================================== -->

        <section class="summary-grid">


            <!-- TOTAL -->

            <div class="summary-card">

                <div class="summary-icon">
                    🌾
                </div>

                <div>

                    <span>
                        Total Harvests
                    </span>

                    <strong>
                        <?= $totalHarvests ?>
                    </strong>

                </div>

            </div>


            <!-- OPEN -->

            <div class="summary-card">

                <div class="summary-icon">
                    🌱
                </div>

                <div>

                    <span>
                        Open for Booking
                    </span>

                    <strong>
                        <?= $openHarvests ?>
                    </strong>

                </div>

            </div>


            <!-- FULL -->

            <div class="summary-card">

                <div class="summary-icon">
                    📦
                </div>

                <div>

                    <span>
                        Fully Booked
                    </span>

                    <strong>
                        <?= $fullyBookedHarvests ?>
                    </strong>

                </div>

            </div>


            <!-- BOOKINGS -->

            <div class="summary-card">

                <div class="summary-icon">
                    📋
                </div>

                <div>

                    <span>
                        Total Bookings
                    </span>

                    <strong>
                        <?= $totalBookings ?>
                    </strong>

                </div>

            </div>

        </section>



        <!-- =====================================================
             FUTURE HARVEST LISTINGS
        ====================================================== -->

        <section class="harvest-section">


            <div class="section-heading">

                <div>

                    <span>
                        YOUR FUTURE HARVESTS
                    </span>

                    <h2>
                        Upcoming Harvest Listings
                    </h2>

                </div>

                <a href="add-product.php">
                    + Add Harvest
                </a>

            </div>



            <?php if (empty($futureHarvests)): ?>

                <!-- EMPTY STATE -->

                <div class="empty-state">

                    <div class="empty-icon">
                        🌱
                    </div>

                    <h3>
                        No Future Harvests Yet
                    </h3>

                    <p>
                        You haven't added any future harvest products.
                        Add a future harvest so consumers can pre-book
                        your produce before harvest.
                    </p>

                    <a
                        href="add-product.php"
                        class="empty-button"
                    >
                        + Add Future Harvest
                    </a>

                </div>


            <?php else: ?>


                <div class="harvest-grid">


                    <?php foreach ($futureHarvests as $harvest): ?>

                        <?php

                        $expectedQuantity =
                            (float) $harvest["expected_quantity"];

                        $prebookQuantity =
                            (float) $harvest["prebook_quantity"];

                        $bookedQuantity =
                            (float) $harvest["booked_quantity"];

                        /*
                         * Available quantity for booking.
                         *
                         * We use the pre-book quantity as the
                         * maximum quantity consumers can reserve.
                         */

                        $availableForBooking =
                            max(
                                0,
                                $prebookQuantity - $bookedQuantity
                            );


                        if ($prebookQuantity > 0) {

                            $bookingProgress =
                                ($bookedQuantity / $prebookQuantity) * 100;

                        } else {

                            $bookingProgress = 0;

                        }


                        $bookingProgress =
                            min(100, max(0, $bookingProgress));


                        $harvestDate =
                            strtotime($harvest["harvest_date"]);

                        $daysRemaining =
                            floor(
                                (
                                    $harvestDate - time()
                                ) / 86400
                            );


                        if ($daysRemaining < 0) {

                            $daysLabel = "Harvest date passed";

                        } elseif ($daysRemaining == 0) {

                            $daysLabel = "Harvesting today";

                        } elseif ($daysRemaining == 1) {

                            $daysLabel = "1 day remaining";

                        } else {

                            $daysLabel =
                                $daysRemaining .
                                " days remaining";

                        }


                        ?>


                        <!-- HARVEST CARD -->

                        <article class="harvest-card">


                            <!-- IMAGE -->

                            <div class="harvest-image">

                                <?php if (
                                    !empty($harvest["image"])
                                ): ?>

                                    <img
                                        src="uploads/products/<?= e($harvest["image"]) ?>"
                                        alt="<?= e($harvest["product_name"]) ?>"
                                    >

                                <?php else: ?>

                                    <div class="image-placeholder">
                                        🌾
                                    </div>

                                <?php endif; ?>


                                <span
                                    class="harvest-status <?= harvestStatusClass($harvest["status"]) ?>"
                                >
                                    <?= e(
                                        ucwords(
                                            str_replace(
                                                "_",
                                                " ",
                                                $harvest["status"]
                                            )
                                        )
                                    ) ?>
                                </span>

                            </div>



                            <!-- CARD CONTENT -->

                            <div class="harvest-content">


                                <div class="harvest-category">

                                    <?= e($harvest["category"]) ?>

                                </div>


                                <h3>
                                    <?= e($harvest["product_name"]) ?>
                                </h3>


                                <?php if (
                                    !empty($harvest["description"])
                                ): ?>

                                    <p class="harvest-description">

                                        <?= e(
                                            $harvest["description"]
                                        ) ?>

                                    </p>

                                <?php endif; ?>



                                <!-- PRICE -->

                                <div class="harvest-price">

                                    <strong>
                                        ৳<?= number_format(
                                            (float) $harvest["price"],
                                            2
                                        ) ?>
                                    </strong>

                                    <span>
                                        / <?= e($harvest["unit"]) ?>
                                    </span>

                                </div>



                                <!-- HARVEST DATE -->

                                <div class="harvest-info-row">

                                    <span>
                                        📅 Harvest Date
                                    </span>

                                    <strong>

                                        <?= date(
                                            "d M Y",
                                            $harvestDate
                                        ) ?>

                                    </strong>

                                </div>


                                <div class="days-remaining">

                                    ⏳ <?= e($daysLabel) ?>

                                </div>



                                <!-- LOCATION -->

                                <?php if (
                                    !empty($harvest["location"])
                                ): ?>

                                    <div class="harvest-info-row">

                                        <span>
                                            📍 Location
                                        </span>

                                        <strong>
                                            <?= e(
                                                $harvest["location"]
                                            ) ?>
                                        </strong>

                                    </div>

                                <?php endif; ?>



                                <!-- QUANTITY -->

                                <div class="quantity-section">

                                    <div class="quantity-heading">

                                        <span>
                                            Pre-booking Progress
                                        </span>

                                        <strong>

                                            <?= number_format(
                                                $bookedQuantity,
                                                2
                                            ) ?>

                                            /

                                            <?= number_format(
                                                $prebookQuantity,
                                                2
                                            ) ?>

                                            <?= e(
                                                $harvest["unit"]
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div class="progress-bar">

                                        <div
                                            class="progress-fill"
                                            style="width: <?= round($bookingProgress) ?>%;"
                                        ></div>

                                    </div>


                                    <div class="quantity-bottom">

                                        <span>

                                            Expected:
                                            <?= number_format(
                                                $expectedQuantity,
                                                2
                                            ) ?>

                                            <?= e(
                                                $harvest["unit"]
                                            ) ?>

                                        </span>


                                        <span>

                                            Available:
                                            <?= number_format(
                                                $availableForBooking,
                                                2
                                            ) ?>

                                            <?= e(
                                                $harvest["unit"]
                                            ) ?>

                                        </span>

                                    </div>

                                </div>



                                <!-- MINIMUM BOOKING -->

                                <div class="minimum-booking">

                                    Minimum booking:

                                    <strong>

                                        <?= number_format(
                                            (float) $harvest["minimum_booking"],
                                            2
                                        ) ?>

                                        <?= e(
                                            $harvest["unit"]
                                        ) ?>

                                    </strong>

                                </div>



                                <!-- BOOKINGS -->

                                <div class="booking-count">

                                    📋

                                    <strong>
                                        <?= (int) $harvest["booking_count"] ?>
                                    </strong>

                                    booking(s)

                                </div>

                            </div>

                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>

        </section>



        <!-- =====================================================
             CONSUMER BOOKINGS
        ====================================================== -->

           <?php if (false): ?>

        <section class="booking-list-section">


            <div class="section-heading">

                <div>

                    <span>
                        CONSUMER RESERVATIONS
                    </span>

                    <h2>
                        Recent Harvest Bookings
                    </h2>

                </div>

            </div>



            <?php if (empty($bookings)): ?>

                <div class="empty-bookings">

                    <div class="empty-icon">
                        📋
                    </div>

                    <h3>
                        No Consumer Bookings Yet
                    </h3>

                    <p>
                        When consumers reserve your future harvest,
                        their bookings will appear here.
                    </p>

                </div>


            <?php else: ?>


                <div class="booking-table-wrapper">

                    <table class="booking-table">

                        <thead>

                            <tr>

                                <th>
                                    Consumer
                                </th>

                                <th>
                                    Harvest
                                </th>

                                <th>
                                    Quantity
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Harvest Date
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Booked On
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                            <?php foreach ($bookings as $booking): ?>

                                <tr>


                                    <!-- CONSUMER -->

                                    <td>

                                        <div class="consumer-cell">

                                            <div class="consumer-avatar">

                                                <?= e(
                                                    strtoupper(
                                                        substr(
                                                            trim(
                                                                $booking["consumer_name"]
                                                            ),
                                                            0,
                                                            1
                                                        )
                                                    )
                                                ) ?>

                                            </div>


                                            <div>

                                                <strong>

                                                    <?= e(
                                                        $booking["consumer_name"]
                                                    ) ?>

                                                </strong>

                                                <small>

                                                    <?= e(
                                                        $booking["consumer_email"]
                                                    ) ?>

                                                </small>

                                            </div>

                                        </div>

                                    </td>



                                    <!-- HARVEST -->

                                    <td>

                                        <strong>
                                            <?= e(
                                                $booking["product_name"]
                                            ) ?>
                                        </strong>

                                    </td>



                                    <!-- QUANTITY -->

                                    <td>

                                        <?= number_format(
                                            (float) $booking["quantity"],
                                            2
                                        ) ?>

                                        <?= e(
                                            $booking["unit"]
                                        ) ?>

                                    </td>



                                    <!-- TOTAL -->

                                    <td>

                                        <strong class="booking-total">

                                            ৳<?= number_format(
                                                (float) $booking["total_amount"],
                                                2
                                            ) ?>

                                        </strong>

                                    </td>



                                    <!-- HARVEST DATE -->

                                    <td>

                                        <?= date(
                                            "d M Y",
                                            strtotime(
                                                $booking["harvest_date"]
                                            )
                                        ) ?>

                                    </td>



                                    <!-- STATUS -->

                                    <td>

                                        <span
                                            class="booking-status <?= bookingStatusClass($booking["status"]) ?>"
                                        >

                                            <?= e(
                                                ucfirst(
                                                    $booking["status"]
                                                )
                                            ) ?>

                                        </span>

                                    </td>



                                    <!-- BOOKED DATE -->

                                    <td>

                                        <?= date(
                                            "d M Y",
                                            strtotime(
                                                $booking["created_at"]
                                            )
                                        ) ?>

                                    </td>


                                </tr>

                            <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


            <?php endif; ?>


        </section>

        <?php endif; ?>


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

            <a href="farmer-bookings.php">
                Harvest Bookings
            </a>

            <a href="farmer-demands.php">
                Demand Broadcasts
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


</body>

</html>