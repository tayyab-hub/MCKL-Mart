<?php
/**
 * Product details — full info for one product, retrieved by ID.
 * MCKL Mart — CSC1215 Web Computing Final Project
 */
$page_title  = 'Product Details | MCKL Mart';            // browser tab title
$active_page = 'products';                               // highlights the Products link in the menu
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Validate the product id from the URL (e.g. product_details.php?id=5).
// FILTER_VALIDATE_INT returns the number only if it's a valid integer, otherwise false.
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$product = null;                                         // will hold the product once we find it

if ($id) {                                               // only query if we got a valid id
    $stmt = $pdo->prepare(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         JOIN categories c ON c.category_id = p.category_id
         WHERE p.product_id = ?"                          // ? placeholder keeps the query safe (prevents SQL injection)
    );
    $stmt->execute([$id]);                               // run the query, filling ? with the id
    $product = $stmt->fetch();                            // fetch() returns ONE product row (or false if none)
}
if (!$product) {
    http_response_code(404);
}
require_once __DIR__ . '/includes/header.php';
?>

    <header class="page-hero" role="banner">
        <div class="container">
            <h1><span class="highlight"><?= $product ? e($product['name']) : 'Product Details' ?></span></h1>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a> <span>›</span>
                <a href="products.php">Products</a> <span>›</span>
                <span><?= $product ? e($product['name']) : 'Not Found' ?></span>
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">
        <section class="checkout-section">
            <div class="container">
            <?php if (!$product): // no matching product was found ?>
                <!-- Clean "not found" state using the same site design -->
                <div class="no-products" style="background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);">
                    <h3>Product not found</h3>
                    <p>Sorry, the product you are looking for does not exist or may have been removed.</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top:1rem;">← Back to Products</a>
                </div>
            <?php else: // product found, so show its full details ?>
                <?php $slug = e($product['category_slug']); $stock = (int) $product['stock']; // grab category slug + stock as a number ?>
                <div class="checkout-grid product-detail-grid">

                    <!-- Product image -->
                    <div class="product-card" style="cursor:default;">
                        <?php if (!empty($product['badge'])): ?>
                            <div class="product-badge"><?= e($product['badge']) ?></div>
                        <?php endif; ?>
                        <div class="product-img <?= $slug ?>" style="height:380px;">
                            <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>"
                                 onerror="this.style.display='none';this.parentElement.classList.add('img-error')">
                            <span class="product-icon"><?= category_icon($product['category_slug']) ?></span>
                        </div>
                    </div>

                    <!-- Product info -->
                    <div class="checkout-form-card">
                        <div class="card-body">
                            <div class="modal-category"><?= e($product['category_name']) ?></div>
                            <h2 class="modal-name"><?= e($product['name']) ?></h2>
                            <p class="modal-desc"><?= e($product['description']) ?></p>

                            <div class="modal-meta">
                                <div class="modal-meta-item"><strong><?= e($product['weight']) ?></strong>Weight</div>
                                <div class="modal-meta-item"><strong><?= e($product['calories']) ?></strong>Calories</div>
                                <div class="modal-meta-item"><strong><?= e($product['category_name']) ?></strong>Category</div>
                                <div class="modal-meta-item">
                                    <!-- Show stock left in green, or "Out" in red if none remain -->
                                    <strong style="color:<?= $stock > 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                                        <?= $stock > 0 ? $stock . ' left' : 'Out' ?>
                                    </strong>Stock
                                </div>
                            </div>

                            <div class="modal-footer">
                                <span class="modal-price">RM <?= money($product['price']) // money() formats the price nicely ?></span>
                                <?php if ($stock > 0): // in stock, so allow adding to cart ?>
                                    <button class="btn btn-primary" data-id="<?= (int) $product['product_id'] ?>"
                                            onclick="addToCart(<?= (int) $product['product_id'] ?>, this)">
                                        Add to Cart
                                    </button>
                                <?php else: // out of stock, so disable the button ?>
                                    <button class="btn btn-primary" disabled
                                            style="background:var(--gray);cursor:not-allowed;">Out of Stock</button>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top:1.5rem;display:flex;gap:0.75rem;flex-wrap:wrap;">
                                <a href="products.php" class="btn btn-outline-dark">← Continue Shopping</a>
                                <a href="cart.php" class="btn btn-primary">View Cart →</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; // loads the shared bottom of the page ?>
</body>
</html>
