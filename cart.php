<?php
/**
 * Shopping cart page.
 * MCKL Mart — CSC1215 Web Computing Final Project
 *
 * Shows items, quantities and totals. Prices are always recalculated
 * from the database in get_cart_details() — hidden values are never trusted.
 */
$page_title  = 'Your Cart | MCKL Mart';      // browser tab title for this page
$active_page = 'checkout';                   // tells the nav bar which link to highlight
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$cart     = get_cart_details($pdo);          // build cart data using live DB prices/stock
$items    = $cart['items'];                  // list of items currently in the cart
$subtotal = $cart['subtotal'];               // total price of all items
require_once __DIR__ . '/includes/header.php';
?>

    <header class="page-hero" role="banner">
        <div class="container">
            <h1>Your <span class="highlight">Cart</span></h1>
            <p>Review your items before checking out</p>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a> <span>›</span> <span>Cart</span>
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">
        <section class="checkout-section">
            <div class="container">
            <?php if (empty($items)): // show an "empty cart" message when nothing has been added ?>
                <div class="no-products" style="background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);">
                    <h3>Your cart is empty</h3>
                    <p>Looks like you haven't added anything yet.</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top:1rem;">Start Shopping →</a>
                </div>
            <?php else: ?>
                <div class="checkout-grid">

                    <!-- Cart items -->
                    <div class="checkout-form-card">
                        <div class="card-header">
                            <h2>Cart Items</h2>
                            <p><?= cart_item_count() ?> item(s) in your cart</p>
                        </div>
                        <div class="card-body">
                            <?php foreach ($items as $item): // loop over every product in the cart and show one row each ?>
                                <div class="summary-item cart-item" style="gap:1rem;padding:1rem 0;">
                                    <div class="product-img <?= e($item['category_slug']) ?>"
                                         style="width:70px;height:70px;border-radius:var(--radius-sm);flex-shrink:0;">
                                        <img src="<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>"
                                             onerror="this.style.display='none';this.parentElement.classList.add('img-error')">
                                        <span class="product-icon"><?= category_icon($item['category_slug']) ?></span>
                                    </div>

                                    <div style="flex:1;min-width:0;">
                                        <a href="product_details.php?id=<?= $item['product_id'] ?>" style="color:inherit;">
                                            <div class="item-name"><?= e($item['name']) ?></div>
                                        </a>
                                        <div style="font-size:0.82rem;color:var(--gray);">RM <?= money($item['price']) ?> each</div>

                                        <!-- Form to change this item's quantity; submits to cart_action.php -->
                                        <form method="post" action="cart_action.php"
                                              style="display:flex;align-items:center;gap:0.5rem;margin-top:0.5rem;">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="update">   <!-- tells controller to run the "update" case -->
                                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>"> <!-- which product to update -->
                                            <label for="quantity-<?= $item['product_id'] ?>" style="font-size:0.8rem;color:var(--gray);">Qty:</label>
                                            <input type="number" id="quantity-<?= $item['product_id'] ?>" name="quantity" value="<?= $item['quantity'] ?>"
                                                   min="1" max="<?= max(1, min(99, $item['stock'])) ?>"
                                                   class="form-control"
                                                   style="width:75px;padding:0.4rem 0.6rem;">
                                            <button type="submit" class="btn btn-outline-dark"
                                                    style="padding:0.4rem 0.9rem;font-size:0.8rem;">Update</button>
                                        </form>
                                        <?php if ($item['quantity'] > $item['stock']): ?>
                                            <p class="form-feedback error">Stock changed: <?= $item['stock'] ?> available. Update or remove this item.</p>
                                        <?php endif; ?>
                                    </div>

                                    <div style="text-align:right;flex-shrink:0;">
                                        <div class="item-price">RM <?= money($item['subtotal']) ?></div>
                                        <!-- Link that removes just this one item from the cart -->
                                        <form method="post" action="cart_action.php" style="display:inline;">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                            <button type="submit" style="background:none;border:0;padding:0;cursor:pointer;font:inherit;font-size:0.78rem;color:var(--danger);font-weight:600;">Remove</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div style="margin-top:1rem;">
                                <!-- Link that empties the whole cart at once -->
                                <form method="post" action="cart_action.php">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="clear">
                                    <button type="submit" class="btn btn-outline-dark"
                                            style="font-size:0.8rem;padding:0.5rem 1rem;">Clear Cart</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Totals -->
                    <aside class="order-summary" aria-label="Cart totals">
                        <div class="card-header">
                            <h2>Order Summary</h2>
                            <p>Totals calculated from live prices</p>
                        </div>
                        <div class="summary-totals">
                            <div class="summary-row"><span>Items</span><span><?= cart_item_count() ?></span></div>
                            <div class="summary-row"><span>Subtotal</span><span>RM <?= money($subtotal) ?></span></div>
                            <div class="summary-row total"><span>Total</span><span>RM <?= money($subtotal) ?></span></div>
                        </div>
                        <div style="padding:0 1.5rem 1.5rem;">
                            <!-- Button to move on to the checkout page -->
                            <a href="checkout.php" class="btn btn-primary"
                               style="width:100%;justify-content:center;padding:1rem;">Proceed to Checkout →</a>
                            <a href="products.php" class="btn btn-outline-dark"
                               style="width:100%;justify-content:center;padding:0.8rem;margin-top:0.75rem;">Continue Shopping</a>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
