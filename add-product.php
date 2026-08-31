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

$product_name = "";
$category = "";
$description = "";
$price = "";
$unit = "kg";
$quantity = "";
$district = "";
$area = "";
$availability = "available";

$error_message = "";
$success_message = "";


// ============================================================
// HANDLE FORM SUBMISSION
// ============================================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // --------------------------------------------------------
    // BASIC INFORMATION
    // --------------------------------------------------------

    $product_name = trim($_POST["product_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");


    // --------------------------------------------------------
    // PRICE & QUANTITY
    // --------------------------------------------------------

    $price = trim($_POST["price"] ?? "");
    $unit = trim($_POST["unit"] ?? "kg");
    $quantity = trim($_POST["quantity"] ?? "");


    // --------------------------------------------------------
    // LOCATION
    // --------------------------------------------------------

    $district = trim($_POST["district"] ?? "");
    $area = trim($_POST["area"] ?? "");


    // --------------------------------------------------------
    // AVAILABILITY
    // --------------------------------------------------------

    $availability = $_POST["availability"] ?? "available";

    if (!in_array($availability, ["available", "inactive"], true)) {
        $availability = "available";
    }


    // ========================================================
    // VALIDATION
    // ========================================================

    if (
        $product_name === "" ||
        $category === "" ||
        $description === "" ||
        $price === "" ||
        $unit === "" ||
        $quantity === "" ||
        $district === ""
    ) {

        $error_message = "Please fill in all required fields.";

    } elseif (!is_numeric($price) || (float) $price < 0) {

        $error_message = "Please enter a valid product price.";

    } elseif (!is_numeric($quantity) || (float) $quantity < 0) {

        $error_message = "Please enter a valid quantity.";

    } else {

        // ====================================================
        // BUILD LOCATION
        // ====================================================

        if ($area !== "") {
            $location = $district . ", " . $area;
        } else {
            $location = $district;
        }


        // ====================================================
        // IMAGE UPLOAD
        // ====================================================

        $image_name = null;

        if (
            isset($_FILES["product_image"]) &&
            $_FILES["product_image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if ($_FILES["product_image"]["error"] !== UPLOAD_ERR_OK) {

                $error_message = "There was an error uploading the product image.";

            } else {

                $file_size = $_FILES["product_image"]["size"];

                // Maximum 5MB
                if ($file_size > 5 * 1024 * 1024) {

                    $error_message = "Product image must be smaller than 5MB.";

                } else {

                    $original_name = $_FILES["product_image"]["name"];

                    $file_extension = strtolower(
                        pathinfo($original_name, PATHINFO_EXTENSION)
                    );

                    $allowed_extensions = [
                        "jpg",
                        "jpeg",
                        "png"
                    ];

                    if (!in_array($file_extension, $allowed_extensions, true)) {

                        $error_message = "Only JPG, JPEG and PNG images are allowed.";

                    } else {

                        // ------------------------------------------------
                        // CREATE UPLOAD DIRECTORY
                        // ------------------------------------------------

                        $upload_directory = __DIR__ . "/uploads/products/";

                        if (!is_dir($upload_directory)) {

                            if (!mkdir($upload_directory, 0777, true)) {
                                $error_message = "Unable to create product upload directory.";
                            }
                        }


                        // ------------------------------------------------
                        // CREATE UNIQUE IMAGE NAME
                        // ------------------------------------------------

                        if ($error_message === "") {

                            $image_name =
                                "product_" .
                                $farmer_id .
                                "_" .
                                time() .
                                "_" .
                                bin2hex(random_bytes(4)) .
                                "." .
                                $file_extension;


                            $image_path =
                                $upload_directory .
                                $image_name;


                            // ------------------------------------------------
                            // MOVE UPLOADED FILE
                            // ------------------------------------------------

                            if (
                                !move_uploaded_file(
                                    $_FILES["product_image"]["tmp_name"],
                                    $image_path
                                )
                            ) {

                                $error_message = "Unable to save the product image.";

                                $image_name = null;
                            }
                        }
                    }
                }
            }
        }


        // ====================================================
        // INSERT PRODUCT
        // ====================================================

        if ($error_message === "") {

            $product_price = (float) $price;
            $product_quantity = (float) $quantity;


            // ------------------------------------------------
            // DETERMINE STATUS
            // ------------------------------------------------

            if ($product_quantity <= 0) {

                $status = "out_of_stock";

            } else {

                $status = $availability;
            }


            // ------------------------------------------------
            // PREPARE INSERT
            // ------------------------------------------------

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

                $error_message = "Unable to prepare product information.";

                // Delete uploaded image if database insertion cannot proceed
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
                    $product_quantity,
                    $location,
                    $image_name,
                    $status
                );


                // ------------------------------------------------
                // EXECUTE INSERT
                // ------------------------------------------------

                if ($insert_stmt->execute()) {

                    $insert_stmt->close();

                    // Redirect after successful insertion
                    header("Location: farmer-products.php?success=product_added");
                    exit;

                } else {

                    $error_message =
                        "Unable to add the product. Please try again.";

                    // Delete uploaded image if DB insertion failed
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

            <a
                href="farmer-products.php"
                class="active-nav"
            >
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
                            substr($farmer_name, 0, 1)
                        )
                    ) ?>

                </span>

                <span class="profile-name">

                    <?= htmlspecialchars($farmer_name) ?>

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


        <!-- PAGE HEADER -->

        <div class="page-header">

            <div>

                <span class="page-tag">
                    FARM MANAGEMENT
                </span>

                <h1>
                    Add New Product
                </h1>

                <p>
                    Add your agricultural product to the
                    AgroLink marketplace.
                </p>

            </div>


            <a
                href="farmer-products.php"
                class="back-link"
            >
                ← Back to My Products
            </a>

        </div>



        <!-- SUCCESS MESSAGE -->

        <?php if ($success_message !== ""): ?>

            <div class="message success-message">
                <?= htmlspecialchars($success_message) ?>
            </div>

        <?php endif; ?>



        <!-- ERROR MESSAGE -->

        <?php if ($error_message !== ""): ?>

            <div class="message error-message">
                <?= htmlspecialchars($error_message) ?>
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
        >


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
                        01
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
                            value="<?= htmlspecialchars($product_name) ?>"
                            placeholder="e.g. Fresh Organic Tomatoes"
                            maxlength="150"
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
                                <?= $category === "vegetables" ? "selected" : "" ?>
                            >
                                Vegetables
                            </option>

                            <option
                                value="fruits"
                                <?= $category === "fruits" ? "selected" : "" ?>
                            >
                                Fruits
                            </option>

                            <option
                                value="grains"
                                <?= $category === "grains" ? "selected" : "" ?>
                            >
                                Grains
                            </option>

                            <option
                                value="dairy"
                                <?= $category === "dairy" ? "selected" : "" ?>
                            >
                                Dairy
                            </option>

                            <option
                                value="fish"
                                <?= $category === "fish" ? "selected" : "" ?>
                            >
                                Fish
                            </option>

                            <option
                                value="meat"
                                <?= $category === "meat" ? "selected" : "" ?>
                            >
                                Meat
                            </option>

                            <option
                                value="poultry"
                                <?= $category === "poultry" ? "selected" : "" ?>
                            >
                                Poultry
                            </option>

                            <option
                                value="other"
                                <?= $category === "other" ? "selected" : "" ?>
                            >
                                Other
                            </option>

                        </select>

                    </div>



                    <!-- UNIT -->

                    <div class="form-group">

                        <label for="unit">

                            Selling Unit

                            <span>*</span>

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
                                Kilogram (kg)
                            </option>

                            <option
                                value="gram"
                                <?= $unit === "gram" ? "selected" : "" ?>
                            >
                                Gram (g)
                            </option>

                            <option
                                value="liter"
                                <?= $unit === "liter" ? "selected" : "" ?>
                            >
                                Liter (L)
                            </option>

                            <option
                                value="piece"
                                <?= $unit === "piece" ? "selected" : "" ?>
                            >
                                Piece
                            </option>

                            <option
                                value="dozen"
                                <?= $unit === "dozen" ? "selected" : "" ?>
                            >
                                Dozen
                            </option>

                            <option
                                value="bag"
                                <?= $unit === "bag" ? "selected" : "" ?>
                            >
                                Bag
                            </option>

                        </select>

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
                        ><?= htmlspecialchars($description) ?></textarea>

                    </div>

                </div>

            </section>



            <!-- =================================================
                 PRICE & STOCK
            ================================================== -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Price & Stock
                        </h2>

                        <p>
                            Set your selling price and available quantity.
                        </p>

                    </div>

                    <span>
                        02
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
                                value="<?= htmlspecialchars($price) ?>"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                required
                            >

                        </div>

                    </div>



                    <!-- QUANTITY -->

                    <div class="form-group">

                        <label for="quantity">

                            Available Quantity

                            <span>*</span>

                        </label>

                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            value="<?= htmlspecialchars($quantity) ?>"
                            min="0"
                            step="0.01"
                            placeholder="e.g. 100"
                            required
                        >

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
                        03
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

                    <span
                        id="file-name"
                        class="file-name"
                    >
                    </span>

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
                        04
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
                            value="<?= htmlspecialchars($district) ?>"
                            placeholder="e.g. Dhaka"
                            maxlength="150"
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
                            value="<?= htmlspecialchars($area) ?>"
                            placeholder="e.g. Savar"
                        >

                    </div>

                </div>

            </section>



            <!-- =================================================
                 AVAILABILITY
            ================================================== -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Product Availability
                        </h2>

                        <p>
                            Choose whether consumers can currently purchase this product.
                        </p>

                    </div>

                    <span>
                        05
                    </span>

                </div>


                <label class="availability-option">

                    <input
                        type="radio"
                        name="availability"
                        value="available"
                        <?= $availability === "available" ? "checked" : "" ?>
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
                        <?= $availability === "inactive" ? "checked" : "" ?>
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
     IMAGE FILE NAME
========================================================= -->

<script>

const imageInput = document.getElementById("product-image");
const fileName = document.getElementById("file-name");

if (imageInput) {

    imageInput.addEventListener("change", function () {

        if (this.files && this.files.length > 0) {

            fileName.textContent = this.files[0].name;

        } else {

            fileName.textContent = "";

        }

    });

}

</script>


</body>

</html>