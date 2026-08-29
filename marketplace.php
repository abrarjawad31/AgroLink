<?php

require_once "auth.php";
require_once "db.php";

// Only logged-in consumers can access marketplace
requireConsumer();


// ============================================================
// CURRENT CONSUMER
// ============================================================

$consumer_id = (int) $_SESSION["user_id"];


// ============================================================
// CART COUNT
// ============================================================

$cart_count = 0;

$cart_stmt = $conn->prepare("
    SELECT COALESCE(SUM(ci.quantity), 0) AS total_items
    FROM cart c
    INNER JOIN cart_items ci ON ci.cart_id = c.id
    WHERE c.consumer_id = ?
");

if ($cart_stmt) {

    $cart_stmt->bind_param("i", $consumer_id);
    $cart_stmt->execute();

    $cart_result = $cart_stmt->get_result();

    if ($cart_row = $cart_result->fetch_assoc()) {
        $cart_count = (int) $cart_row["total_items"];
    }

    $cart_stmt->close();
}


// ============================================================
// CONSUMER INFORMATION
// ============================================================

$consumer_name = "Consumer";

$user_stmt = $conn->prepare("
    SELECT name
    FROM users
    WHERE id = ?
      AND role = 'consumer'
    LIMIT 1
");

if ($user_stmt) {

    $user_stmt->bind_param("i", $consumer_id);
    $user_stmt->execute();

    $user_result = $user_stmt->get_result();

    if ($user_row = $user_result->fetch_assoc()) {
        $consumer_name = $user_row["name"];
    }

    $user_stmt->close();
}


// ============================================================
// FILTER VALUES
// ============================================================

$search = isset($_GET["search"])
    ? trim($_GET["search"])
    : "";

$category = isset($_GET["category"])
    ? trim($_GET["category"])
    : "";

$location = isset($_GET["location"])
    ? trim($_GET["location"])
    : "";

$min_price = isset($_GET["min_price"]) && $_GET["min_price"] !== ""
    ? (float) $_GET["min_price"]
    : null;

$max_price = isset($_GET["max_price"]) && $_GET["max_price"] !== ""
    ? (float) $_GET["max_price"]
    : null;

$sort = isset($_GET["sort"])
    ? $_GET["sort"]
    : "recommended";


// ============================================================
// PAGINATION
// ============================================================

$per_page = 9;

$page = isset($_GET["page"])
    ? (int) $_GET["page"]
    : 1;

if ($page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $per_page;


// ============================================================
// BUILD PRODUCT FILTER
// ============================================================

$where = [
    "p.status = 'available'",
    "p.quantity > 0",
    "u.role = 'farmer'",
    "u.status = 'active'"
];

$params = [];
$types = "";


// SEARCH
if ($search !== "") {

    $where[] = "(p.name LIKE ? OR p.category LIKE ? OR u.name LIKE ?)";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;

    $types .= "sss";
}


// CATEGORY
if ($category !== "") {

    $where[] = "p.category = ?";

    $params[] = $category;

    $types .= "s";
}


// LOCATION
if ($location !== "") {

    $where[] = "p.location = ?";

    $params[] = $location;

    $types .= "s";
}


// MIN PRICE
if ($min_price !== null) {

    $where[] = "p.price >= ?";

    $params[] = $min_price;

    $types .= "d";
}


// MAX PRICE
if ($max_price !== null) {

    $where[] = "p.price <= ?";

    $params[] = $max_price;

    $types .= "d";
}


$where_sql = implode(" AND ", $where);


// ============================================================
// SORTING
// ============================================================

$order_sql = "p.created_at DESC";

switch ($sort) {

    case "price_low":
        $order_sql = "p.price ASC";
        break;

    case "price_high":
        $order_sql = "p.price DESC";
        break;

    case "newest":
        $order_sql = "p.created_at DESC";
        break;

    case "recommended":
    default:
        $order_sql = "p.created_at DESC";
        break;
}


// ============================================================
// TOTAL PRODUCTS
// ============================================================

$count_sql = "
    SELECT COUNT(*) AS total
    FROM products p
    INNER JOIN users u ON u.id = p.farmer_id
    WHERE $where_sql
";

$count_stmt = $conn->prepare($count_sql);

$total_products = 0;

if ($count_stmt) {

    if (!empty($params)) {
        $count_stmt->bind_param($types, ...$params);
    }

    $count_stmt->execute();

    $count_result = $count_stmt->get_result();

    if ($count_row = $count_result->fetch_assoc()) {
        $total_products = (int) $count_row["total"];
    }

    $count_stmt->close();
}


$total_pages = max(1, (int) ceil($total_products / $per_page));

if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}


// ============================================================
// GET PRODUCTS
// ============================================================

$product_sql = "
    SELECT
        p.id,
        p.farmer_id,
        p.name,
        p.category,
        p.description,
        p.price,
        p.unit,
        p.quantity,
        p.location,
        p.image,
        p.status,
        p.created_at,
        u.name AS farmer_name
    FROM products p
    INNER JOIN users u
        ON u.id = p.farmer_id
    WHERE $where_sql
    ORDER BY $order_sql
    LIMIT ? OFFSET ?
";

$product_stmt = $conn->prepare($product_sql);

$products = [];

if ($product_stmt) {

    $product_params = $params;
    $product_types = $types . "ii";

    $product_params[] = $per_page;
    $product_params[] = $offset;

    $product_stmt->bind_param(
        $product_types,
        ...$product_params
    );

    $product_stmt->execute();

    $product_result = $product_stmt->get_result();

    while ($product_row = $product_result->fetch_assoc()) {
        $products[] = $product_row;
    }

    $product_stmt->close();
}


// ============================================================
// CATEGORY COUNTS
// ============================================================

$category_counts = [];

$category_stmt = $conn->prepare("
    SELECT
        category,
        COUNT(*) AS total
    FROM products p
    INNER JOIN users u
        ON u.id = p.farmer_id
    WHERE p.status = 'available'
      AND p.quantity > 0
      AND u.role = 'farmer'
      AND u.status = 'active'
    GROUP BY category
    ORDER BY category
");

if ($category_stmt) {

    $category_stmt->execute();

    $category_result = $category_stmt->get_result();

    while ($category_row = $category_result->fetch_assoc()) {

        $category_counts[$category_row["category"]]
            = (int) $category_row["total"];
    }

    $category_stmt->close();
}


// ============================================================
// LOCATIONS
// ============================================================

$locations = [];

$location_stmt = $conn->prepare("
    SELECT DISTINCT p.location
    FROM products p
    INNER JOIN users u
        ON u.id = p.farmer_id
    WHERE p.status = 'available'
      AND p.quantity > 0
      AND u.role = 'farmer'
      AND u.status = 'active'
      AND p.location IS NOT NULL
      AND p.location != ''
    ORDER BY p.location ASC
");

if ($location_stmt) {

    $location_stmt->execute();

    $location_result = $location_stmt->get_result();

    while ($location_row = $location_result->fetch_assoc()) {
        $locations[] = $location_row["location"];
    }

    $location_stmt->close();
}


// ============================================================
// HELPER FOR FILTER URL
// ============================================================

function buildPageUrl($page_number)
{
    $query = $_GET;

    $query["page"] = $page_number;

    return "marketplace.php?" . http_build_query($query);
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

    <title>Marketplace | AgroLink</title>

    <link rel="stylesheet" href="css/style.css">

    <link
        rel="stylesheet"
        href="css/marketplace.css"
    >

    <!-- Google Fonts -->

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

                <a href="consumer-dashboard.php"">
                    Home
                </a>

                <a href="marketplace.php" class="active-nav">
                    Marketplace
                </a>

                <a href="future-harvests.php">
                    Pre Bookings
                </a>

                <a href="my-orders.php">
                    My Orders
                </a>

                <a href="consumer-demands.php">
                    My Demands
                </a>

            </nav>


        <!-- CONSUMER ACTIONS -->

        <div class="consumer-actions">

            <a
                href="cart.php"
                class="cart-link"
            >

                <span>
                    🛒
                </span>

                Cart

                <span class="cart-count">
                    <?= $cart_count ?>
                </span>

            </a>


            <a
                href="consumer-profile.php"
                class="profile-link"
            >

                <span class="profile-avatar">
                    <?= htmlspecialchars(
                        strtoupper(substr($consumer_name, 0, 1))
                    ) ?>
                </span>

                <span class="profile-name">
                    <?= htmlspecialchars($consumer_name) ?>
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
     PAGE HEADER
========================================================= -->

<section class="marketplace-hero">

    <div class="container marketplace-heading">

        <span class="section-tag">
            AGROLINK MARKETPLACE
        </span>

        <h1>
            Fresh Products,
            <span>Direct From Farmers</span>
        </h1>

        <p>
            Discover fresh agricultural products from trusted
            farmers across Bangladesh.
        </p>

    </div>

</section>



<!-- =========================================================
     MARKETPLACE
========================================================= -->

<main class="marketplace">

    <div class="container marketplace-layout">


        <!-- =================================================
             FILTER SIDEBAR
        ================================================== -->

        <aside class="filter-sidebar">

            <div class="filter-header">

                <h3>
                    Filter Products
                </h3>

                <a
                    href="marketplace.php"
                    class="clear-filter"
                >
                    Clear All
                </a>

            </div>


            <!-- SEARCH -->

            <form
                action="marketplace.php"
                method="GET"
            >

                <div class="filter-group">

                    <label for="search">
                        Search
                    </label>

                    <div class="search-box">

                        <input
                            type="text"
                            id="search"
                            name="search"
                            placeholder="Search products..."
                            value="<?= htmlspecialchars($search) ?>"
                        >

                        <span>
                            🔍
                        </span>

                    </div>

                </div>


                <!-- CATEGORIES -->

                <div class="filter-group">

                    <h4>
                        Category
                    </h4>


                    <?php

                    $category_names = [
                        "Vegetables",
                        "Fruits",
                        "Grains",
                        "Dairy",
                        "Seeds",
                        "Fertilizers"
                    ];

                    foreach ($category_names as $cat):

                        $cat_count = $category_counts[$cat] ?? 0;

                    ?>

                        <label class="checkbox-item">

                            <input
                                type="radio"
                                name="category"
                                value="<?= htmlspecialchars($cat) ?>"
                                <?= $category === $cat ? "checked" : "" ?>
                            >

                            <span>
                                <?= htmlspecialchars($cat) ?>
                            </span>

                            <small>
                                <?= $cat_count ?>
                            </small>

                        </label>

                    <?php endforeach; ?>

                </div>


                <!-- LOCATION -->

                <div class="filter-group">

                    <h4>
                        Location
                    </h4>

                    <select name="location">

                        <option value="">
                            All Locations
                        </option>

                        <?php foreach ($locations as $loc): ?>

                            <option
                                value="<?= htmlspecialchars($loc) ?>"
                                <?= $location === $loc ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars($loc) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- PRICE -->

                <div class="filter-group">

                    <h4>
                        Price Range
                    </h4>

                    <div class="price-inputs">

                        <input
                            type="number"
                            name="min_price"
                            placeholder="Min"
                            min="0"
                            step="0.01"
                            value="<?= $min_price !== null ? htmlspecialchars($min_price) : "" ?>"
                        >

                        <span>
                            —
                        </span>

                        <input
                            type="number"
                            name="max_price"
                            placeholder="Max"
                            min="0"
                            step="0.01"
                            value="<?= $max_price !== null ? htmlspecialchars($max_price) : "" ?>"
                        >

                    </div>


                    <!-- Keep sort while filtering -->

                    <input
                        type="hidden"
                        name="sort"
                        value="<?= htmlspecialchars($sort) ?>"
                    >


                    <button
                        type="submit"
                        class="apply-filter"
                    >
                        Apply Filter
                    </button>

                </div>

            </form>

        </aside>



        <!-- =================================================
             PRODUCTS AREA
        ================================================== -->

        <section class="products-area">


            <!-- TOP BAR -->

            <div class="products-top">

                <div>

                    <h2>
                        Agricultural Products
                    </h2>

                    <p>

                        Showing

                        <strong>
                            <?= count($products) ?>
                        </strong>

                        of

                        <strong>
                            <?= $total_products ?>
                        </strong>

                        products

                    </p>

                </div>


                <!-- SORT -->

                <form
                    action="marketplace.php"
                    method="GET"
                    class="sort-area"
                >

                    <!-- Preserve filters -->

                    <?php if ($search !== ""): ?>

                        <input
                            type="hidden"
                            name="search"
                            value="<?= htmlspecialchars($search) ?>"
                        >

                    <?php endif; ?>


                    <?php if ($category !== ""): ?>

                        <input
                            type="hidden"
                            name="category"
                            value="<?= htmlspecialchars($category) ?>"
                        >

                    <?php endif; ?>


                    <?php if ($location !== ""): ?>

                        <input
                            type="hidden"
                            name="location"
                            value="<?= htmlspecialchars($location) ?>"
                        >

                    <?php endif; ?>


                    <?php if ($min_price !== null): ?>

                        <input
                            type="hidden"
                            name="min_price"
                            value="<?= htmlspecialchars($min_price) ?>"
                        >

                    <?php endif; ?>


                    <?php if ($max_price !== null): ?>

                        <input
                            type="hidden"
                            name="max_price"
                            value="<?= htmlspecialchars($max_price) ?>"
                        >

                    <?php endif; ?>


                    <label for="sort">
                        Sort by:
                    </label>

                    <select
                        id="sort"
                        name="sort"
                        onchange="this.form.submit()"
                    >

                        <option
                            value="recommended"
                            <?= $sort === "recommended" ? "selected" : "" ?>
                        >
                            Recommended
                        </option>

                        <option
                            value="price_low"
                            <?= $sort === "price_low" ? "selected" : "" ?>
                        >
                            Price: Low to High
                        </option>

                        <option
                            value="price_high"
                            <?= $sort === "price_high" ? "selected" : "" ?>
                        >
                            Price: High to Low
                        </option>

                        <option
                            value="newest"
                            <?= $sort === "newest" ? "selected" : "" ?>
                        >
                            Newest First
                        </option>

                    </select>

                </form>

            </div>



            <!-- =================================================
                 PRODUCT GRID
            ================================================== -->

            <div class="marketplace-product-grid">


                <?php if (empty($products)): ?>

                    <div
                        style="
                            grid-column: 1 / -1;
                            text-align: center;
                            padding: 60px 20px;
                        "
                    >

                        <h3>
                            No products found
                        </h3>

                        <p>
                            Try changing your search or filter options.
                        </p>

                        <br>

                        <a
                            href="marketplace.php"
                            class="add-cart"
                        >
                            View All Products
                        </a>

                    </div>


                <?php else: ?>


                    <?php foreach ($products as $product): ?>


                        <!-- PRODUCT CARD -->

                        <div class="market-product-card">


                            <!-- IMAGE -->

                            <div class="market-product-image">

                                <?php

                                $image = trim($product["image"] ?? "");

                                if ($image !== "") {

                                    /*
                                     * If the farmer stored a complete URL,
                                     * use it directly.
                                     *
                                     * Otherwise assume the image is inside
                                     * the uploads/products folder.
                                     */

                                    if (
                                        strpos($image, "http://") === 0 ||
                                        strpos($image, "https://") === 0
                                    ) {

                                        $image_url = $image;

                                    } else {

                                        $image_url = "uploads/products/" . $image;
                                    }

                                } else {

                                    /*
                                     * No image available.
                                     */

                                    $image_url =
                                        "https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=600&q=80";
                                }

                                ?>


                                <img
                                    src="<?= htmlspecialchars($image_url) ?>"
                                    alt="<?= htmlspecialchars($product["name"]) ?>"
                                >


                                <!-- BADGE -->

                                <span class="product-badge">

                                    <?php

                                    $category_lower =
                                        strtolower($product["category"]);

                                    if (
                                        strpos(
                                            $category_lower,
                                            "organic"
                                        ) !== false
                                    ) {

                                        echo "Organic";

                                    } else {

                                        echo "Fresh";
                                    }

                                    ?>

                                </span>


                                <!-- WISHLIST -->

                                <button
                                    type="button"
                                    class="wishlist"
                                    title="Add to wishlist"
                                >
                                    ♡
                                </button>

                            </div>



                            <!-- PRODUCT INFORMATION -->

                            <div class="market-product-info">


                                <!-- CATEGORY -->

                                <span class="market-category">

                                    <?= htmlspecialchars(
                                        $product["category"]
                                    ) ?>

                                </span>


                                <!-- PRODUCT NAME -->

                                <h3>

                                    <a
                                        href="product-details.php?id=<?= (int) $product["id"] ?>"
                                    >

                                        <?= htmlspecialchars(
                                            $product["name"]
                                        ) ?>

                                    </a>

                                </h3>


                                <!-- FARMER -->

                                <p class="market-farmer">

                                    👨‍🌾

                                    <?= htmlspecialchars(
                                        $product["farmer_name"]
                                    ) ?>

                                </p>


                                <!-- LOCATION -->

                                <?php if (!empty($product["location"])): ?>

                                    <p class="market-location">

                                        📍

                                        <?= htmlspecialchars(
                                            $product["location"]
                                        ) ?>

                                    </p>

                                <?php endif; ?>


                                <!-- PRICE + CART -->

                                <div class="market-product-bottom">


                                    <div>

                                        <strong>

                                            ৳<?= number_format(
                                                (float) $product["price"],
                                                2
                                            ) ?>

                                        </strong>

                                        <span>

                                            /

                                            <?= htmlspecialchars(
                                                $product["unit"]
                                            ) ?>

                                        </span>

                                    </div>


                                    <!-- ADD TO CART -->

                                    <a
                                        href="cart.php?add=<?= (int) $product["id"] ?>"
                                        class="add-cart"
                                    >
                                        🛒 Add
                                    </a>

                                </div>

                            </div>

                        </div>


                    <?php endforeach; ?>


                <?php endif; ?>


            </div>



            <!-- =================================================
                 PAGINATION
            ================================================== -->

            <?php if ($total_pages > 1): ?>

                <div class="pagination">


                    <!-- PREVIOUS -->

                    <?php if ($page > 1): ?>

                        <a
                            href="<?= htmlspecialchars(
                                buildPageUrl($page - 1)
                            ) ?>"
                            class="page-arrow"
                        >
                            ←
                        </a>

                    <?php else: ?>

                        <button
                            type="button"
                            class="page-arrow"
                            disabled
                        >
                            ←
                        </button>

                    <?php endif; ?>


                    <?php

                    /*
                     * Display a reasonable number of page buttons.
                     */

                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    ?>


                    <?php if ($start_page > 1): ?>

                        <a
                            href="<?= htmlspecialchars(
                                buildPageUrl(1)
                            ) ?>"
                            class="page-number"
                        >
                            1
                        </a>

                        <?php if ($start_page > 2): ?>

                            <span>
                                ...
                            </span>

                        <?php endif; ?>

                    <?php endif; ?>


                    <?php for (
                        $i = $start_page;
                        $i <= $end_page;
                        $i++
                    ): ?>

                        <?php if ($i == $page): ?>

                            <button
                                type="button"
                                class="page-number active-page"
                            >
                                <?= $i ?>
                            </button>

                        <?php else: ?>

                            <a
                                href="<?= htmlspecialchars(
                                    buildPageUrl($i)
                                ) ?>"
                                class="page-number"
                            >
                                <?= $i ?>
                            </a>

                        <?php endif; ?>

                    <?php endfor; ?>


                    <?php if ($end_page < $total_pages): ?>

                        <?php if ($end_page < $total_pages - 1): ?>

                            <span>
                                ...
                            </span>

                        <?php endif; ?>


                        <a
                            href="<?= htmlspecialchars(
                                buildPageUrl($total_pages)
                            ) ?>"
                            class="page-number"
                        >
                            <?= $total_pages ?>
                        </a>

                    <?php endif; ?>


                    <!-- NEXT -->

                    <?php if ($page < $total_pages): ?>

                        <a
                            href="<?= htmlspecialchars(
                                buildPageUrl($page + 1)
                            ) ?>"
                            class="page-arrow"
                        >
                            →
                        </a>

                    <?php else: ?>

                        <button
                            type="button"
                            class="page-arrow"
                            disabled
                        >
                            →
                        </button>

                    <?php endif; ?>


                </div>

            <?php endif; ?>


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


            <div class="social-links">

                <a href="#">
                    f
                </a>

                <a href="#">
                    in
                </a>

                <a href="#">
                    𝕏
                </a>

            </div>

        </div>



        <!-- MARKETPLACE -->

        <div class="footer-column">

            <h3>
                Marketplace
            </h3>

            <a href="marketplace.php">
                All Products
            </a>

            <a href="marketplace.php?category=Vegetables">
                Vegetables
            </a>

            <a href="marketplace.php?category=Fruits">
                Fruits
            </a>

            <a href="marketplace.php?category=Grains">
                Grains
            </a>

            <a href="marketplace.php?category=Dairy">
                Dairy
            </a>

        </div>



        <!-- CONSUMER -->

        <div class="footer-column">

            <h3>
                Consumer
            </h3>

            <a href="my-orders.php">
                My Orders
            </a>

            <a href="cart.php">
                Cart
            </a>

            <a href="consumer-bookings.php">
                My Bookings
            </a>

            <a href="consumer-demands.php">
                My Demands
            </a>

        </div>



        <!-- SUPPORT -->

        <div class="footer-column">

            <h3>
                Support
            </h3>

            <a href="about.html">
                About Us
            </a>

            <a href="contact.html">
                Contact Us
            </a>

            <a href="#">
                FAQ
            </a>

            <a href="#">
                Privacy Policy
            </a>

        </div>

    </div>



    <!-- FOOTER BOTTOM -->

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