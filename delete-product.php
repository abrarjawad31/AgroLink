<?php

require_once "auth.php";
require_once "db.php";

// ============================================================
// ONLY LOGGED-IN FARMERS CAN DELETE PRODUCTS
// ============================================================

requireFarmer();

$farmer_id = (int) $_SESSION["user_id"];


// ============================================================
// GET PRODUCT ID
// ============================================================

$product_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;


// ============================================================
// INVALID PRODUCT ID
// ============================================================

if ($product_id <= 0) {

    header("Location: farmer-products.php");
    exit;
}


// ============================================================
// GET PRODUCT
// ============================================================
// IMPORTANT:
// We check BOTH product ID and farmer_id.
// This prevents one farmer from deleting another farmer's product.

$stmt = $conn->prepare("
    SELECT id, image
    FROM products
    WHERE id = ?
      AND farmer_id = ?
    LIMIT 1
");


if (!$stmt) {

    header("Location: farmer-products.php");
    exit;
}


$stmt->bind_param(
    "ii",
    $product_id,
    $farmer_id
);

$stmt->execute();

$result = $stmt->get_result();

$product = $result->fetch_assoc();

$stmt->close();


// ============================================================
// PRODUCT NOT FOUND
// ============================================================

if (!$product) {

    header("Location: farmer-products.php");
    exit;
}


// ============================================================
// DELETE PRODUCT FROM DATABASE
// ============================================================

$delete_stmt = $conn->prepare("
    DELETE FROM products
    WHERE id = ?
      AND farmer_id = ?
");


if (!$delete_stmt) {

    header("Location: farmer-products.php");
    exit;
}


$delete_stmt->bind_param(
    "ii",
    $product_id,
    $farmer_id
);


if ($delete_stmt->execute()) {

    // ========================================================
    // DELETE PRODUCT IMAGE
    // ========================================================
    // Only delete the image after the database deletion
    // succeeds.

    if (!empty($product["image"])) {

        $image_path =
            __DIR__ .
            "/uploads/products/" .
            basename($product["image"]);

        if (file_exists($image_path)) {
            unlink($image_path);
        }
    }
}


$delete_stmt->close();


// ============================================================
// RETURN TO MY PRODUCTS
// ============================================================

header("Location: farmer-products.php?success=product_deleted");
exit;

?>