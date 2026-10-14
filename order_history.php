<?php
/**
 * Order history — shows ONLY the logged-in user's own orders.
 * MCKL Mart — CSC1215 Web Computing Final Project
 */
require_once __DIR__ . '/includes/bootstrap.php'; // shared secure session configuration
require_once __DIR__ . '/includes/db.php';             // gives us the $pdo database connection
require_once __DIR__ . '/includes/auth_check.php';     // redirects to login if user is not signed in
require_once __DIR__ . '/includes/functions.php';      // helper functions like e() and money()

$userId = $_SESSION['user_id'];                        // the logged-in user's ID from the session

// Get all orders that belong to THIS user, newest order shown first.
// The "?" is a placeholder filled in safely by execute() to prevent SQL injection.
$orderStmt = $pdo->prepare(
    "SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, order_id DESC"
);
$orderStmt->execute([$userId]);                        // run the query for this user
$orders = $orderStmt->fetchAll();                      // store every order row in an array

// Prepare a second query to fetch the items inside one order.
// We reuse this prepared statement later, once per order, inside the loop.
$detailStmt = $pdo->prepare(
    "SELECT d.order_id, d.product_name, d.unit_price, d.quantity, d.subtotal
     FROM order_details d JOIN orders o ON o.order_id = d.order_id
     WHERE o.user_id = ? ORDER BY d.order_detail_id"
);
$detailStmt->execute([$userId]);
$linesByOrder = [];
foreach ($detailStmt->fetchAll() as $line) {
    $linesByOrder[$line['order_id']][] = $line;
}

// Choose a badge colour for each order status (shown next to the order number).
$statusColors = [
    'pending'   => 'var(--accent-dark)',
    'confirmed' => 'var(--primary)',
    'completed' => 'var(--success)',
    'cancelled' => 'var(--danger)',
];

$page_title  = 'My Orders | MCKL Mart';                // text shown in the browser tab
$active_page = 'orders';                               // tells the navbar which link to highlight
require_once __DIR__ . '/includes/header.php';         // load the shared top section (nav, <head>)
?>

    <header class="page-hero" role="banner">
        <div class="container">
            <h1>My <span class="highlight">Orders</span></h1>
            <p>Track all your past and current orders</p>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a> <span>›</span> <span>My Orders</span>
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">
        <section class="checkout-section">
            <div class="container">
            <?php if (empty($orders)): ?>
                <!-- Shown only when the user has not placed any orders yet -->
                <div class="no-products" style="background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);">
                    <h3>No orders yet</h3>
                    <p>When you place an order, it will appear here.</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top:1rem;">Start Shopping →</a>
                </div>
            <?php else: ?>
                <!-- The user has orders, so loop through and display each one as a card -->
                <div style="display:flex;flex-direction:column;gap:1.5rem;">
                    <?php foreach ($orders as $order): ?>
                        <?php
                        // For the current order, fetch its list of purchased items.
                        $lines = $linesByOrder[$order['order_id']] ?? [];
                        // Pick the badge colour for this order's status (grey if unknown).
                        $color = $statusColors[$order['status']] ?? 'var(--gray)';
                        ?>
                        <article class="checkout-form-card">
                            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:0.5rem;">
                                <div>
                                    <!-- Order number padded to 6 digits, e.g. MM-000042 -->
                                    <h2>Order #MM-<?= str_pad($order['order_id'], 6, '0', STR_PAD_LEFT) ?></h2>
                                    <!-- Format the saved date into a readable form, e.g. 28 Jun 2026, 03:15 PM -->
                                    <p><?= e(date('d M Y, h:i A', strtotime($order['created_at']))) ?></p>
                                </div>
                                <span style="background:<?= $color ?>;color:#fff;padding:0.35rem 0.9rem;border-radius:50px;font-size:0.78rem;font-weight:700;text-transform:uppercase;">
                                    <?= e($order['status']) ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div style="display:flex;flex-wrap:wrap;gap:1.5rem;margin-bottom:1rem;font-size:0.88rem;color:var(--gray);">
                                    <div><strong style="color:var(--dark);">Fulfillment:</strong> <?= ucfirst(e($order['fulfillment'])) ?></div>
                                    <?php if ($order['pickup_time']): ?>
                                        <div><strong style="color:var(--dark);">Pickup:</strong> <?= e($order['pickup_time']) ?></div>
                                    <?php endif; ?>
                                    <div><strong style="color:var(--dark);">Phone:</strong> <?= e($order['customer_phone']) ?></div>
                                </div>

                                <!-- Table listing every item bought in this order -->
                                <div class="order-table-wrap" role="region" aria-label="Order items" tabindex="0">
                                <table style="width:100%;border-collapse:collapse;font-size:0.88rem;">
                                    <thead>
                                        <tr style="text-align:left;border-bottom:2px solid var(--light-gray);">
                                            <th style="padding:0.5rem 0;">Item</th>
                                            <th style="padding:0.5rem 0;text-align:center;">Unit Price</th>
                                            <th style="padding:0.5rem 0;text-align:center;">Qty</th>
                                            <th style="padding:0.5rem 0;text-align:right;">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lines as $line): ?>
                                            <!-- One row per item: name, unit price, quantity, subtotal -->
                                            <tr style="border-bottom:1px solid var(--light);">
                                                <td style="padding:0.55rem 0;font-weight:600;"><?= e($line['product_name']) ?></td>
                                                <td style="padding:0.55rem 0;text-align:center;color:var(--gray);">RM <?= money($line['unit_price']) ?></td>
                                                <td style="padding:0.55rem 0;text-align:center;color:var(--gray);"><?= (int) $line['quantity'] ?></td>
                                                <td style="padding:0.55rem 0;text-align:right;font-weight:600;">RM <?= money($line['subtotal']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <!-- Footer row showing the grand total for the whole order -->
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" style="padding:0.75rem 0;text-align:right;font-weight:800;">Total</td>
                                            <td style="padding:0.75rem 0;text-align:right;font-weight:800;color:var(--primary);">RM <?= money($order['total']) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; // load the shared bottom section of the page ?>
</body>
</html>
