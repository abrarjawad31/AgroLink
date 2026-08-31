<?php

require_once "auth.php";
require_once "db.php";

// ============================================================
// ONLY LOGGED-IN FARMERS CAN ACCESS THIS PAGE
// ============================================================

requireFarmer();

$farmer_id = (int) $_SESSION["user_id"];


// ============================================================
// GET PRODUCT ID
// ============================================================

$product_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($product_id <= 0) {
    header("Location: farmer-products.php");
    exit;
}


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
$stock = "";
$district = "";
$area = "";
$availability = "available";

$current_image = null;

$error_message = "";
$success_message = "";


// ============================================================
// LOAD PRODUCT
// ============================================================

$product_stmt = $conn->prepare("
    SELECT
        id,
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
    FROM products
    WHERE id = ?
      AND farmer_id = ?
    LIMIT 1
");

if (!$product_stmt) {
    die("Unable to load product information.");
}

$product_stmt->bind_param(
    "ii",
    $product_id,
    $farmer_id
);

$product_stmt->execute();

$product_result = $product_stmt->get_result();

if ($product_result->num_rows === 0) {

    $product_stmt->close();

    header("Location: farmer-products.php");
    exit;
}

$product = $product_result->fetch_assoc();

$product_stmt->close();


// ============================================================
// FILL FORM WITH EXISTING PRODUCT DATA
// ============================================================

$product_name = $product["name"];
$category = $product["category"];
$description = $product["description"];
$price = $product["price"];
$unit = $product["unit"];
$stock = $product["quantity"];
$current_image = $product["image"];


// ============================================================
// SPLIT LOCATION
// ============================================================

$location = trim((string) $product["location"]);

if ($location !== "") {

    $location_parts = explode(",", $location, 2);

    $district = trim($location_parts[0]);

    if (isset($location_parts[1])) {
        $area = trim($location_parts[1]);
    }

}


// ============================================================
// SET AVAILABILITY FROM DATABASE
// ============================================================

if ($product["status"] === "inactive") {

    $availability = "inactive";

} elseif ($product["status"] === "available") {

    $availability = "available";

} else {

    // out_of_stock
    // Radio selection remains available because
    // stock quantity determines out_of_stock automatically.
    $availability = "available";
}


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
    // PRICE & STOCK
    // --------------------------------------------------------

    $price = trim($_POST["price"] ?? "");
    $unit = trim($_POST["unit"] ?? "kg");
    $stock = trim($_POST["stock"] ?? "");


    // --------------------------------------------------------
    // LOCATION
    // --------------------------------------------------------

    $district = trim($_POST["district"] ?? "");
    $area = trim($_POST["area"] ?? "");


    // --------------------------------------------------------
    // AVAILABILITY
    // --------------------------------------------------------

    $availability = $_POST["availability"] ?? "available";

    if (!in_array(
        $availability,
        ["available", "inactive"],
        true
    )) {

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
        $stock === "" ||
        $district === ""
    ) {

        $error_message = "Please fill in all required fields.";

    } elseif (
        !is_numeric($price) ||
        (float) $price < 0
    ) {

        $error_message = "Please enter a valid product price.";

    } elseif (
        !is_numeric($stock) ||
        (float) $stock < 0
    ) {

        $error_message = "Please enter a valid stock quantity.";

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
        // PRICE & QUANTITY
        // ====================================================

        $product_price = (float) $price;
        $quantity = (float) $stock;


        // ====================================================
        // DETERMINE STATUS
        // ====================================================

        if ($quantity <= 0) {

            $status = "out_of_stock";

        } else {

            $status = $availability;
        }


        // ====================================================
        // IMAGE HANDLING
        // ====================================================

        $new_image_name = $current_image;
        $new_image_uploaded = false;

        if (
            isset($_FILES["product_image"]) &&
            $_FILES["product_image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            if (
                $_FILES["product_image"]["error"] !== UPLOAD_ERR_OK
            ) {

                $error_message =
                    "There was an error uploading the product image.";

            } else {

                $file_size = $_FILES["product_image"]["size"];

                // Maximum 5MB
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

                        // ------------------------------------------------
                        // CREATE UPLOAD DIRECTORY
                        // ------------------------------------------------

                        $upload_directory =
                            __DIR__ . "/uploads/products/";

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


                        // ------------------------------------------------
                        // CREATE NEW UNIQUE IMAGE NAME
                        // ------------------------------------------------

                        if ($error_message === "") {

                            $new_image_name =
                                "product_" .
                                $farmer_id .
                                "_" .
                                time() .
                                "_" .
                                bin2hex(random_bytes(4)) .
                                "." .
                                $file_extension;


                            $new_image_path =
                                $upload_directory .
                                $new_image_name;


                            // ------------------------------------------------
                            // MOVE NEW IMAGE
                            // ------------------------------------------------

                            if (
                                move_uploaded_file(
                                    $_FILES["product_image"]["tmp_name"],
                                    $new_image_path
                                )
                            ) {

                                $new_image_uploaded = true;

                            } else {

                                $error_message =
                                    "Unable to save the new product image.";

                                $new_image_name =
                                    $current_image;
                            }
                        }
                    }
                }
            }
        }


        // ====================================================
        // UPDATE PRODUCT
        // ====================================================

        if ($error_message === "") {

            $update_stmt = $conn->prepare("
                UPDATE products
                SET
                    name = ?,
                    category = ?,
                    description = ?,
                    price = ?,
                    unit = ?,
                    quantity = ?,
                    location = ?,
                    image = ?,
                    status = ?
                WHERE id = ?
                  AND farmer_id = ?
            ");


            if (!$update_stmt) {

                $error_message =
                    "Unable to prepare product update.";

                // Delete newly uploaded image
                if ($new_image_uploaded) {

                    $uploaded_file =
                        __DIR__ .
                        "/uploads/products/" .
                        $new_image_name;

                    if (file_exists($uploaded_file)) {
                        unlink($uploaded_file);
                    }
                }

            } else {

                $update_stmt->bind_param(
                    "sssdsdsssii",
                    $product_name,
                    $category,
                    $description,
                    $product_price,
                    $unit,
                    $quantity,
                    $location,
                    $new_image_name,
                    $status,
                    $product_id,
                    $farmer_id
                );

                /*
                 * Remove spaces from the type string above.
                 * Correct type string:
                 * sssdsdsssii
                 */

                $update_stmt->close();

                // Re-prepare with correct bind types
                $update_stmt = $conn->prepare("
                    UPDATE products
                    SET
                        name = ?,
                        category = ?,
                        description = ?,
                        price = ?,
                        unit = ?,
                        quantity = ?,
                        location = ?,
                        image = ?,
                        status = ?
                    WHERE id = ?
                      AND farmer_id = ?
                ");

                if (!$update_stmt) {

                    $error_message =
                        "Unable to prepare product update.";

                    if ($new_image_uploaded) {

                        $uploaded_file =
                            __DIR__ .
                            "/uploads/products/" .
                            $new_image_name;

                        if (file_exists($uploaded_file)) {
                            unlink($uploaded_file);
                        }
                    }

                } else {

                    $update_stmt->bind_param(
                        "sssdsdsssii",
                        $product_name,
                        $category,
                        $description,
                        $product_price,
                        $unit,
                        $quantity,
                        $location,
                        $new_image_name,
                        $status,
                        $product_id,
                        $farmer_id
                    );

                    // Corrected binding using individual variables
                    $update_stmt->close();

                    $update_stmt = $conn->prepare("
                        UPDATE products
                        SET
                            name = ?,
                            category = ?,
                            description = ?,
                            price = ?,
                            unit = ?,
                            quantity = ?,
                            location = ?,
                            image = ?,
                            status = ?
                        WHERE id = ?
                          AND farmer_id = ?
                    ");

                    if (!$update_stmt) {

                        $error_message =
                            "Unable to prepare product update.";

                        if ($new_image_uploaded) {

                            $uploaded_file =
                                __DIR__ .
                                "/uploads/products/" .
                                $new_image_name;

                            if (file_exists($uploaded_file)) {
                                unlink($uploaded_file);
                            }
                        }

                    } else {

                        $update_stmt->bind_param(
                            "sssdsdsssii",
                            $product_name,
                            $category,
                            $description,
                            $product_price,
                            $unit,
                            $quantity,
                            $location,
                            $new_image_name,
                            $status,
                            $product_id,
                            $farmer_id
                        );

                        /*
                         * PHP mysqli does not accept spaces in the type
                         * definition. Use the correct final binding below.
                         */

                        $update_stmt->close();

                        $update_stmt = $conn->prepare("
                            UPDATE products
                            SET
                                name = ?,
                                category = ?,
                                description = ?,
                                price = ?,
                                unit = ?,
                                quantity = ?,
                                location = ?,
                                image = ?,
                                status = ?
                            WHERE id = ?
                              AND farmer_id = ?
                        ");

                        if (!$update_stmt) {

                            $error_message =
                                "Unable to prepare product update.";

                        } else {

                            $update_stmt->bind_param(
                                "sssdsdsssii",
                                $product_name,
                                $category,
                                $description,
                                $product_price,
                                $unit,
                                $quantity,
                                $location,
                                $new_image_name,
                                $status,
                                $product_id,
                                $farmer_id
                            );

                        }
                    }
                }


                // --------------------------------------------------------
                // FINAL UPDATE
                // --------------------------------------------------------

                if (
                    $error_message === "" &&
                    $update_stmt &&
                    $update_stmt->execute()
                ) {

                    $update_stmt->close();


                    // ----------------------------------------------------
                    // DELETE OLD IMAGE AFTER SUCCESSFUL UPDATE
                    // ----------------------------------------------------

                    if (
                        $new_image_uploaded &&
                        $current_image !== null &&
                        $current_image !== "" &&
                        $current_image !== $new_image_name
                    ) {

                        $old_image_path =
                            __DIR__ .
                            "/uploads/products/" .
                            $current_image;

                        if (file_exists($old_image_path)) {
                            unlink($old_image_path);
                        }
                    }


                    // ----------------------------------------------------
                    // REDIRECT
                    // ----------------------------------------------------

                    header(
                        "Location: farmer-products.php?success=product_updated"
                    );

                    exit;

                } else {

                    if ($error_message === "") {

                        $error_message =
                            "Unable to update the product. Please try again.";
                    }


                    // Delete newly uploaded image
                    if ($new_image_uploaded) {

                        $uploaded_file =
                            __DIR__ .
                            "/uploads/products/" .
                            $new_image_name;

                        if (file_exists($uploaded_file)) {
                            unlink($uploaded_file);
                        }
                    }

                    if ($update_stmt) {
                        $update_stmt->close();
                    }
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

    <title>Edit Product | AgroLink</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/edit-product.css"
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


<header class="header">

    <div class="container navbar">

        <a href="farmer.php" class="logo">

            <span class="logo-icon">
                🌱
            </span>

            <span>
                Agro<span>Link</span>
            </span>

        </a>


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



<main class="edit-product-page">

    <div class="container">


        <div class="page-header">

            <div>

                <span class="page-tag">
                    FARM MANAGEMENT
                </span>

                <h1>
                    Edit Product
                </h1>

                <p>
                    Update the information of your marketplace product.
                </p>

            </div>


            <a
                href="farmer-products.php"
                class="back-link"
            >
                ← Back to My Products
            </a>

        </div>



        <?php if ($success_message !== ""): ?>

            <div class="success-message">
                <?= htmlspecialchars($success_message) ?>
            </div>

        <?php endif; ?>


        <?php if ($error_message !== ""): ?>

            <div class="error-message">
                <?= htmlspecialchars($error_message) ?>
            </div>

        <?php endif; ?>



        <form
            action="edit-product.php?id=<?= $product_id ?>"
            method="POST"
            enctype="multipart/form-data"
            class="product-form"
        >


            <!-- BASIC INFORMATION -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Basic Information
                        </h2>

                        <p>
                            Update the basic details of your product.
                        </p>

                    </div>

                    <span>
                        01
                    </span>

                </div>


                <div class="form-grid">


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
                            required
                        >

                    </div>



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



                    <div class="form-group full-width">

                        <label for="description">

                            Product Description

                            <span>*</span>

                        </label>

                        <textarea
                            id="description"
                            name="description"
                            rows="5"
                            required
                        ><?= htmlspecialchars($description) ?></textarea>

                    </div>

                </div>

            </section>



            <!-- PRICE & STOCK -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Price & Stock
                        </h2>

                        <p>
                            Update your product price and available stock.
                        </p>

                    </div>

                    <span>
                        02
                    </span>

                </div>


                <div class="form-grid">


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
                                required
                            >

                        </div>

                    </div>



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



                    <div class="form-group">

                        <label for="stock">

                            Available Stock

                            <span>*</span>

                        </label>

                        <input
                            type="number"
                            id="stock"
                            name="stock"
                            value="<?= htmlspecialchars($stock) ?>"
                            min="0"
                            step="0.01"
                            required
                        >

                    </div>

                </div>

            </section>



            <!-- PRODUCT IMAGE -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Product Image
                        </h2>

                        <p>
                            Replace the current product image if needed.
                        </p>

                    </div>

                    <span>
                        03
                    </span>

                </div>


                <?php if (
                    $current_image !== null &&
                    $current_image !== ""
                ): ?>

                    <div class="current-image">

                        <div class="current-image-preview">

                            <img
                                src="uploads/products/<?= htmlspecialchars($current_image) ?>"
                                alt="<?= htmlspecialchars($product_name) ?>"
                            >

                        </div>

                        <div>

                            <strong>
                                Current Product Image
                            </strong>

                            <span>
                                <?= htmlspecialchars($current_image) ?>
                            </span>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="current-image">

                        <div class="current-image-preview">
                            📦
                        </div>

                        <div>

                            <strong>
                                No Product Image
                            </strong>

                            <span>
                                No image has been uploaded for this product.
                            </span>

                        </div>

                    </div>

                <?php endif; ?>



                <div class="upload-area">

                    <div class="upload-icon">
                        📷
                    </div>

                    <h3>
                        Replace Product Image
                    </h3>

                    <p>
                        JPG, JPEG or PNG • Maximum 5MB
                    </p>

                    <label
                        for="product-image"
                        class="upload-button"
                    >
                        Choose New Image
                    </label>

                    <input
                        type="file"
                        id="product-image"
                        name="product_image"
                        accept=".jpg,.jpeg,.png"
                    >

                </div>

            </section>



            <!-- LOCATION -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Product Location
                        </h2>

                        <p>
                            Update where your product is available.
                        </p>

                    </div>

                    <span>
                        04
                    </span>

                </div>


                <div class="form-grid">


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
                            required
                        >

                    </div>



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



            <!-- AVAILABILITY -->

            <section class="form-card">

                <div class="form-card-header">

                    <div>

                        <h2>
                            Product Availability
                        </h2>

                        <p>
                            Control whether consumers can purchase this product.
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
                            Product is visible and available in the marketplace.
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
                            Product will be hidden from consumers.
                        </small>

                    </span>

                </label>


                <?php if ($product["status"] === "out_of_stock"): ?>

                    <div class="stock-warning">
                        This product is currently out of stock because its quantity is 0.
                        Increase the stock quantity to make it available again.
                    </div>

                <?php endif; ?>

            </section>



            <!-- ACTIONS -->

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
                    ✓ Save Changes
                </button>

            </div>


        </form>

    </div>

</main>



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


</body>

</html>