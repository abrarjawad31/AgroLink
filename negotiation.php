<?php

require_once "auth.php";
requireConsumer();

require_once "db.php";

$consumerId = (int) $_SESSION["user_id"];
$consumerName = $_SESSION["user_name"] ?? "Consumer";

$message = "";
$messageType = "";

$offerId = (int) ($_GET["offer"] ?? $_POST["offer_id"] ?? 0);
$action = $_GET["action"] ?? "";

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function formatDateValue($date)
{
    if (!$date) {
        return "Not specified";
    }

    return date("d M Y", strtotime($date));
}

/*
|--------------------------------------------------------------------------
| Validate Offer ID
|--------------------------------------------------------------------------
*/

if ($offerId <= 0) {
    header("Location: consumer-demands.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| ACCEPT OFFER
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "GET" && $action === "accept") {

    $acceptStmt = $conn->prepare("
        SELECT
            do.id,
            do.demand_id,
            do.status,
            d.consumer_id,
            d.status AS demand_status
        FROM demand_offers do
        INNER JOIN demands d
            ON do.demand_id = d.id
        WHERE do.id = ?
          AND d.consumer_id = ?
        LIMIT 1
    ");

    $acceptStmt->bind_param(
        "ii",
        $offerId,
        $consumerId
    );

    $acceptStmt->execute();

    $acceptResult = $acceptStmt->get_result();
    $offer = $acceptResult->fetch_assoc();

    $acceptStmt->close();

    if (!$offer) {

        $message = "The offer could not be found.";
        $messageType = "error";

    } elseif (
        $offer["status"] !== "pending" &&
        $offer["status"] !== "countered"
    ) {

        $message = "This offer can no longer be accepted.";
        $messageType = "error";

    } else {

        $updateOffer = $conn->prepare("
            UPDATE demand_offers
            SET status = 'accepted'
            WHERE id = ?
              AND status IN ('pending', 'countered')
        ");

        $updateOffer->bind_param(
            "i",
            $offerId
        );

        if (
            $updateOffer->execute() &&
            $updateOffer->affected_rows > 0
        ) {

            $updateDemand = $conn->prepare("
                UPDATE demands
                SET status = 'fulfilled'
                WHERE id = ?
            ");

            $updateDemand->bind_param(
                "i",
                $offer["demand_id"]
            );

            $updateDemand->execute();
            $updateDemand->close();

            $message = "Offer accepted successfully. The booking has been confirmed.";
            $messageType = "success";

        } else {

            $message = "Unable to accept this offer.";
            $messageType = "error";
        }

        $updateOffer->close();
    }
}

/*
|--------------------------------------------------------------------------
| SUBMIT COUNTER OFFER
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["submit_counter"])
) {

    $offerId = (int) ($_POST["offer_id"] ?? 0);

    $counterPrice = (float) ($_POST["counter_price"] ?? 0);
    $counterQuantity = (float) ($_POST["counter_quantity"] ?? 0);
    $counterUnit = trim($_POST["counter_unit"] ?? "kg");
    $counterDeliveryDate = trim(
        $_POST["counter_delivery_date"] ?? ""
    );
    $counterMessage = trim(
        $_POST["counter_message"] ?? ""
    );

    if (
        $offerId <= 0 ||
        $counterPrice <= 0 ||
        $counterQuantity <= 0
    ) {

        $message = "Please enter a valid counter-offer price and quantity.";
        $messageType = "error";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Fetch Existing Offer
        |--------------------------------------------------------------------------
        */

        $offerStmt = $conn->prepare("
            SELECT
                do.id,
                do.demand_id,
                do.farmer_id,
                do.offer_price,
                do.quantity,
                do.unit,
                do.delivery_date,
                do.message,
                do.status,

                d.consumer_id,
                d.quantity AS requested_quantity,
                d.unit AS requested_unit,
                d.status AS demand_status

            FROM demand_offers do

            INNER JOIN demands d
                ON do.demand_id = d.id

            WHERE do.id = ?
              AND d.consumer_id = ?

            LIMIT 1
        ");

        $offerStmt->bind_param(
            "ii",
            $offerId,
            $consumerId
        );

        $offerStmt->execute();

        $offerResult = $offerStmt->get_result();
        $currentOffer = $offerResult->fetch_assoc();

        $offerStmt->close();

        if (!$currentOffer) {

            $message = "The offer could not be found.";
            $messageType = "error";

        } elseif (
            $currentOffer["status"] !== "pending" &&
            $currentOffer["status"] !== "countered"
        ) {

            $message = "This offer is no longer available for negotiation.";
            $messageType = "error";

        } elseif (
            $counterQuantity >
            (float) $currentOffer["requested_quantity"]
        ) {

            $message = "Counter-offer quantity cannot exceed the requested quantity.";
            $messageType = "error";

        } else {

            if ($counterDeliveryDate === "") {
                $counterDeliveryDate = null;
            }

            /*
            |--------------------------------------------------------------------------
            | Store Counter Offer
            |--------------------------------------------------------------------------
            */

            $counterText = "Consumer counter-offer";

            if ($counterMessage !== "") {
                $counterText .= ": " . $counterMessage;
            }

            $counterStmt = $conn->prepare("
                UPDATE demand_offers
                SET
                    offer_price = ?,
                    quantity = ?,
                    unit = ?,
                    delivery_date = ?,
                    message = ?,
                    status = 'countered'
                WHERE id = ?
            ");

            $counterStmt->bind_param(
                "ddsssi",
                $counterPrice,
                $counterQuantity,
                $counterUnit,
                $counterDeliveryDate,
                $counterText,
                $offerId
            );

            if ($counterStmt->execute()) {

                $updateDemand = $conn->prepare("
                    UPDATE demands
                    SET status = 'negotiating'
                    WHERE id = ?
                ");

                $updateDemand->bind_param(
                    "i",
                    $currentOffer["demand_id"]
                );

                $updateDemand->execute();
                $updateDemand->close();

                $message = "Your counter-offer has been submitted successfully.";
                $messageType = "success";

            } else {

                $message = "Something went wrong while submitting your counter-offer.";
                $messageType = "error";
            }

            $counterStmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| FETCH CURRENT OFFER
|--------------------------------------------------------------------------
*/

$offerStmt = $conn->prepare("
    SELECT
        do.id,
        do.demand_id,
        do.farmer_id,
        do.offer_price,
        do.quantity,
        do.unit,
        do.delivery_date,
        do.message,
        do.status,
        do.created_at,
        do.updated_at,

        d.crop_name,
        d.category,
        d.quantity AS requested_quantity,
        d.unit AS requested_unit,
        d.target_price,
        d.delivery_by,
        d.location,
        d.description,
        d.status AS demand_status,

        u.name AS farmer_name,
        u.phone AS farmer_phone

    FROM demand_offers do

    INNER JOIN demands d
        ON do.demand_id = d.id

    INNER JOIN users u
        ON do.farmer_id = u.id

    WHERE do.id = ?
      AND d.consumer_id = ?

    LIMIT 1
");

$offerStmt->bind_param(
    "ii",
    $offerId,
    $consumerId
);

$offerStmt->execute();

$offerResult = $offerStmt->get_result();
$offerData = $offerResult->fetch_assoc();

$offerStmt->close();

if (!$offerData) {
    header("Location: consumer-demands.php");
    exit;
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
        Negotiate Offer | AgroLink
    </title>

    <!-- Main AgroLink CSS -->
    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <!-- Negotiation Page CSS -->
    <link
        rel="stylesheet"
        href="css/negotiation.css"
    >

</head>

<body>

<!-- =========================================================
     NAVBAR
========================================================= -->

<header class="navbar">

    <div class="nav-container">

        <a
            href="consumer-dashboard.php"
            class="logo"
        >
            AgroLink
        </a>

        <nav class="nav-links">

            <a href="consumer-dashboard.php">
                Home
            </a>

            <a href="marketplace.php">
                Marketplace
            </a>

            <a href="future-harvests.php">
                Future Harvests
            </a>

            <a
                href="consumer-demands.php"
                class="active"
            >
                Demand Hub
            </a>

            <a href="my-orders.php">
                My Orders
            </a>

        </nav>

        <div class="nav-profile">

            <a href="consumer-profile.php">
                <?php echo e($consumerName); ?>
            </a>

            <a href="logout.php">
                Logout
            </a>

        </div>

    </div>

</header>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="negotiation-container">

    <div class="page-header">

        <span class="module-label">
            DEMAND-BASED TRADING
        </span>

        <h1>
            Negotiate Offer
        </h1>

        <p>
            Review the farmer's offer and submit a counter-offer
            if you want to negotiate the price or quantity.
        </p>

    </div>


    <!-- ALERT -->

    <?php if ($message !== ""): ?>

        <div class="negotiation-alert <?php echo e($messageType); ?>">

            <span class="alert-icon">

                <?php if ($messageType === "success"): ?>

                    ✓

                <?php else: ?>

                    !

                <?php endif; ?>

            </span>

            <span>
                <?php echo e($message); ?>
            </span>

        </div>

    <?php endif; ?>


    <div class="negotiation-grid">


        <!-- =================================================
             LEFT COLUMN
        ================================================== -->

        <div class="left-column">


            <!-- DEMAND DETAILS -->

            <section class="negotiation-card">

                <div class="card-heading">

                    <div>

                        <span class="card-kicker">
                            YOUR REQUEST
                        </span>

                        <h2>
                            Demand Details
                        </h2>

                    </div>

                    <span class="demand-status">
                        <?php echo e(
                            ucfirst($offerData["demand_status"])
                        ); ?>
                    </span>

                </div>


                <div class="info-list">

                    <div class="info-row">

                        <span class="info-label">
                            Crop
                        </span>

                        <span class="info-value">
                            <?php echo e(
                                $offerData["crop_name"]
                            ); ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Category
                        </span>

                        <span class="info-value">
                            <?php echo e(
                                $offerData["category"]
                            ); ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Requested Quantity
                        </span>

                        <span class="info-value">
                            <?php echo e(
                                $offerData["requested_quantity"]
                                . " "
                                . $offerData["requested_unit"]
                            ); ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Target Price
                        </span>

                        <span class="info-value price-text">

                            <?php if (
                                $offerData["target_price"] !== null
                            ): ?>

                                ৳<?php echo number_format(
                                    (float) $offerData["target_price"],
                                    2
                                ); ?>

                            <?php else: ?>

                                Not specified

                            <?php endif; ?>

                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Delivery By
                        </span>

                        <span class="info-value">
                            <?php echo e(
                                formatDateValue(
                                    $offerData["delivery_by"]
                                )
                            ); ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Location
                        </span>

                        <span class="info-value">
                            <?php echo e(
                                $offerData["location"]
                                ?: "Not specified"
                            ); ?>
                        </span>

                    </div>

                </div>


                <?php if (
                    !empty($offerData["description"])
                ): ?>

                    <div class="description-box">

                        <span>
                            Demand Description
                        </span>

                        <p>
                            <?php echo nl2br(
                                e($offerData["description"])
                            ); ?>
                        </p>

                    </div>

                <?php endif; ?>

            </section>


            <!-- FARMER OFFER -->

            <section class="negotiation-card farmer-offer-card">

                <div class="card-heading">

                    <div>

                        <span class="card-kicker">
                            FARMER RESPONSE
                        </span>

                        <h2>
                            Farmer's Offer
                        </h2>

                    </div>

                    <span class="offer-status status-<?php echo e(
                        $offerData["status"]
                    ); ?>">

                        <?php echo e(
                            ucfirst($offerData["status"])
                        ); ?>

                    </span>

                </div>


                <div class="farmer-name">

                    <div class="farmer-avatar">
                        <?php echo strtoupper(
                            substr(
                                $offerData["farmer_name"],
                                0,
                                1
                            )
                        ); ?>
                    </div>

                    <div>

                        <strong>
                            <?php echo e(
                                $offerData["farmer_name"]
                            ); ?>
                        </strong>

                        <span>
                            Verified Farmer
                        </span>

                    </div>

                </div>


                <div class="offer-highlight">

                    <span>
                        Offered Price
                    </span>

                    <strong>
                        ৳<?php echo number_format(
                            (float) $offerData["offer_price"],
                            2
                        ); ?>
                    </strong>

                </div>


                <div class="info-list">

                    <div class="info-row">

                        <span class="info-label">
                            Quantity
                        </span>

                        <span class="info-value">
                            <?php echo e(
                                $offerData["quantity"]
                                . " "
                                . $offerData["unit"]
                            ); ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Delivery Date
                        </span>

                        <span class="info-value">
                            <?php echo e(
                                formatDateValue(
                                    $offerData["delivery_date"]
                                )
                            ); ?>
                        </span>

                    </div>

                </div>


                <?php if (
                    !empty($offerData["message"])
                ): ?>

                    <div class="message-box">

                        <span>
                            Farmer's Message
                        </span>

                        <p>
                            <?php echo nl2br(
                                e($offerData["message"])
                            ); ?>
                        </p>

                    </div>

                <?php endif; ?>

            </section>

        </div>


        <!-- =================================================
             RIGHT COLUMN
        ================================================== -->

        <div class="right-column">


            <!-- COUNTER OFFER -->

            <section class="negotiation-card counter-card">

                <div class="card-heading">

                    <div>

                        <span class="card-kicker">
                            NEGOTIATION
                        </span>

                        <h2>
                            Make a Counter-Offer
                        </h2>

                    </div>

                    <span class="negotiation-icon">
                        💬
                    </span>

                </div>


                <?php if (
                    $offerData["status"] === "pending" ||
                    $offerData["status"] === "countered"
                ): ?>


                    <form
                        method="POST"
                        action="negotiation.php?offer=<?php echo $offerId; ?>"
                        class="counter-form"
                    >

                        <input
                            type="hidden"
                            name="offer_id"
                            value="<?php echo $offerId; ?>"
                        >


                        <div class="form-group">

                            <label for="counter_price">
                                Your Price
                                <span>৳</span>
                            </label>

                            <div class="input-with-prefix">

                                <span>
                                    ৳
                                </span>

                                <input
                                    type="number"
                                    id="counter_price"
                                    name="counter_price"
                                    min="0.01"
                                    step="0.01"
                                    value="<?php echo e(
                                        $offerData["offer_price"]
                                    ); ?>"
                                    required
                                >

                            </div>

                        </div>


                        <div class="form-row">

                            <div class="form-group">

                                <label for="counter_quantity">
                                    Quantity
                                </label>

                                <input
                                    type="number"
                                    id="counter_quantity"
                                    name="counter_quantity"
                                    min="0.01"
                                    step="0.01"
                                    max="<?php echo e(
                                        $offerData["requested_quantity"]
                                    ); ?>"
                                    value="<?php echo e(
                                        $offerData["quantity"]
                                    ); ?>"
                                    required
                                >

                            </div>


                            <div class="form-group">

                                <label for="counter_unit">
                                    Unit
                                </label>

                                <select
                                    id="counter_unit"
                                    name="counter_unit"
                                    required
                                >

                                    <option
                                        value="kg"
                                        <?php
                                        echo $offerData["unit"] === "kg"
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        kg
                                    </option>

                                    <option
                                        value="Mon"
                                        <?php
                                        echo $offerData["unit"] === "Mon"
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        Mon (40 kg)
                                    </option>

                                    <option
                                        value="Ton"
                                        <?php
                                        echo $offerData["unit"] === "Ton"
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        Ton
                                    </option>

                                    <option
                                        value="Pieces / Bundles"
                                        <?php
                                        echo $offerData["unit"] === "Pieces / Bundles"
                                            ? "selected"
                                            : "";
                                        ?>
                                    >
                                        Pieces / Bundles
                                    </option>

                                </select>

                            </div>

                        </div>


                        <div class="form-group">

                            <label for="counter_delivery_date">
                                Preferred Delivery Date
                            </label>

                            <input
                                type="date"
                                id="counter_delivery_date"
                                name="counter_delivery_date"
                                value="<?php echo e(
                                    $offerData["delivery_date"]
                                ); ?>"
                            >

                        </div>


                        <div class="form-group">

                            <label for="counter_message">
                                Message
                            </label>

                            <textarea
                                id="counter_message"
                                name="counter_message"
                                placeholder="Write a message to the farmer..."
                            ></textarea>

                        </div>


                        <button
                            type="submit"
                            name="submit_counter"
                            class="counter-submit-btn"
                        >
                            Submit Counter-Offer
                            <span>→</span>
                        </button>

                    </form>


                    <div class="negotiation-tip">

                        <div class="tip-icon">
                            💡
                        </div>

                        <div>

                            <strong>
                                Negotiation Tip
                            </strong>

                            <p>
                                You can change the price, quantity,
                                delivery date, or add a message before
                                sending your counter-offer.
                            </p>

                        </div>

                    </div>


                <?php elseif (
                    $offerData["status"] === "accepted"
                ): ?>

                    <div class="completed-state accepted-state">

                        <div class="completed-icon">
                            ✓
                        </div>

                        <h3>
                            Offer Accepted
                        </h3>

                        <p>
                            You have accepted this farmer's offer.
                            Your demand has been successfully fulfilled.
                        </p>

                    </div>


                <?php elseif (
                    $offerData["status"] === "rejected"
                ): ?>

                    <div class="completed-state rejected-state">

                        <div class="completed-icon">
                            ×
                        </div>

                        <h3>
                            Offer Rejected
                        </h3>

                        <p>
                            This offer has been rejected and is no
                            longer available for negotiation.
                        </p>

                    </div>

                <?php endif; ?>


                <div class="back-link-wrapper">

                    <a
                        href="consumer-demands.php"
                        class="back-link"
                    >
                        ← Back to Demand Hub
                    </a>

                </div>

            </section>

        </div>

    </div>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer>

    <div class="footer-container">

        <p>
            © <?php echo date("Y"); ?> AgroLink.
            Connecting Farmers and Consumers.
        </p>

    </div>

</footer>

</body>

</html>