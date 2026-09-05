<?php

require_once "auth.php";
require_once "db.php";

requireConsumer();

$consumerId = (int) $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Helper
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

/*
|--------------------------------------------------------------------------
| Current Consumer
|--------------------------------------------------------------------------
*/

$consumerName = "Consumer";

$stmt = $conn->prepare("
    SELECT name
    FROM users
    WHERE id = ?
      AND role = 'consumer'
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("i", $consumerId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $consumerName = $row["name"] ?: "Consumer";
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Avatar
|--------------------------------------------------------------------------
*/

$avatarLetter = strtoupper(
    substr(trim($consumerName), 0, 1)
);

if ($avatarLetter === "") {
    $avatarLetter = "C";
}

/*
|--------------------------------------------------------------------------
| Cart Count
|--------------------------------------------------------------------------
*/

$cartCount = 0;

$stmt = $conn->prepare("
    SELECT COALESCE(SUM(ci.quantity), 0) AS cart_count
    FROM cart c
    LEFT JOIN cart_items ci
        ON ci.cart_id = c.id
    WHERE c.consumer_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $consumerId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $cartCount = (int) $row["cart_count"];
    }

    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| Form Variables
|--------------------------------------------------------------------------
*/

$cropName = "";
$category = "";
$quantity = "";
$unit = "kg";
$targetPrice = "";
$deadline = "";
$location = "";
$notes = "";

$errorMessage = "";
$isEditMode = false;
$editDemandId = 0;

/*
|--------------------------------------------------------------------------
| Detect Edit Mode
|--------------------------------------------------------------------------
*/

if (isset($_GET["edit"])) {

    $editDemandId = (int) $_GET["edit"];

    if ($editDemandId > 0) {

        $stmt = $conn->prepare("
            SELECT
                id,
                crop_name,
                category,
                quantity,
                unit,
                target_price,
                delivery_by,
                location,
                description,
                status
            FROM demands
            WHERE id = ?
              AND consumer_id = ?
            LIMIT 1
        ");

        if ($stmt) {

            $stmt->bind_param(
                "ii",
                $editDemandId,
                $consumerId
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($demand = $result->fetch_assoc()) {

                if (
                    $demand["status"] === "open" ||
                    $demand["status"] === "negotiating"
                ) {

                    $isEditMode = true;

                    $cropName = $demand["crop_name"];
                    $category = $demand["category"];
                    $quantity = $demand["quantity"];
                    $unit = $demand["unit"];
                    $targetPrice = $demand["target_price"];
                    $deadline = $demand["delivery_by"];
                    $location = $demand["location"];
                    $notes = $demand["description"];

                } else {

                    $errorMessage =
                        "This demand can no longer be edited.";

                }

            } else {

                $errorMessage =
                    "Demand request not found.";

            }

            $stmt->close();
        }
    }
}

/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $cropName = trim($_POST["crop_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $quantity = trim($_POST["quantity"] ?? "");
    $unit = trim($_POST["unit"] ?? "");
    $targetPrice = trim($_POST["target_price"] ?? "");
    $deadline = trim($_POST["deadline"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $notes = trim($_POST["notes"] ?? "");

    $submittedEditId = (int) ($_POST["edit_id"] ?? 0);

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($cropName === "") {

        $errorMessage = "Please enter the crop or product name.";

    } elseif ($category === "") {

        $errorMessage = "Please select a category.";

    } elseif ($quantity === "" || !is_numeric($quantity) || $quantity <= 0) {

        $errorMessage = "Please enter a valid required quantity.";

    } elseif ($unit === "") {

        $errorMessage = "Please select a measurement unit.";

    } elseif (
        $targetPrice === "" ||
        !is_numeric($targetPrice) ||
        $targetPrice < 0
    ) {

        $errorMessage = "Please enter a valid target price.";

    } elseif ($deadline === "") {

        $errorMessage = "Please select a delivery date.";

    } elseif ($location === "") {

        $errorMessage = "Please select a delivery region.";

    }

    /*
    |--------------------------------------------------------------------------
    | Validate Date
    |--------------------------------------------------------------------------
    */

    if ($errorMessage === "") {

        $deadlineTimestamp = strtotime($deadline);

        if ($deadlineTimestamp === false) {

            $errorMessage =
                "Please select a valid delivery date.";

        } elseif ($deadlineTimestamp < strtotime(date("Y-m-d"))) {

            $errorMessage =
                "Delivery date cannot be in the past.";

        }
    }

    /*
    |--------------------------------------------------------------------------
    | Convert Numeric Values
    |--------------------------------------------------------------------------
    */

    if ($errorMessage === "") {

        $quantityValue = (float) $quantity;
        $targetPriceValue = (float) $targetPrice;

        /*
        |--------------------------------------------------------------------------
        | EDIT EXISTING DEMAND
        |--------------------------------------------------------------------------
        */

        if ($submittedEditId > 0) {

            $stmt = $conn->prepare("
                UPDATE demands
                SET
                    crop_name = ?,
                    category = ?,
                    quantity = ?,
                    unit = ?,
                    target_price = ?,
                    delivery_by = ?,
                    location = ?,
                    description = ?
                WHERE id = ?
                  AND consumer_id = ?
                  AND status IN ('open', 'negotiating')
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "ssdssdssii",
                    $cropName,
                    $category,
                    $quantityValue,
                    $unit,
                    $targetPriceValue,
                    $deadline,
                    $location,
                    $notes,
                    $submittedEditId,
                    $consumerId
                );

                if ($stmt->execute()) {

                    $stmt->close();

                    header(
                        "Location: consumer-demands.php?updated=1"
                    );

                    exit;

                } else {

                    $errorMessage =
                        "Unable to update the demand request.";
                }

                $stmt->close();

            } else {

                $errorMessage =
                    "Unable to prepare the update request.";
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | CREATE NEW DEMAND
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare("
                INSERT INTO demands (
                    consumer_id,
                    crop_name,
                    category,
                    quantity,
                    unit,
                    target_price,
                    delivery_by,
                    location,
                    description,
                    status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'open')
            ");

            if ($stmt) {

                $stmt->bind_param(
                    "issdsdsss",
                    $consumerId,
                    $cropName,
                    $category,
                    $quantityValue,
                    $unit,
                    $targetPriceValue,
                    $deadline,
                    $location,
                    $notes
                );

                if ($stmt->execute()) {

                    $newDemandId = $stmt->insert_id;

                    $stmt->close();

                    header(
                        "Location: consumer-demands.php?posted=1"
                    );

                    exit;

                } else {

                    $errorMessage =
                        "Unable to broadcast your demand request.";
                }

                $stmt->close();

            } else {

                $errorMessage =
                    "Unable to prepare the demand request.";
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Page Title
|--------------------------------------------------------------------------
*/

$pageTitle = $isEditMode
    ? "Edit Demand Request | AgroLink"
    : "Post Crop Demand Request | AgroLink";

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
        <?= e($pageTitle) ?>
    </title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/post-demand.css"
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

            <a href="future-harvests.php">
                Pre Bookings
            </a>

            <a
                href="consumer-demands.php"
                class="active"
            >
                My Demands
            </a>

            <a href="my-orders.php">
                My Orders
            </a>

        </nav>


        <!-- CONSUMER ACTIONS -->

        <div class="consumer-actions">

            <a
                href="cart.php"
                class="cart-link"
            >

                <span class="cart-icon">
                    🛒
                </span>

                <span>
                    Cart
                </span>

                <span class="cart-count">
                    <?= $cartCount ?>
                </span>

            </a>


            <a
                href="consumer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">
                    <?= e($avatarLetter) ?>
                </span>

                <span class="profile-name">
                    <?= e($consumerName) ?>
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
     MAIN
========================================================= -->

<main class="post-demand-page">

    <div class="container">

        <div class="demand-form-card">

            <!-- HEADER -->

            <div class="demand-header">

                <span class="page-tag">
                    SRS MODULE 3.5: DEMAND-BASED TRADING (FR-20)
                </span>

                <h1>

                    <?php if ($isEditMode): ?>

                        Edit Purchasing Demand Request

                    <?php else: ?>

                        Broadcast a Purchasing Demand Request

                    <?php endif; ?>

                </h1>

                <p>
                    Specify the agricultural products you need in
                    bulk or customized quantities. Local farmers
                    can review your demand and submit competitive
                    fulfillment offers.
                </p>

            </div>


            <!-- ERROR -->

            <?php if ($errorMessage !== ""): ?>

                <div class="form-message error-message">

                    <span class="message-icon">
                        ⚠
                    </span>

                    <span>
                        <?= e($errorMessage) ?>
                    </span>

                </div>

            <?php endif; ?>


            <!-- FORM -->

            <form
                action="post-demand.php<?= $isEditMode ? '?edit=' . $editDemandId : '' ?>"
                method="POST"
            >

                <?php if ($isEditMode): ?>

                    <input
                        type="hidden"
                        name="edit_id"
                        value="<?= $editDemandId ?>"
                    >

                <?php endif; ?>


                <div class="demand-grid">

                    <!-- CROP -->

                    <div class="form-group">

                        <label for="crop-name">
                            Crop / Product Required *
                        </label>

                        <input
                            type="text"
                            id="crop-name"
                            name="crop_name"
                            value="<?= e($cropName) ?>"
                            placeholder="e.g. Organic Red Tomatoes, Diamond Potatoes"
                            required
                        >

                    </div>


                    <!-- CATEGORY -->

                    <div class="form-group">

                        <label for="category">
                            Category *
                        </label>

                        <select
                            id="category"
                            name="category"
                            required
                        >

                            <option value="">
                                Select Category
                            </option>

                            <option
                                value="Organic Vegetables"
                                <?= $category === "Organic Vegetables" ? "selected" : "" ?>
                            >
                                Organic Vegetables
                            </option>

                            <option
                                value="Fresh Fruits"
                                <?= $category === "Fresh Fruits" ? "selected" : "" ?>
                            >
                                Fresh Fruits
                            </option>

                            <option
                                value="Grains & Cereals"
                                <?= $category === "Grains & Cereals" ? "selected" : "" ?>
                            >
                                Grains & Cereals
                            </option>

                            <option
                                value="Pulses & Spices"
                                <?= $category === "Pulses & Spices" ? "selected" : "" ?>
                            >
                                Pulses & Spices
                            </option>

                            <option
                                value="Dairy & Honey"
                                <?= $category === "Dairy & Honey" ? "selected" : "" ?>
                            >
                                Dairy & Honey
                            </option>

                        </select>

                    </div>


                    <!-- QUANTITY -->

                    <div class="form-group">

                        <label for="quantity">
                            Required Quantity *
                        </label>

                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            value="<?= e($quantity) ?>"
                            placeholder="e.g. 500"
                            min="0.01"
                            step="0.01"
                            required
                        >

                    </div>


                    <!-- UNIT -->

                    <div class="form-group">

                        <label for="unit">
                            Measurement Unit *
                        </label>

                        <select
                            id="unit"
                            name="unit"
                            required
                        >

                            <option
                                value="kg"
                                <?= $unit === "kg" ? "selected" : "" ?>
                            >
                                kg (Kilogram)
                            </option>

                            <option
                                value="Mon"
                                <?= $unit === "Mon" ? "selected" : "" ?>
                            >
                                Mon (40 kg)
                            </option>

                            <option
                                value="Ton"
                                <?= $unit === "Ton" ? "selected" : "" ?>
                            >
                                Ton
                            </option>

                            <option
                                value="Pieces / Bundles"
                                <?= $unit === "Pieces / Bundles" ? "selected" : "" ?>
                            >
                                Pieces / Bundles
                            </option>

                        </select>

                    </div>


                    <!-- TARGET PRICE -->

                    <div class="form-group">

                        <label for="target-price">
                            Target Budget Price per Unit (৳) *
                        </label>

                        <input
                            type="number"
                            id="target-price"
                            name="target_price"
                            value="<?= e($targetPrice) ?>"
                            placeholder="e.g. 70"
                            min="0"
                            step="0.01"
                            required
                        >

                    </div>


                    <!-- DEADLINE -->

                    <div class="form-group">

                        <label for="deadline">
                            Required Delivery Date *
                        </label>

                        <input
                            type="date"
                            id="deadline"
                            name="deadline"
                            value="<?= e($deadline) ?>"
                            min="<?= date("Y-m-d") ?>"
                            required
                        >

                    </div>

                </div>


                <!-- LOCATION -->

                <div class="form-group full-width-group">

                    <label for="location">
                        Preferred Delivery Region *
                    </label>

                    <select
                        id="location"
                        name="location"
                        required
                    >

                        <option value="">
                            Select Delivery Region
                        </option>

                        <option
                            value="Dhanmondi / Dhaka Metro"
                            <?= $location === "Dhanmondi / Dhaka Metro" ? "selected" : "" ?>
                        >
                            Dhanmondi / Dhaka Metro
                        </option>

                        <option
                            value="Uttara / Gazipur"
                            <?= $location === "Uttara / Gazipur" ? "selected" : "" ?>
                        >
                            Uttara / Gazipur
                        </option>

                        <option
                            value="Chattogram Metro"
                            <?= $location === "Chattogram Metro" ? "selected" : "" ?>
                        >
                            Chattogram Metro
                        </option>

                        <option
                            value="Rajshahi City"
                            <?= $location === "Rajshahi City" ? "selected" : "" ?>
                        >
                            Rajshahi City
                        </option>

                        <option
                            value="Sylhet City"
                            <?= $location === "Sylhet City" ? "selected" : "" ?>
                        >
                            Sylhet City
                        </option>

                    </select>

                </div>


                <!-- NOTES -->

                <div class="form-group notes-group">

                    <label for="notes">
                        Quality Specifications / Notes (Optional)
                    </label>

                    <textarea
                        id="notes"
                        name="notes"
                        rows="4"
                        placeholder="e.g. Must be 100% pesticide-free, size medium to large, fresh harvest preferred..."
                    ><?= e($notes) ?></textarea>

                </div>


                <!-- ACTIONS -->

                <div class="form-actions">

                    <a
                        href="consumer-demands.php"
                        class="cancel-btn"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="post-demand-btn"
                    >

                        <?php if ($isEditMode): ?>

                            Save Demand Changes

                        <?php else: ?>

                            📢 Broadcast Demand Request to Farmers

                        <?php endif; ?>

                    </button>

                </div>

            </form>

        </div>

    </div>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

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

        </div>


        <div class="footer-column">

            <h3>
                Consumer
            </h3>

            <a href="consumer-dashboard.php">
                Dashboard
            </a>

            <a href="marketplace.php">
                Marketplace
            </a>

            <a href="future-harvests.php">
                Future Harvests
            </a>

            <a href="consumer-demands.php">
                Demand Broadcasts
            </a>

        </div>


        <div class="footer-column">

            <h3>
                Account
            </h3>

            <a href="consumer-profile.php">
                My Profile
            </a>

            <a href="my-orders.php">
                My Orders
            </a>

            <a href="cart.php">
                Cart
            </a>

            <a href="logout.php">
                Logout
            </a>

        </div>


        <div class="footer-column">

            <h3>
                Support
            </h3>

            <a href="#">
                Help Center
            </a>

            <a href="#">
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