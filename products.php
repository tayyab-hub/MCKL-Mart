<?php
/**
 * Product listing — all products from the database.
 * Category filtering + search handled server-side via GET parameters.
 * MCKL Mart — CSC1215 Web Computing Final Project
 */
$page_title  = 'Products | MCKL Mart';                   // browser tab title
$active_page = 'products';                               // highlights the Products link in the menu
require_once __DIR__ . '/includes/header.php';           // loads the header + gives us $pdo (database connection)

// --- Read filters from the URL (e.g. products.php?category=drinks&q=tea) ---
// $_GET holds values from the URL; trim() removes extra spaces. Default to "all"/empty if missing.
$category = sanitizeInput($_GET['category'] ?? 'all');
$search   = mb_substr(sanitizeInput($_GET['q'] ?? ''), 0, 100);

// Categories for the filter buttons (drinks/snacks/meals/essentials).
$categories = $pdo->query("SELECT slug, name FROM categories ORDER BY category_id")->fetchAll();
$validSlugs = array_column($categories, 'slug');         // just the list of valid category slugs to check against

// --- Build the product query with prepared statements ---
// We start with "WHERE 1 = 1" so we can keep adding "AND ..." conditions easily below.
$sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug
        FROM products p
        JOIN categories c ON c.category_id = p.category_id
        WHERE 1 = 1";
$params = [];                                            // values that will safely fill the ? placeholders

// If a real category was chosen, only show products in that category.
if ($category !== 'all' && in_array($category, $validSlugs, true)) {
    $sql .= " AND c.slug = ?";                           // ? is a placeholder (prevents SQL injection)
    $params[] = $category;
} else {
    $category = 'all'; // normalise any unknown category back to "all"
}

// If the user typed a search term, match it against product name or category name.
if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR c.name LIKE ?)";     // LIKE with % finds partial matches
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.product_id";                        // show products in a consistent order

$stmt = $pdo->prepare($sql);                             // prepare the query (placeholders filled safely)
$stmt->execute($params);                                 // run it, passing in the filter values
$products = $stmt->fetchAll();                           // array of all matching product rows
$count = count($products);                               // how many products were found
?>

    <header class="page-hero" role="banner">
        <div class="container">
            <h1>Our <span class="highlight">Products</span></h1>
            <p>Browse our fresh selection of drinks, snacks, meals and essentials</p>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a> <span>›</span> <span>Products</span>
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">
        <div class="products-page">
            <div class="container">
                <div class="filter-section" role="search" aria-label="Product search and filter">
                    <div class="filter-top">
                        <!-- Search form: submits "q" in the URL so the PHP above can filter products -->
                        <form class="search-box" method="get" action="products.php" role="search" style="margin:0;">
                            <span class="search-icon" aria-hidden="true">🔍</span>
                            <input type="search" id="searchInput" name="q" value="<?= e($search) ?>"
                                placeholder="Search products..." aria-label="Search products" autocomplete="off">
                            <button type="submit" class="search-submit">Search</button>
                            <?php if ($category !== 'all'): // keep the current category when searching ?>
                                <input type="hidden" name="category" value="<?= e($category) ?>">
                            <?php endif; ?>
                        </form>
                        <p id="results-info" class="results-info" aria-live="polite">
                            Showing <strong id="count"><?= $count ?></strong> products
                        </p>
                    </div>
                    <!-- Category filter buttons: "All" plus one button per category from the database -->
                    <div class="filter-buttons" role="group" aria-label="Filter by category">
                        <a href="products.php<?= $search !== '' ? '?q=' . urlencode($search) : '' ?>"
                           class="filter-btn <?= $category === 'all' ? 'active' : '' ?>">All</a>
                        <?php foreach ($categories as $cat): // build one button for each category ?>
                            <?php
                            // Build the link for this category, keeping the search term if one was entered.
                            $url = 'products.php?category=' . urlencode($cat['slug']);
                            if ($search !== '') {
                                $url .= '&q=' . urlencode($search);
                            }
                            ?>
                            <a href="<?= e($url) ?>" class="filter-btn <?= $category === $cat['slug'] ? 'active' : '' ?>">
                                <?= e($cat['name']) // e() escapes text so it's safe to print on the page ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Show the matching products, or a "no products found" message if none matched -->
                <div id="products-container" aria-live="polite" aria-label="Product listings">
                    <?php if ($count > 0): ?>
                        <?php foreach ($products as $product): // loop through each product row ?>
                            <?php render_product_card($product); // helper that prints one product card ?>
                        <?php endforeach; ?>
                    <?php else: // no products matched the filter/search ?>
                        <div class="no-products">
                            <h3>No products found</h3>
                            <p>Try adjusting your search or filter selection.</p>
                            <a href="products.php" class="btn btn-primary" style="margin-top:1rem;">Reset Filters</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; // loads the shared bottom of the page ?>
</body>
</html>
