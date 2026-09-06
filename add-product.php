<?php

require_once "auth.php";
require_once "db.php";

// ============================================================
// ONLY LOGGED-IN FARMERS CAN ACCESS THIS PAGE
// ============================================================

requireFarmer();

$farmer_id = (int) $_SESSION["user_id"];


// ============================================================
// FARMER INFORMATION
// ============================================================

$farmer_name = "Farmer";

$user_stmt = $conn->prepare("
    SELECT name
    FROM users
    WHERE id = ?
      AND role = 'farmer'
    LIMIT 1
");

if ($user_stmt) {

    $user_stmt->bind_param("i", $farmer_id);
    $user_stmt->execute();

    $user_result = $user_stmt->get_result();

    if ($user_row = $user_result->fetch_assoc()) {
        $farmer_name = $user_row["name"];
    }

    $user_stmt->close();
}


// ============================================================
// NOTIFICATION COUNT
// ============================================================

$notification_count = 0;


// ============================================================
// FORM VARIABLES
// ============================================================

// Product information
$product_type = "regular";
$product_name = "";
$category = "";
$subcategory = "";
$description = "";

// Price
$price = "";
$unit = "kg";

// Regular product
$stock = "";

// Future harvest
$harvest_date = "";
$expected_quantity = "";
$prebook_quantity = "";
$minimum_booking = "1";

// Location
$district = "";
$area = "";
$delivery = "home-delivery";

// Regular product availability
$availability = "available";


// ============================================================
// MESSAGES
// ============================================================

$error_message = "";
$success_message = "";


// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ========================================================
    // PRODUCT TYPE
    // ========================================================

    $product_type = $_POST["product_type"] ?? "regular";

    if (!in_array($product_type, ["regular", "future"], true)) {
        $product_type = "regular";
    }


    // ========================================================
    // BASIC INFORMATION
    // ========================================================

    $product_name = trim($_POST["product_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $subcategory = trim($_POST["subcategory"] ?? "");
    $description = trim($_POST["description"] ?? "");


    // ========================================================
    // PRICE
    // ========================================================

    $price = trim($_POST["price"] ?? "");
    $unit = trim($_POST["unit"] ?? "kg");


    // ========================================================
    // REGULAR PRODUCT STOCK
    // ========================================================

    $stock = trim($_POST["stock"] ?? "");


    // ========================================================
    // FUTURE HARVEST INFORMATION
    // ========================================================

    $harvest_date = trim($_POST["harvest_date"] ?? "");
    $expected_quantity = trim($_POST["expected_quantity"] ?? "");
    $prebook_quantity = trim($_POST["prebook_quantity"] ?? "");
    $minimum_booking = trim($_POST["minimum_booking"] ?? "1");


    // ========================================================
    // LOCATION
    // ========================================================

    $district = trim($_POST["district"] ?? "");
    $area = trim($_POST["area"] ?? "");

    $delivery = $_POST["delivery"] ?? "home-delivery";

    if (!in_array(
        $delivery,
        ["home-delivery", "pickup", "both"],
        true
    )) {
        $delivery = "home-delivery";
    }


    // ========================================================
    // REGULAR PRODUCT AVAILABILITY
    // ========================================================

    $availability = $_POST["availability"] ?? "available";

    if (!in_array(
        $availability,
        ["available", "inactive"],
        true
    )) {
        $availability = "available";
    }


    // ========================================================
    // VALIDATION - COMMON FIELDS
    // ========================================================

    if (
        $product_name === "" ||
        $category === "" ||
        $description === "" ||
        $price === "" ||
        $unit === "" ||
        $district === ""
    ) {

        $error_message = "Please fill in all required fields.";

    } elseif (
        !is_numeric($price) ||
        (float) $price < 0
    ) {

        $error_message = "Please enter a valid product price.";

    }


    // ========================================================
    // VALIDATION - PRODUCT TYPE SPECIFIC
    // ========================================================

    if ($error_message === "") {

        if ($product_type === "regular") {

            // ------------------------------------------------
            // REGULAR PRODUCT
            // ------------------------------------------------

            if ($stock === "") {

                $error_message =
                    "Please enter the available stock quantity.";

            } elseif (
                !is_numeric($stock) ||
                (float) $stock < 0
            ) {

                $error_message =
                    "Please enter a valid stock quantity.";
            }

        } else {

            // ------------------------------------------------
            // FUTURE HARVEST
            // ------------------------------------------------

            if ($harvest_date === "") {

                $error_message =
                    "Please select the expected harvest date.";

            } elseif ($expected_quantity === "") {

                $error_message =
                    "Please enter the expected harvest quantity.";

            } elseif ($prebook_quantity === "") {

                $error_message =
                    "Please enter the quantity available for pre-booking.";

            } elseif (
                !is_numeric($expected_quantity) ||
                (float) $expected_quantity <= 0
            ) {

                $error_message =
                    "Expected harvest quantity must be greater than 0.";

            } elseif (
                !is_numeric($prebook_quantity) ||
                (float) $prebook_quantity <= 0
            ) {

                $error_message =
                    "Pre-booking quantity must be greater than 0.";

            } elseif (
                !is_numeric($minimum_booking) ||
                (float) $minimum_booking <= 0
            ) {

                $error_message =
                    "Minimum booking quantity must be greater than 0.";

            } elseif (
                (float) $prebook_quantity >
                (float) $expected_quantity
            ) {

                $error_message =
                    "Pre-booking quantity cannot be greater than the expected harvest quantity.";

            } elseif (
                (float) $minimum_booking >
                (float) $prebook_quantity
            ) {

                $error_message =
                    "Minimum booking quantity cannot be greater than the available pre-booking quantity.";

            } else {

                // --------------------------------------------
                // HARVEST DATE MUST BE IN THE FUTURE
                // --------------------------------------------

                $today = date("Y-m-d");

                if ($harvest_date <= $today) {

                    $error_message =
                        "Expected harvest date must be a future date.";
                }
            }
        }
    }


    // ========================================================
    // BUILD LOCATION
    // ========================================================

    if ($error_message === "") {

        if ($area !== "") {
            $location = $district . ", " . $area;
        } else {
            $location = $district;
        }
    }


    // ========================================================
    // IMAGE UPLOAD
    // ========================================================

    $image_name = null;

    if ($error_message === "") {

        if (
            isset($_FILES["product_image"]) &&
            $_FILES["product_image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $_FILES["product_image"]["error"] !==
                UPLOAD_ERR_OK
            ) {

                $error_message =
                    "There was an error uploading the product image.";

            } else {

                $file_size =
                    $_FILES["product_image"]["size"];

                // --------------------------------------------
                // MAXIMUM 5MB
                // --------------------------------------------

                if ($file_size > 5 * 1024 * 1024) {

                    $error_message =
                        "Product image must be smaller than 5MB.";

                } else {

                    $original_name =
                        $_FILES["product_image"]["name"];

                    $file_extension = strtolower(
                        pathinfo(
                            $original_name,
                            PATHINFO_EXTENSION
                        )
                    );

                    $allowed_extensions = [
                        "jpg",
                        "jpeg",
                        "png"
                    ];

                    if (
                        !in_array(
                            $file_extension,
                            $allowed_extensions,
                            true
                        )
                    ) {

                        $error_message =
                            "Only JPG, JPEG and PNG images are allowed.";

                    } else {

                        // ------------------------------------
                        // CREATE UPLOAD DIRECTORY
                        // ------------------------------------

                        $upload_directory =
                            __DIR__ .
                            "/uploads/products/";

                        if (!is_dir($upload_directory)) {

                            if (
                                !mkdir(
                                    $upload_directory,
                                    0777,
                                    true
                                )
                            ) {

                                $error_message =
                                    "Unable to create product upload directory.";
                            }
                        }


                        // ------------------------------------
                        // CREATE UNIQUE IMAGE NAME
                        // ------------------------------------

                        if ($error_message === "") {

                            $image_name =
                                "product_" .
                                $farmer_id .
                                "_" .
                                time() .
                                "_" .
                                bin2hex(
                                    random_bytes(4)
                                ) .
                                "." .
                                $file_extension;


                            $image_path =
                                $upload_directory .
                                $image_name;


                            // --------------------------------
                            // MOVE UPLOADED FILE
                            // --------------------------------

                            if (
                                !move_uploaded_file(
                                    $_FILES["product_image"]["tmp_name"],
                                    $image_path
                                )
                            ) {

                                $error_message =
                                    "Unable to save the product image.";

                                $image_name = null;
                            }
                        }
                    }
                }
            }
        }
    }


    // ========================================================
    // SAVE DATA
    // ========================================================

    if ($error_message === "") {

        $product_price = (float) $price;


        // ====================================================
        // FUTURE HARVEST
        // ====================================================

        if ($product_type === "future") {

            $expected_qty =
                (float) $expected_quantity;

            $prebook_qty =
                (float) $prebook_quantity;

            $minimum_book_qty =
                (float) $minimum_booking;


            // --------------------------------------------
            // INSERT FUTURE HARVEST
            // --------------------------------------------

            $insert_stmt = $conn->prepare("
                INSERT INTO future_harvests
                (
                    farmer_id,
                    product_name,
                    category,
                    description,
                    price,
                    unit,
                    expected_quantity,
                    prebook_quantity,
                    remaining_quantity,
                    minimum_booking,
                    harvest_date,
                    location,
                    image,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'open'
                )
            ");


            if (!$insert_stmt) {

                $error_message =
                    "Unable to prepare future harvest information.";

            } else {

                $insert_stmt->bind_param(
                    "isssdsddddsss",
                    $farmer_id,
                    $product_name,
                    $category,
                    $description,
                    $product_price,
                    $unit,
                    $expected_qty,
                    $prebook_qty,
                    $prebook_qty,
                    $minimum_book_qty,
                    $harvest_date,
                    $location,
                    $image_name
                );


                if ($insert_stmt->execute()) {

                    $insert_stmt->close();

                    header(
                        "Location: farmer-bookings.php?success=harvest_added"
                    );

                    exit;

                } else {

                    $error_message =
                        "Unable to add the future harvest. Please try again.";

                    // ------------------------------------
                    // DELETE IMAGE IF DATABASE FAILED
                    // ------------------------------------

                    if ($image_name !== null) {

                        $uploaded_file =
                            __DIR__ .
                            "/uploads/products/" .
                            $image_name;

                        if (file_exists($uploaded_file)) {
                            unlink($uploaded_file);
                        }
                    }

                    $insert_stmt->close();
                }
            }


        // ====================================================
        // REGULAR PRODUCT
        // ====================================================

        } else {

            $quantity = (float) $stock;


            // --------------------------------------------
            // DETERMINE PRODUCT STATUS
            // --------------------------------------------

            if ($quantity <= 0) {

                $status = "out_of_stock";

            } else {

                $status = $availability;
            }


            // --------------------------------------------
            // INSERT REGULAR PRODUCT
            // --------------------------------------------

            $insert_stmt = $conn->prepare("
                INSERT INTO products
                (
                    farmer_id,
                    name,
                    category,
                    description,
                    price,
                    unit,
                    quantity,
                    location,
                    image,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");


            if (!$insert_stmt) {

                $error_message =
                    "Unable to prepare product information.";

                // ----------------------------------------
                // DELETE IMAGE
                // ----------------------------------------

                if ($image_name !== null) {

                    $uploaded_file =
                        __DIR__ .
                        "/uploads/products/" .
                        $image_name;

                    if (file_exists($uploaded_file)) {
                        unlink($uploaded_file);
                    }
                }

            } else {

                $insert_stmt->bind_param(
                    "isssdsdsss",
                    $farmer_id,
                    $product_name,
                    $category,
                    $description,
                    $product_price,
                    $unit,
                    $quantity,
                    $location,
                    $image_name,
                    $status
                );


                if ($insert_stmt->execute()) {

                    $insert_stmt->close();

                    header(
                        "Location: farmer-products.php?success=product_added"
                    );

                    exit;

                } else {

                    $error_message =
                        "Unable to add the product. Please try again.";

                    // ------------------------------------
                    // DELETE IMAGE IF DATABASE FAILED
                    // ------------------------------------

                    if ($image_name !== null) {

                        $uploaded_file =
                            __DIR__ .
                            "/uploads/products/" .
                            $image_name;

                        if (file_exists($uploaded_file)) {
                            unlink($uploaded_file);
                        }
                    }

                    $insert_stmt->close();
                }
            }
        }
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

    <title>Add Product | AgroLink</title>


    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/add-product.css"
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


            <a
                href="#"
                class="notification"
            >

                🔔

                <?php if ($notification_count > 0): ?>

                    <span class="notification-count">
                        <?= $notification_count ?>
                    </span>

                <?php endif; ?>

            </a>


            <a
                href="farmer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">

                    <?= htmlspecialchars(
                        strtoupper(
                            substr(
                                $farmer_name,
                                0,
                                1
                            )
                        )
                    ) ?>

                </span>

                <span class="profile-name">

                    <?= htmlspecialchars(
                        $farmer_name
                    ) ?>

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

<main class="add-product-page">

    <div class="container">


        <!-- =====================================================
             PAGE HEADER
        ====================================================== -->

        <div class="page-header">

            <div>

                <span class="page-tag">
                    FARM MANAGEMENT
                </span>

                <h1>
                    Add New Product
                </h1>

                <p>
                    Add a product or announce a future harvest
                    to AgroLink consumers.
                </p>

            </div>


            <a
                href="farmer-products.php"
                class="back-link"
            >
                ← Back to My Products
            </a>

        </div>


        <!-- =====================================================
             SUCCESS MESSAGE
        ====================================================== -->

        <?php if ($success_message !== ""): ?>

            <div
                style="
                    margin-bottom:20px;
                    padding:12px 15px;
                    border:1px solid #cfe5cf;
                    border-radius:6px;
                    background:#f1faf1;
                    color:#2e7d32;
                    font-size:10px;
                "
            >

                <?= htmlspecialchars(
                    $success_message
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             ERROR MESSAGE
        ====================================================== -->

        <?php if ($error_message !== ""): ?>

            <div
                style="
                    margin-bottom:20px;
                    padding:12px 15px;
                    border:1px solid #ead0d0;
                    border-radius:6px;
                    background:#fff5f5;
                    color:#c62828;
                    font-size:10px;
                "
            >

                <?= htmlspecialchars(
                    $error_message
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             FORM
        ====================================================== -->

        <form
            action="add-product.php"
            method="POST"
            enctype="multipart/form-data"
            class="product-form"
            id="product-form"
        >


            <!-- =================================================
                 PRODUCT TYPE
            ================================================== -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Listing Type
                        </h2>

                        <p>
                            Choose whether this is available now
                            or a future harvest for pre-booking.
                        </p>

                    </div>

                    <span>
                        01
                    </span>

                </div>


                <label class="availability-option">

                    <input
                        type="radio"
                        name="product_type"
                        value="regular"
                        id="type-regular"
                        <?= $product_type === "regular"
                            ? "checked"
                            : "" ?>
                    >

                    <span class="custom-radio"></span>

                    <span>

                        <strong>
                            Available Now
                        </strong>

                        <small>
                            Product will be listed in the
                            normal AgroLink marketplace.
                        </small>

                    </span>

                </label>


                <label class="availability-option">

                    <input
                        type="radio"
                        name="product_type"
                        value="future"
                        id="type-future"
                        <?= $product_type === "future"
                            ? "checked"
                            : "" ?>
                    >

                    <span class="custom-radio"></span>

                    <span>

                        <strong>
                            Future Harvest
                        </strong>

                        <small>
                            Consumers can pre-book bulk quantities
                            before the harvest date.
                        </small>

                    </span>

                </label>

            </section>


            <!-- =================================================
                 BASIC INFORMATION
            ================================================== -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Basic Information
                        </h2>

                        <p>
                            Provide the basic details of your product.
                        </p>

                    </div>

                    <span>
                        02
                    </span>

                </div>


                <div class="form-grid">


                    <!-- PRODUCT NAME -->

                    <div class="form-group full-width">

                        <label for="product-name">

                            Product Name

                            <span>*</span>

                        </label>

                        <input
                            type="text"
                            id="product-name"
                            name="product_name"
                            value="<?= htmlspecialchars(
                                $product_name
                            ) ?>"
                            placeholder="e.g. Fresh Organic Tomatoes"
                            required
                        >

                    </div>


                    <!-- CATEGORY -->

                    <div class="form-group">

                        <label for="category">

                            Category

                            <span>*</span>

                        </label>

                        <select
                            id="category"
                            name="category"
                            required
                        >

                            <option value="">
                                Select category
                            </option>

                            <option
                                value="vegetables"
                                <?= $category === "vegetables"
                                    ? "selected"
                                    : "" ?>
                            >
                                Vegetables
                            </option>

                            <option
                                value="fruits"
                                <?= $category === "fruits"
                                    ? "selected"
                                    : "" ?>
                            >
                                Fruits
                            </option>

                            <option
                                value="grains"
                                <?= $category === "grains"
                                    ? "selected"
                                    : "" ?>
                            >
                                Grains
                            </option>

                            <option
                                value="dairy"
                                <?= $category === "dairy"
                                    ? "selected"
                                    : "" ?>
                            >
                                Dairy
                            </option>

                            <option
                                value="fish"
                                <?= $category === "fish"
                                    ? "selected"
                                    : "" ?>
                            >
                                Fish
                            </option>

                            <option
                                value="meat"
                                <?= $category === "meat"
                                    ? "selected"
                                    : "" ?>
                            >
                                Meat
                            </option>

                            <option
                                value="poultry"
                                <?= $category === "poultry"
                                    ? "selected"
                                    : "" ?>
                            >
                                Poultry
                            </option>

                            <option
                                value="other"
                                <?= $category === "other"
                                    ? "selected"
                                    : "" ?>
                            >
                                Other
                            </option>

                        </select>

                    </div>


                    <!-- SUBCATEGORY -->

                    <div class="form-group">

                        <label for="subcategory">
                            Subcategory
                        </label>

                        <input
                            type="text"
                            id="subcategory"
                            name="subcategory"
                            value="<?= htmlspecialchars(
                                $subcategory
                            ) ?>"
                            placeholder="e.g. Tomato, Mango, Rice"
                        >

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="form-group full-width">

                        <label for="description">

                            Product Description

                            <span>*</span>

                        </label>

                        <textarea
                            id="description"
                            name="description"
                            rows="5"
                            placeholder="Describe your product, quality, farming method, freshness, etc."
                            required
                        ><?= htmlspecialchars(
                            $description
                        ) ?></textarea>

                    </div>


                </div>

            </section>


            <!-- =================================================
                 PRICE
            ================================================== -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Price Information
                        </h2>

                        <p>
                            Set the expected selling price and unit.
                        </p>

                    </div>

                    <span>
                        03
                    </span>

                </div>


                <div class="form-grid">


                    <!-- PRICE -->

                    <div class="form-group">

                        <label for="price">

                            Price

                            <span>*</span>

                        </label>

                        <div class="input-with-prefix">

                            <span>
                                ৳
                            </span>

                            <input
                                type="number"
                                id="price"
                                name="price"
                                value="<?= htmlspecialchars(
                                    $price
                                ) ?>"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                required
                            >

                        </div>

                    </div>


                    <!-- UNIT -->

                    <div class="form-group">

                        <label for="unit">

                            Unit

                            <span>*</span>

                        </label>

                        <select
                            id="unit"
                            name="unit"
                            required
                        >

                            <option
                                value="kg"
                                <?= $unit === "kg"
                                    ? "selected"
                                    : "" ?>
                            >
                                Kilogram (kg)
                            </option>

                            <option
                                value="gram"
                                <?= $unit === "gram"
                                    ? "selected"
                                    : "" ?>
                            >
                                Gram (g)
                            </option>

                            <option
                                value="liter"
                                <?= $unit === "liter"
                                    ? "selected"
                                    : "" ?>
                            >
                                Liter (L)
                            </option>

                            <option
                                value="piece"
                                <?= $unit === "piece"
                                    ? "selected"
                                    : "" ?>
                            >
                                Piece
                            </option>

                            <option
                                value="dozen"
                                <?= $unit === "dozen"
                                    ? "selected"
                                    : "" ?>
                            >
                                Dozen
                            </option>

                            <option
                                value="bag"
                                <?= $unit === "bag"
                                    ? "selected"
                                    : "" ?>
                            >
                                Bag
                            </option>

                        </select>

                    </div>


                </div>

            </section>


            <!-- =================================================
                 REGULAR PRODUCT STOCK
            ================================================== -->

            <section
                class="form-card"
                id="regular-stock-section"
            >

                <div class="form-card-header">

                    <div>

                        <h2>
                            Current Stock
                        </h2>

                        <p>
                            Set the quantity currently available
                            for sale.
                        </p>

                    </div>

                    <span>
                        04
                    </span>

                </div>


                <div class="form-grid">


                    <div class="form-group">

                        <label for="stock">

                            Available Stock

                            <span>*</span>

                        </label>

                        <input
                            type="number"
                            id="stock"
                            name="stock"
                            value="<?= htmlspecialchars(
                                $stock
                            ) ?>"
                            min="0"
                            step="0.01"
                            placeholder="e.g. 100"
                        >

                    </div>


                </div>

            </section>


            <!-- =================================================
                 FUTURE HARVEST
            ================================================== -->

            <section
                class="form-card"
                id="future-harvest-section"
                style="display:none;"
            >

                <div class="form-card-header">

                    <div>

                        <h2>
                            Future Harvest Details
                        </h2>

                        <p>
                            Tell consumers what you expect to harvest
                            and how much they can reserve in advance.
                        </p>

                    </div>

                    <span>
                        04
                    </span>

                </div>


                <div class="form-grid">


                    <!-- HARVEST DATE -->

                    <div class="form-group">

                        <label for="harvest-date">

                            Expected Harvest Date

                            <span>*</span>

                        </label>

                        <input
                            type="date"
                            id="harvest-date"
                            name="harvest_date"
                            value="<?= htmlspecialchars(
                                $harvest_date
                            ) ?>"
                        >

                    </div>


                    <!-- EXPECTED QUANTITY -->

                    <div class="form-group">

                        <label for="expected-quantity">

                            Expected Harvest Quantity

                            <span>*</span>

                        </label>

                        <input
                            type="number"
                            id="expected-quantity"
                            name="expected_quantity"
                            value="<?= htmlspecialchars(
                                $expected_quantity
                            ) ?>"
                            min="0.01"
                            step="0.01"
                            placeholder="e.g. 500"
                        >

                    </div>


                    <!-- PREBOOK QUANTITY -->

                    <div class="form-group">

                        <label for="prebook-quantity">

                            Available for Pre-Booking

                            <span>*</span>

                        </label>

                        <input
                            type="number"
                            id="prebook-quantity"
                            name="prebook_quantity"
                            value="<?= htmlspecialchars(
                                $prebook_quantity
                            ) ?>"
                            min="0.01"
                            step="0.01"
                            placeholder="e.g. 350"
                        >

                    </div>


                    <!-- MINIMUM BOOKING -->

                    <div class="form-group">

                        <label for="minimum-booking">

                            Minimum Bulk Booking

                            <span>*</span>

                        </label>

                        <input
                            type="number"
                            id="minimum-booking"
                            name="minimum_booking"
                            value="<?= htmlspecialchars(
                                $minimum_booking
                            ) ?>"
                            min="0.01"
                            step="0.01"
                            placeholder="e.g. 50"
                        >

                    </div>


                    <!-- INFORMATION -->

                    <div
                        class="form-group full-width"
                    >

                        <small
                            style="
                                display:block;
                                margin-top:5px;
                                line-height:1.6;
                            "
                        >
                            Example: If you expect to harvest
                            <strong>500 kg</strong> and want consumers
                            to pre-book up to <strong>350 kg</strong>,
                            enter 500 as the expected quantity and
                            350 as the pre-booking quantity.
                            If the minimum booking is 50 kg, consumers
                            must reserve at least 50 kg.
                        </small>

                    </div>


                </div>

            </section>


            <!-- =================================================
                 PRODUCT IMAGE
            ================================================== -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Product Image
                        </h2>

                        <p>
                            Upload a clear image of your product.
                        </p>

                    </div>

                    <span>
                        05
                    </span>

                </div>


                <div class="upload-area">

                    <div class="upload-icon">
                        📷
                    </div>

                    <h3>
                        Upload Product Image
                    </h3>

                    <p>
                        JPG, JPEG or PNG • Maximum 5MB
                    </p>

                    <label
                        for="product-image"
                        class="upload-button"
                    >
                        Choose Image
                    </label>

                    <input
                        type="file"
                        id="product-image"
                        name="product_image"
                        accept=".jpg,.jpeg,.png"
                    >

                </div>

            </section>


            <!-- =================================================
                 LOCATION
            ================================================== -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Product Location
                        </h2>

                        <p>
                            Tell consumers where the product is available.
                        </p>

                    </div>

                    <span>
                        06
                    </span>

                </div>


                <div class="form-grid">


                    <!-- DISTRICT -->

                    <div class="form-group">

                        <label for="district">

                            District

                            <span>*</span>

                        </label>

                        <input
                            type="text"
                            id="district"
                            name="district"
                            value="<?= htmlspecialchars(
                                $district
                            ) ?>"
                            placeholder="e.g. Dhaka"
                            required
                        >

                    </div>


                    <!-- AREA -->

                    <div class="form-group">

                        <label for="area">
                            Area / Upazila
                        </label>

                        <input
                            type="text"
                            id="area"
                            name="area"
                            value="<?= htmlspecialchars(
                                $area
                            ) ?>"
                            placeholder="e.g. Savar"
                        >

                    </div>


                    <!-- DELIVERY -->

                    <div class="form-group full-width">

                        <label for="delivery">

                            Delivery Availability

                        </label>

                        <select
                            id="delivery"
                            name="delivery"
                        >

                            <option
                                value="home-delivery"
                                <?= $delivery === "home-delivery"
                                    ? "selected"
                                    : "" ?>
                            >
                                Home Delivery Available
                            </option>

                            <option
                                value="pickup"
                                <?= $delivery === "pickup"
                                    ? "selected"
                                    : "" ?>
                            >
                                Farmer Pickup Only
                            </option>

                            <option
                                value="both"
                                <?= $delivery === "both"
                                    ? "selected"
                                    : "" ?>
                            >
                                Home Delivery & Pickup
                            </option>

                        </select>

                    </div>


                </div>

            </section>


            <!-- =================================================
                 AVAILABILITY
            ================================================== -->

            <section
                class="form-card"
                id="availability-section"
            >

                <div class="form-card-header">

                    <div>

                        <h2>
                            Product Availability
                        </h2>

                        <p>
                            Choose whether consumers can currently
                            purchase this product.
                        </p>

                    </div>

                    <span>
                        07
                    </span>

                </div>


                <label class="availability-option">

                    <input
                        type="radio"
                        name="availability"
                        value="available"
                        <?= $availability === "available"
                            ? "checked"
                            : "" ?>
                    >

                    <span class="custom-radio"></span>

                    <span>

                        <strong>
                            Active
                        </strong>

                        <small>
                            Product will be visible in the marketplace.
                        </small>

                    </span>

                </label>


                <label class="availability-option">

                    <input
                        type="radio"
                        name="availability"
                        value="inactive"
                        <?= $availability === "inactive"
                            ? "checked"
                            : "" ?>
                    >

                    <span class="custom-radio"></span>

                    <span>

                        <strong>
                            Inactive
                        </strong>

                        <small>
                            Product will be saved but hidden from consumers.
                        </small>

                    </span>

                </label>

            </section>


            <!-- =================================================
                 FORM ACTIONS
            ================================================== -->

            <div class="form-actions">

                <a
                    href="farmer-products.php"
                    class="cancel-btn"
                >
                    Cancel
                </a>

                <button
                    type="submit"
                    class="save-btn"
                    id="submit-button"
                >
                    + Add Product
                </button>

            </div>


        </form>

    </div>

</main>


<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="footer">

    <div class="container footer-grid">


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

            <a href="farmer-orders.php">
                Orders
            </a>

            <a href="farmer-dss.php">
                Decision Support
            </a>

        </div>


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


<!-- =========================================================
     PRODUCT TYPE TOGGLE
========================================================= -->

<script>

document.addEventListener("DOMContentLoaded", function () {

    const regularRadio =
        document.getElementById("type-regular");

    const futureRadio =
        document.getElementById("type-future");

    const regularStockSection =
        document.getElementById("regular-stock-section");

    const futureHarvestSection =
        document.getElementById("future-harvest-section");

    const availabilitySection =
        document.getElementById("availability-section");

    const stockInput =
        document.getElementById("stock");

    const harvestDateInput =
        document.getElementById("harvest-date");

    const expectedQuantityInput =
        document.getElementById("expected-quantity");

    const prebookQuantityInput =
        document.getElementById("prebook-quantity");

    const minimumBookingInput =
        document.getElementById("minimum-booking");

    const submitButton =
        document.getElementById("submit-button");


    function updateProductType() {

        if (futureRadio.checked) {

            // --------------------------------------------
            // FUTURE HARVEST
            // --------------------------------------------

            regularStockSection.style.display = "none";

            futureHarvestSection.style.display = "block";

            availabilitySection.style.display = "none";

            stockInput.removeAttribute("required");

            harvestDateInput.setAttribute(
                "required",
                "required"
            );

            expectedQuantityInput.setAttribute(
                "required",
                "required"
            );

            prebookQuantityInput.setAttribute(
                "required",
                "required"
            );

            minimumBookingInput.setAttribute(
                "required",
                "required"
            );

            submitButton.textContent =
                "+ Add Future Harvest";

        } else {

            // --------------------------------------------
            // REGULAR PRODUCT
            // --------------------------------------------

            regularStockSection.style.display = "block";

            futureHarvestSection.style.display = "none";

            availabilitySection.style.display = "block";

            stockInput.setAttribute(
                "required",
                "required"
            );

            harvestDateInput.removeAttribute(
                "required"
            );

            expectedQuantityInput.removeAttribute(
                "required"
            );

            prebookQuantityInput.removeAttribute(
                "required"
            );

            minimumBookingInput.removeAttribute(
                "required"
            );

            submitButton.textContent =
                "+ Add Product";
        }
    }


    regularRadio.addEventListener(
        "change",
        updateProductType
    );

    futureRadio.addEventListener(
        "change",
        updateProductType
    );


    // Run once when page loads
    updateProductType();

});

</script>


</body>

</html>