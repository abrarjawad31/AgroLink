<?php

// ============================================================
// FARMER PRODUCTS PAGE
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
// PRODUCT COUNTS
// ============================================================

$totalProducts = 0;
$activeProducts = 0;
$inactiveProducts = 0;


// Total products
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE farmer_id = ?"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $totalProducts = (int) $row["total"];
}

$stmt->close();


// Available products
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE farmer_id = ?
     AND status = 'available'"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $activeProducts = (int) $row["total"];
}

$stmt->close();


// Inactive products
$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM products
     WHERE farmer_id = ?
     AND status = 'inactive'"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $inactiveProducts = (int) $row["total"];
}

$stmt->close();


// ============================================================
// GET FARMER PRODUCTS
// ============================================================

$products = [];

$stmt = $conn->prepare(
    "SELECT
        id,
        name,
        category,
        description,
        price,
        unit,
        quantity,
        location,
        image,
        status,
        created_at,
        updated_at
     FROM products
     WHERE farmer_id = ?
     ORDER BY created_at DESC"
);

$stmt->bind_param("i", $farmerId);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Products | AgroLink</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >

    <link
        rel="stylesheet"
        href="css/farmer-products.css"
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

<main class="products-page">

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
                    My Products
                </h1>

                <p>
                    Manage the agricultural products you are
                    selling on AgroLink.
                </p>

            </div>


            <a
                href="add-product.php"
                class="add-product-btn"
            >
                + Add New Product
            </a>

        </section>



        <!-- =================================================
             PRODUCT SUMMARY
        ================================================= -->

        <section class="product-summary">


            <!-- TOTAL -->

            <div class="summary-item">

                <span class="summary-icon">
                    🌾
                </span>

                <div>

                    <span>
                        Total Products
                    </span>

                    <strong>
                        <?php echo $totalProducts; ?>
                    </strong>

                </div>

            </div>


            <!-- ACTIVE -->

            <div class="summary-item">

                <span class="summary-icon">
                    🟢
                </span>

                <div>

                    <span>
                        Active
                    </span>

                    <strong>
                        <?php echo $activeProducts; ?>
                    </strong>

                </div>

            </div>


            <!-- INACTIVE -->

            <div class="summary-item">

                <span class="summary-icon">
                    ⏸️
                </span>

                <div>

                    <span>
                        Inactive
                    </span>

                    <strong>
                        <?php echo $inactiveProducts; ?>
                    </strong>

                </div>

            </div>


        </section>



        <!-- =================================================
             FILTER BAR
        ================================================= -->

        <section class="filter-bar">


            <!-- SEARCH -->

            <div class="search-box">

                <span>
                    🔍
                </span>

                <input
                    type="text"
                    id="productSearch"
                    placeholder="Search your products..."
                >

            </div>


            <!-- CATEGORY -->

            <select id="categoryFilter">

                <option value="all">
                    All Categories
                </option>

                <option value="vegetables">
                    Vegetables
                </option>

                <option value="fruits">
                    Fruits
                </option>

                <option value="grains">
                    Grains
                </option>

                <option value="dairy">
                    Dairy
                </option>

            </select>


            <!-- STATUS -->

            <select id="statusFilter">

                <option value="all">
                    All Status
                </option>

                <option value="available">
                    Active
                </option>

                <option value="inactive">
                    Inactive
                </option>

                <option value="out_of_stock">
                    Out of Stock
                </option>

            </select>


        </section>



        <!-- =================================================
             PRODUCTS CARD
        ================================================= -->

        <section class="products-card">


            <!-- TABLE HEADER -->

            <div class="table-header">

                <h2>
                    Product Listings
                </h2>

                <span id="productCount">
                    <?php echo $totalProducts; ?>
                    products
                </span>

            </div>



            <!-- TABLE -->

            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Category
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Stock
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody id="productsTableBody">


                    <?php if (count($products) > 0): ?>


                        <?php foreach ($products as $product): ?>


                            <?php

                            $status = $product["status"];

                            if ($status === "available") {

                                $statusLabel = "Active";
                                $statusClass = "active";

                            } elseif ($status === "out_of_stock") {

                                $statusLabel = "Out of Stock";
                                $statusClass = "out-of-stock";

                            } else {

                                $statusLabel = "Inactive";
                                $statusClass = "inactive";

                            }


                            $category = $product["category"];

                            $categoryLower = strtolower($category);


                            if (
                                strpos(
                                    $categoryLower,
                                    "vegetable"
                                ) !== false
                            ) {

                                $productIcon = "🥬";

                            } elseif (
                                strpos(
                                    $categoryLower,
                                    "fruit"
                                ) !== false
                            ) {

                                $productIcon = "🥭";

                            } elseif (
                                strpos(
                                    $categoryLower,
                                    "grain"
                                ) !== false ||
                                strpos(
                                    $categoryLower,
                                    "rice"
                                ) !== false
                            ) {

                                $productIcon = "🌾";

                            } elseif (
                                strpos(
                                    $categoryLower,
                                    "dairy"
                                ) !== false
                            ) {

                                $productIcon = "🥛";

                            } else {

                                $productIcon = "🌱";

                            }


                            $createdTimestamp = strtotime(
                                $product["created_at"]
                            );


                            ?>


                            <tr
                                class="product-row"
                                data-name="<?php echo e(
                                    strtolower(
                                        $product["name"]
                                    )
                                ); ?>"
                                data-category="<?php echo e(
                                    strtolower(
                                        $product["category"]
                                    )
                                ); ?>"
                                data-status="<?php echo e(
                                    $product["status"]
                                ); ?>"
                            >


                                <!-- PRODUCT -->

                                <td>

                                    <div class="product-info">


                                        <div class="product-image">

                                            <?php

                                            if (
                                                !empty(
                                                    $product["image"]
                                                )
                                            ) {

                                                $imagePath =
                                                    "uploads/products/" .
                                                    $product["image"];

                                                echo '<img src="' .
                                                    e($imagePath) .
                                                    '" alt="' .
                                                    e($product["name"]) .
                                                    '">';

                                            } else {

                                                echo $productIcon;

                                            }

                                            ?>

                                        </div>


                                        <div>

                                            <strong>
                                                <?php echo e(
                                                    $product["name"]
                                                ); ?>
                                            </strong>

                                            <span>

                                                Added

                                                <?php echo date(
                                                    "d M Y",
                                                    $createdTimestamp
                                                ); ?>

                                            </span>

                                        </div>


                                    </div>

                                </td>


                                <!-- CATEGORY -->

                                <td>
                                    <?php echo e(
                                        $product["category"]
                                    ); ?>
                                </td>


                                <!-- PRICE -->

                                <td>

                                    <strong>

                                        ৳<?php echo number_format(
                                            (float) $product["price"],
                                            2
                                        ); ?>

                                        /

                                        <?php echo e(
                                            $product["unit"]
                                        ); ?>

                                    </strong>

                                </td>


                                <!-- STOCK -->

                                <td>

                                    <?php echo number_format(
                                        (float) $product["quantity"],
                                        2
                                    ); ?>

                                    <?php echo e(
                                        $product["unit"]
                                    ); ?>

                                </td>


                                <!-- STATUS -->

                                <td>

                                    <span
                                        class="status <?php echo e(
                                            $statusClass
                                        ); ?>"
                                    >
                                        <?php echo e(
                                            $statusLabel
                                        ); ?>
                                    </span>

                                </td>


                                <!-- ACTIONS -->

                                <td>

                                    <div class="actions">


                                        <!-- EDIT -->

                                        <a
                                            href="edit-product.php?id=<?php echo (int) $product["id"]; ?>"
                                            class="edit-btn"
                                        >
                                            Edit
                                        </a>


                                        <!-- DELETE -->

                                        <a
                                            href="delete-product.php?id=<?php echo (int) $product["id"]; ?>"
                                            class="delete-btn"
                                            onclick="return confirm('Are you sure you want to delete this product?');"
                                        >
                                            Delete
                                        </a>


                                    </div>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="6"
                                class="empty-products"
                            >

                                <div>

                                    <div class="empty-icon">
                                        🌱
                                    </div>

                                    <h3>
                                        No Products Yet
                                    </h3>

                                </div>

                            </td>

                        </tr>


                    <?php endif; ?>


                    </tbody>

                </table>

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
     SEARCH / FILTER SCRIPT
========================================================= -->

<script>

const searchInput =
    document.getElementById("productSearch");

const categoryFilter =
    document.getElementById("categoryFilter");

const statusFilter =
    document.getElementById("statusFilter");

const productRows =
    document.querySelectorAll(".product-row");

const productCount =
    document.getElementById("productCount");


function filterProducts()
{

    const searchValue =
        searchInput.value.toLowerCase().trim();

    const categoryValue =
        categoryFilter.value.toLowerCase();

    const statusValue =
        statusFilter.value.toLowerCase();


    let visibleCount = 0;


    productRows.forEach(function(row)
    {

        const name =
            row.dataset.name;

        const category =
            row.dataset.category;

        const status =
            row.dataset.status;


        const matchesSearch =
            name.includes(searchValue);


        const matchesCategory =
            categoryValue === "all" ||
            category.includes(categoryValue);


        const matchesStatus =
            statusValue === "all" ||
            status === statusValue;


        if (
            matchesSearch &&
            matchesCategory &&
            matchesStatus
        ) {

            row.style.display = "";

            visibleCount++;

        } else {

            row.style.display = "none";

        }

    });


    productCount.textContent =
        visibleCount + " products";

}


searchInput.addEventListener(
    "input",
    filterProducts
);


categoryFilter.addEventListener(
    "change",
    filterProducts
);


statusFilter.addEventListener(
    "change",
    filterProducts
);

</script>


</body>

</html>