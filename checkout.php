<?php
/**
 * Checkout — collects order details and stores the order in the database.
 * MCKL Mart — CSC1215 Web Computing Final Project
 *
 * Works for BOTH logged-in users and guests:
 *   - Logged in  -> order is linked to the user (user_id).
 *   - Guest      -> order is saved with user_id = NULL and the guest's
 *                   details (email, address, payment) are stored on the order.
 *
 * Totals are recalculated on the server from DB prices, the order +
 * order_details are written inside a transaction, stock is reduced, and
 * the Post/Redirect/Get pattern avoids duplicate submissions.
 */

require_once __DIR__ . '/includes/bootstrap.php'; // shared secure session configuration
require_once __DIR__ . '/includes/db.php';        // gives us the $pdo database connection
require_once __DIR__ . '/includes/functions.php'; // helper functions (cart, login check, escaping, etc.)

$errors = [];   // collects validation error messages to show next to each field
$old    = [];   // remembers what the user typed so the form can be re-filled after an error

// Allowed pickup time slots — defined once and reused by validation AND the dropdown below.
$pickupSlots = ['08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00'];
// Allowed payment methods (the only values we accept from the form).
$paymentMethods = ['cash', 'card', 'ewallet'];

// Pre-fill name / phone / email from the account when logged in.
$prefill = ['name' => '', 'phone' => '', 'email' => ''];
if (is_logged_in()) {
    // The ? is a placeholder filled in by execute() — a prepared statement that prevents SQL injection.
    $uStmt = $pdo->prepare("SELECT name, phone, email FROM users WHERE user_id = ?");
    $uStmt->execute([$_SESSION['user_id']]);
    if ($u = $uStmt->fetch()) {
        $prefill['name']  = $u['name'];
        $prefill['phone'] = $u['phone'];
        $prefill['email'] = $u['email'];
    }
}

/* ----------------------------------------------------------------- *
 *  Handle order submission (logged-in users AND guests)
 * ----------------------------------------------------------------- */
// This block only runs when the form is submitted (an HTTP POST request).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $demoLease = demo_guard($pdo, 'checkout');
    demo_prepare_post('checkout', $prefill['email'] ?? '');

    // Read each submitted field from $_POST; trim() removes extra spaces, ?? '' gives a default if missing.
    $firstName   = sanitizeInput($_POST['firstName'] ?? '');
    $lastName    = sanitizeInput($_POST['lastName'] ?? '');
    $email       = sanitizeInput($_POST['email'] ?? '');
    $phone       = sanitizeInput($_POST['phone'] ?? '');
    $fulfillment = input_string($_POST['fulfillment'] ?? 'pickup');
    $pickupDate  = sanitizeInput($_POST['pickupDate'] ?? '');
    $pickupTime  = sanitizeInput($_POST['pickupTime'] ?? '');
    $address     = sanitizeInput($_POST['address'] ?? '');
    $payment     = input_string($_POST['payment'] ?? '');

    // Save all the typed values so we can re-show them if validation fails.
    $old = compact('firstName', 'lastName', 'email', 'phone', 'fulfillment', 'pickupDate', 'pickupTime', 'address', 'payment');

    // --- Server-side validation: check each field and store a message in $errors if it's wrong ---
    // Names: required, letters only, 2–100 characters.
    if (!validateName($firstName)) {
        $errors['firstName'] = 'Enter a valid first name (letters only, 2–100 characters).';
    }
    if (!validateName($lastName)) {
        $errors['lastName'] = 'Enter a valid last name (letters only, 2–100 characters).';
    }
    if (mb_strlen($firstName . ' ' . $lastName) > 100) {
        $errors['lastName'] = 'Your combined first and last name must be 100 characters or fewer.';
    }
    // Email: required and valid format.
    if (!validateEmail($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    // Phone: required and must be a valid Malaysian mobile number; keep the cleaned version.
    $phoneNormalized = validateMalaysiaPhone($phone);
    if ($phoneNormalized === false) {
        $errors['phone'] = 'Enter a valid Malaysian mobile number, e.g. 012-3456789.';
    }
    // Fulfillment must be exactly "pickup" or "delivery" (matches the DB enum).
    if (!in_array($fulfillment, ['pickup', 'delivery'], true)) {
        $errors['fulfillment'] = 'Please choose pickup or delivery.';
        $fulfillment = 'pickup';
    }
    // Payment must be one of the methods the website actually offers.
    if (!in_array($payment, $paymentMethods, true)) {
        $errors['payment'] = 'Please select a payment method.';
    }
    if ($fulfillment === 'pickup') {
        // Pickup needs a date and a real time slot from our list.
        if ($pickupDate === '') {
            $errors['pickupDate'] = 'Please choose a pickup date.';
        }
        if (!in_array($pickupTime, $pickupSlots, true)) {
            $errors['pickupTime'] = 'Please choose a valid pickup time.';
        }
        if (!isset($errors['pickupDate']) && !isset($errors['pickupTime']) && $pickupDate !== '' && in_array($pickupTime, $pickupSlots, true)) {
            $tz = new DateTimeZone('Asia/Kuala_Lumpur');
            $requested = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $pickupDate . ' ' . $pickupTime, $tz);
            if (!$requested || $requested->format('Y-m-d H:i') !== ($pickupDate . ' ' . $pickupTime)
                || $requested <= new DateTimeImmutable('now', $tz)) {
                $errors['pickupDate'] = 'Choose a valid future pickup date and time.';
            }
        }
    } else { // delivery
        // Delivery needs a proper address (5–255 chars, safe characters only).
        if (!validateAddress($address)) {
            $errors['address'] = 'Enter a complete delivery address (5–255 characters).';
        }
    }

    // Cart must contain valid items. get_cart_details() loads the cart and its prices from the database.
    $cart = get_cart_details($pdo);
    if (empty($cart['items'])) {
        set_flash('Your cart is empty.', 'error');
        header('Location: cart.php');   // redirect: send the browser to another page
        exit;                           // stop running this script after a redirect
    }

    // --- If there were no validation errors, save the order ---
    if (empty($errors)) {
        $customerName = $firstName . ' ' . $lastName;
        $pickupValue  = ($fulfillment === 'pickup') ? trim($pickupDate . ' ' . $pickupTime) : null;
        $addressValue = ($fulfillment === 'delivery') ? $address : null;
        $userId       = is_logged_in() ? (int) $_SESSION['user_id'] : null;

        try {
            $pdo->beginTransaction();

            // Lock rows in a stable order, validating current quantity, stock and price.
            // The cart preview is not authoritative at purchase time.
            $cartQuantities = $_SESSION['cart'] ?? [];
            ksort($cartQuantities, SORT_NUMERIC);
            $lockedItems = [];
            $total = 0.0;
            $lockProduct = $pdo->prepare(
                'SELECT product_id, name, price, stock FROM products WHERE product_id = ? FOR UPDATE'
            );
            foreach ($cartQuantities as $id => $qty) {
                $id = filter_var($id, FILTER_VALIDATE_INT);
                $qty = filter_var($qty, FILTER_VALIDATE_INT);
                if (!$id || !$qty || $qty < 1 || $qty > 99) {
                    throw new DomainException('Your cart contains an invalid quantity. Please update your cart.');
                }
                $lockProduct->execute([$id]);
                $product = $lockProduct->fetch();
                if (!$product || (int) $product['stock'] < $qty) {
                    throw new DomainException('One or more products are no longer available in the requested quantity. Update your cart.');
                }
                $linePrice = round((float) $product['price'] * $qty, 2);
                $total = round($total + $linePrice, 2);
                $lockedItems[] = [
                    'product_id' => (int) $product['product_id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'quantity' => $qty,
                    'subtotal' => $linePrice,
                ];
            }
            if (!$lockedItems) {
                throw new DomainException('Your cart is empty.');
            }

            $orderStmt = $pdo->prepare(
                "INSERT INTO orders
                    (user_id, customer_name, customer_phone, customer_email, customer_address, payment_method, fulfillment, pickup_time, total, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
            $orderStmt->execute([
                $userId, $customerName, $phoneNormalized, $email, $addressValue,
                $payment, $fulfillment, $pickupValue, $total,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $detailStmt = $pdo->prepare(
                "INSERT INTO order_details (order_id, product_id, product_name, unit_price, quantity, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stockStmt = $pdo->prepare(
                'UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?'
            );
            foreach ($lockedItems as $item) {
                $stockStmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                if ($stockStmt->rowCount() !== 1) {
                    throw new DomainException('Stock has changed. Please review your cart.');
                }
                $detailStmt->execute([
                    $orderId, $item['product_id'], $item['name'],
                    $item['price'], $item['quantity'], $item['subtotal'],
                ]);
            }

            $pdo->commit();   // everything worked — save all the changes to the database permanently

            // Remember this order for guests so they can (a) view their confirmation
            // and (b) link it to a new account if they sign up afterwards.
            if (!is_logged_in()) {
                $_SESSION['guest_last_order']    = $orderId; // confirmation access
                $_SESSION['last_guest_order_id'] = $orderId; // for post-signup linking
                $_SESSION['guest_email']         = $email;   // pre-fill signup email
            }

            // Clear the cart and show the confirmation page (PRG).
            $_SESSION['cart'] = [];
            header('Location: checkout.php?order=' . $orderId);
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors['general'] = $e instanceof DomainException
                ? $e->getMessage()
                : 'Sorry, we could not place your order. Please try again.';
            if (!$e instanceof DomainException) {
                error_log('MCKL Mart: checkout transaction failed');
            }
        }
    }
}

/* ----------------------------------------------------------------- *
 *  Confirmation view (after a successful order — ?order=ID)
 *  Visible to the order's owner (logged-in) OR the guest who placed it.
 * ----------------------------------------------------------------- */
$confirmedOrder = null;
if (isset($_GET['order'])) {                                       // we arrived here via the ?order=ID redirect after ordering
    $oid = filter_input(INPUT_GET, 'order', FILTER_VALIDATE_INT); // read the order ID from the URL, only if it's a valid number
    if ($oid) {
        $oStmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");   // look up that order safely
        $oStmt->execute([$oid]);
        $candidate = $oStmt->fetch();

        if ($candidate) {
            // Only show the order to the person who placed it (the matching user, or the guest in this session).
            $ownedByUser  = is_logged_in() && (int) $candidate['user_id'] === (int) $_SESSION['user_id'];
            $ownedByGuest = $candidate['user_id'] === null && (($_SESSION['guest_last_order'] ?? 0) == $oid);
            if ($ownedByUser || $ownedByGuest) {
                $confirmedOrder = $candidate;
            }
        }
    }
    if (!$confirmedOrder) {
        http_response_code(404);
    }
}

// Cart for the live order summary shown on the right of the form.
$cartView = get_cart_details($pdo);

$page_title  = 'Checkout | MCKL Mart';
$active_page = 'checkout';
require_once __DIR__ . '/includes/header.php';   // outputs the shared page top (navbar, <head>, etc.)

// Default values for the form fields (use what the user typed, otherwise their account details).
$nameParts = explode(' ', $prefill['name'], 2);
$defFirst  = $old['firstName'] ?? ($nameParts[0] ?? '');
$defLast   = $old['lastName']  ?? ($nameParts[1] ?? '');
$defEmail  = $old['email']     ?? $prefill['email'];
$defPhone  = $old['phone']     ?? $prefill['phone'];
if (public_demo_mode()) {
    $defFirst = 'Demo';
    $defLast = 'Visitor';
    $defEmail = is_demo_email($prefill['email'] ?? '') ? $prefill['email'] : demo_email();
    $defPhone = '0100000000';
    $old['address'] = '10 Example Street, Demo City';
}
$ff        = $old['fulfillment'] ?? 'pickup';
$pay       = $old['payment'] ?? 'cash';
?>

    <header class="page-hero" role="banner">
        <div class="container">
            <h1><span class="highlight">Checkout</span></h1>
            <p>Try a simulated order — no payment or delivery takes place.</p>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a> <span>›</span>
                <a href="cart.php">Cart</a> <span>›</span> <span>Checkout</span>
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">
        <section class="checkout-section">
            <div class="container">

            <?php if (empty($cartView['items']) && !$confirmedOrder): ?>
                <!-- Empty cart: cannot check out -->
                <div class="no-products" style="background:var(--white);border-radius:var(--radius);box-shadow:var(--shadow);">
                    <h3>Your cart is empty</h3>
                    <p>Add some products before checking out.</p>
                    <a href="products.php" class="btn btn-primary" style="margin-top:1rem;">Start Shopping →</a>
                </div>

            <?php elseif ($confirmedOrder): ?>
                <div class="checkout-form-card">
                    <div class="card-header"><h2>Demo order saved</h2></div>
                    <div class="card-body">
                        <p>Order #MM-<?= str_pad($confirmedOrder['order_id'], 6, '0', STR_PAD_LEFT) ?> · RM <?= money($confirmedOrder['total']) ?></p>
                        <p>No payment has been taken and no delivery will be made.</p>
                        <a href="products.php" class="btn btn-primary">Continue Shopping</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="checkout-grid">

                    <!-- Order form -->
                    <div class="checkout-form-card">
                        <?php if (!empty($errors['general'])): ?>
                            <p class="form-feedback error" role="alert" style="margin:1rem;">⚠ <?= e($errors['general']) ?></p>
                        <?php endif; ?>
                        <div class="card-header">
                            <h2>Order Details</h2>
                            <p>All fields marked with <span style="color:#ffcdd2;">*</span> are required</p>
                        </div>
                        <div class="card-body">
                            <!-- Checkout form: collects customer details and submits the order back to this page -->
                            <form id="checkoutForm" method="post" action="checkout.php"
                                  data-logged-in="<?= is_logged_in() ? '1' : '0' ?>" aria-label="Checkout form">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <?php demo_honeypot(); ?>
                                <input type="hidden" name="guest" id="guestFlag" value="0">

                                <fieldset style="border:none;padding:0;margin-bottom:1.5rem;">
                                    <legend style="font-size:1rem;font-weight:700;color:var(--secondary);margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid var(--light-gray);width:100%;">
                                        Personal Information
                                    </legend>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label" for="firstName">First Name <span class="req">*</span></label>
                                            <input type="text" id="firstName" <?= public_demo_mode() ? 'readonly' : '' ?> name="firstName" class="form-control <?= isset($errors['firstName']) ? 'invalid' : '' ?>"
                                                value="<?= e($defFirst) ?>" placeholder="e.g. Ahmad" data-validate="name">
                                            <?php if (isset($errors['firstName'])): ?>
                                                <div class="form-feedback error">⚠ <?= e($errors['firstName']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="lastName">Last Name <span class="req">*</span></label>
                                            <input type="text" id="lastName" <?= public_demo_mode() ? 'readonly' : '' ?> name="lastName" class="form-control <?= isset($errors['lastName']) ? 'invalid' : '' ?>"
                                                value="<?= e($defLast) ?>" placeholder="e.g. Razali" data-validate="name">
                                            <?php if (isset($errors['lastName'])): ?>
                                                <div class="form-feedback error">⚠ <?= e($errors['lastName']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="email">Email Address <span class="req">*</span></label>
                                        <input type="email" id="email" <?= public_demo_mode() ? 'readonly' : '' ?> name="email" class="form-control <?= isset($errors['email']) ? 'invalid' : '' ?>"
                                            value="<?= e($defEmail) ?>" placeholder="e.g. ahmad@email.com" data-validate="email">
                                        <?php if (isset($errors['email'])): ?>
                                            <div class="form-feedback error">⚠ <?= e($errors['email']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="phone">Phone Number <span class="req">*</span></label>
                                        <input type="tel" id="phone" <?= public_demo_mode() ? 'readonly' : '' ?> name="phone" class="form-control <?= isset($errors['phone']) ? 'invalid' : '' ?>"
                                            value="<?= e($defPhone) ?>" placeholder="e.g. 012-3456789" data-validate="phone">
                                        <?php if (isset($errors['phone'])): ?>
                                            <div class="form-feedback error">⚠ <?= e($errors['phone']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </fieldset>

                                <fieldset style="border:none;padding:0;margin-bottom:1.5rem;">
                                    <legend style="font-size:1rem;font-weight:700;color:var(--secondary);margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid var(--light-gray);width:100%;">
                                        Fulfillment Method
                                    </legend>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;" role="radiogroup" aria-label="Fulfillment method">
                                        <label style="cursor:pointer;">
                                            <input type="radio" name="fulfillment" value="pickup" class="ff-radio visually-hidden" <?= $ff === 'pickup' ? 'checked' : '' ?>>
                                            <div class="ff-option" data-ff="pickup" style="border:2px solid <?= $ff === 'pickup' ? 'var(--primary)' : 'var(--light-gray)' ?>;border-radius:var(--radius-sm);padding:1rem;text-align:center;transition:var(--transition);background:<?= $ff === 'pickup' ? 'var(--primary-light)' : 'var(--white)' ?>;">
                                                <div style="font-size:1.8rem;">🏬</div>
                                                <div style="font-size:0.85rem;font-weight:600;margin-top:0.3rem;">Store Pickup</div>
                                            </div>
                                        </label>
                                        <label style="cursor:pointer;">
                                            <input type="radio" name="fulfillment" value="delivery" class="ff-radio visually-hidden" <?= $ff === 'delivery' ? 'checked' : '' ?>>
                                            <div class="ff-option" data-ff="delivery" style="border:2px solid <?= $ff === 'delivery' ? 'var(--primary)' : 'var(--light-gray)' ?>;border-radius:var(--radius-sm);padding:1rem;text-align:center;transition:var(--transition);background:<?= $ff === 'delivery' ? 'var(--primary-light)' : 'var(--white)' ?>;">
                                                <div style="font-size:1.8rem;">🛵</div>
                                                <div style="font-size:0.85rem;font-weight:600;margin-top:0.3rem;">Delivery</div>
                                            </div>
                                        </label>
                                    </div>
                                    <?php if (isset($errors['fulfillment'])): ?>
                                        <div class="form-feedback error">⚠ <?= e($errors['fulfillment']) ?></div>
                                    <?php endif; ?>
                                </fieldset>

                                <fieldset id="pickupFields" style="border:none;padding:0;margin-bottom:1.5rem;<?= $ff === 'delivery' ? 'display:none;' : '' ?>">
                                    <legend style="font-size:1rem;font-weight:700;color:var(--secondary);margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid var(--light-gray);width:100%;">
                                        Pickup Details
                                    </legend>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="form-label" for="pickupDate">Pickup Date <span class="req">*</span></label>
                                            <input type="date" id="pickupDate" name="pickupDate" class="form-control <?= isset($errors['pickupDate']) ? 'invalid' : '' ?>"
                                                value="<?= e($old['pickupDate'] ?? '') ?>" min="<?= date('Y-m-d') ?>" data-validate="required">
                                            <?php if (isset($errors['pickupDate'])): ?>
                                                <div class="form-feedback error">⚠ <?= e($errors['pickupDate']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label" for="pickupTime">Pickup Time <span class="req">*</span></label>
                                            <select id="pickupTime" name="pickupTime" class="form-control <?= isset($errors['pickupTime']) ? 'invalid' : '' ?>" data-validate="select">
                                                <option value="">-- Select a time slot --</option>
                                                <?php
                                                // Reuse the same $pickupSlots list the backend validates against.
                                                foreach ($pickupSlots as $slot):
                                                    $sel = (($old['pickupTime'] ?? '') === $slot) ? 'selected' : '';
                                                ?>
                                                    <option value="<?= $slot ?>" <?= $sel ?>><?= $slot ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($errors['pickupTime'])): ?>
                                                <div class="form-feedback error">⚠ <?= e($errors['pickupTime']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </fieldset>

                                <fieldset id="deliveryFields" style="border:none;padding:0;margin-bottom:1.5rem;<?= $ff === 'pickup' ? 'display:none;' : '' ?>">
                                    <legend style="font-size:1rem;font-weight:700;color:var(--secondary);margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid var(--light-gray);width:100%;">
                                        Delivery Address
                                    </legend>
                                    <div class="form-group">
                                        <label class="form-label" for="address">Full Delivery Address <span class="req">*</span></label>
                                        <textarea id="address" <?= public_demo_mode() ? 'readonly' : '' ?> name="address" class="form-control <?= isset($errors['address']) ? 'invalid' : '' ?>" rows="3"
                                            placeholder="Unit / house no, street, area, postcode, city" style="resize:vertical;" data-validate="address"><?= e($old['address'] ?? '') ?></textarea>
                                        <?php if (isset($errors['address'])): ?>
                                            <div class="form-feedback error">⚠ <?= e($errors['address']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </fieldset>

                                <fieldset style="border:none;padding:0;margin-bottom:1.5rem;">
                                    <legend style="font-size:1rem;font-weight:700;color:var(--secondary);margin-bottom:1rem;padding-bottom:0.5rem;border-bottom:2px solid var(--light-gray);width:100%;">
                                        Payment Method
                                    </legend>
                                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem;" role="radiogroup" aria-label="Payment method">
                                        <?php
                                        $payOptions = ['cash' => ['💵', 'Cash'], 'card' => ['💳', 'Card'], 'ewallet' => ['📱', 'e-Wallet']];
                                        foreach ($payOptions as $val => [$icon, $label]):
                                            $on = ($pay === $val);
                                        ?>
                                        <label style="cursor:pointer;">
                                            <input type="radio" name="payment" value="<?= $val ?>" class="pay-radio visually-hidden" <?= $on ? 'checked' : '' ?>>
                                            <div class="pay-option" style="border:2px solid <?= $on ? 'var(--primary)' : 'var(--light-gray)' ?>;border-radius:var(--radius-sm);padding:1rem;text-align:center;transition:var(--transition);background:<?= $on ? 'var(--primary-light)' : 'var(--white)' ?>;">
                                                <div style="font-size:1.8rem;"><?= $icon ?></div>
                                                <div style="font-size:0.8rem;font-weight:600;margin-top:0.3rem;"><?= $label ?></div>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <p style="font-size:0.83rem;color:var(--gray);margin-top:0.75rem;">Academic demo only: selecting card or e-wallet does not process any real payment.</p>
                                    <?php if (isset($errors['payment'])): ?>
                                        <div class="form-feedback error">⚠ <?= e($errors['payment']) ?></div>
                                    <?php endif; ?>
                                </fieldset>

                                <button type="submit" id="placeOrderBtn" class="btn btn-primary"
                                    style="width:100%;padding:1rem;font-size:1rem;border-radius:var(--radius-sm);justify-content:center;">
                                    Place Demo Order
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Live order summary -->
                    <aside class="order-summary" aria-label="Order summary">
                        <div class="card-header">
                            <h2>Order Summary</h2>
                            <p>Your cart items</p>
                            <?php if (public_demo_mode()): ?><p>Demo limit: 10 different products, up to 3 of each.</p><?php endif; ?>
                        </div>
                        <div class="summary-items">
                            <?php foreach ($cartView['items'] as $item): ?>
                                <div class="summary-item">
                                    <div class="item-img"><?= category_icon($item['category_slug']) ?></div>
                                    <div class="item-name"><?= e($item['name']) ?> × <?= $item['quantity'] ?></div>
                                    <div class="item-price">RM <?= money($item['subtotal']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="summary-totals">
                            <div class="summary-row"><span>Subtotal</span><span>RM <?= money($cartView['subtotal']) ?></span></div>
                            <div class="summary-row total"><span>Total</span><span>RM <?= money($cartView['subtotal']) ?></span></div>
                        </div>
                    </aside>
                </div>
            <?php endif; ?>
            </div>
        </section>
    </main>

    <?php if ($confirmedOrder): ?>
        <?php $isGuestOrder = !is_logged_in() && empty($confirmedOrder['user_id']); ?>
        <?php
            // Shared order-detail block reused by both popups.
            $orderRef = 'MM-' . str_pad($confirmedOrder['order_id'], 6, '0', STR_PAD_LEFT);
            ob_start();
        ?>
            <div style="font-size:0.88rem;color:var(--gray);line-height:1.9;margin-bottom:1.5rem;text-align:left;background:var(--light);padding:1rem;border-radius:var(--radius-sm);">
                <strong>Fulfillment:</strong> <?= ucfirst(e($confirmedOrder['fulfillment'])) ?><br>
                <?php if ($confirmedOrder['pickup_time']): ?>
                    <strong>Pickup:</strong> <?= e($confirmedOrder['pickup_time']) ?><br>
                <?php endif; ?>
                <?php if (!empty($confirmedOrder['customer_address'])): ?>
                    <strong>Deliver to:</strong> <?= e($confirmedOrder['customer_address']) ?><br>
                <?php endif; ?>
                <strong>Payment:</strong> <?= ucfirst(e($confirmedOrder['payment_method'])) ?><br>
                <strong>Phone:</strong> <?= e($confirmedOrder['customer_phone']) ?><br>
                <strong>Total:</strong> RM <?= money($confirmedOrder['total']) ?><br>
                <strong>Status:</strong> <?= ucfirst(e($confirmedOrder['status'])) ?>
            </div>
        <?php $orderDetailsHtml = ob_get_clean(); ?>

        <?php if (!$isGuestOrder): ?>
        <!-- Logged-in order confirmation -->
        <div class="confirmation-overlay show" id="confirmationOverlay" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
            <div class="confirmation-card">
                <h2 id="confirm-title">Demo Order Saved</h2>
                <p>Thank you, <strong><?= e($confirmedOrder['customer_name']) ?></strong>!</p>
                <p>Your demo order is pending. No real payment or delivery takes place.</p>
                <div class="order-ref" aria-label="Order reference number"><?= $orderRef ?></div>
                <?= $orderDetailsHtml ?>
                <div style="display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;">
                    <a href="products.php" class="btn btn-primary">Continue Shopping</a>
                    <a href="order_history.php" class="btn btn-outline-dark">View My Orders</a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Guest order: success + "sign up to track" modal -->
        <div class="choice-overlay show" id="guestSignupOverlay" role="dialog" aria-modal="true" aria-labelledby="guest-confirm-title">
            <div class="choice-modal">
                <button class="choice-close" id="guestModalClose" aria-label="Close">&times;</button>
                <div class="guest-modal-icon" aria-hidden="true">🎉</div>
                <h3 id="guest-confirm-title">Demo Order Saved</h3>
                <p>No real payment or delivery takes place.</p>
                <div class="order-ref" style="margin:0.5rem auto 1rem;"><?= $orderRef ?></div>
                <?= $orderDetailsHtml ?>
                <p style="margin-bottom:1.25rem;">Create an account to view and track your orders anytime.</p>
                <div class="choice-actions">
                    <button type="button" class="btn btn-primary" id="guestSignupBtn"
                            data-email="<?= e($confirmedOrder['customer_email']) ?>">Sign Up to Track Order</button>
                    <a href="products.php" class="btn btn-outline-dark">Continue as Guest</a>
                    <button type="button" class="btn btn-outline-dark" id="guestDismissBtn">Close</button>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Checkout choice modal: only used when a guest clicks Place Order -->
    <div class="choice-overlay" id="choiceOverlay" role="dialog" aria-modal="true" aria-label="How would you like to order?">
        <div class="choice-modal">
            <button class="choice-close" id="choiceClose" aria-label="Close">&times;</button>
            <h3>Almost there!</h3>
            <p>How would you like to place your order?</p>
            <div class="choice-actions">
                <button type="button" class="btn btn-primary" id="choiceGuest">Continue as Guest</button>
                <button type="button" class="btn btn-outline-dark" id="choiceLogin">Login</button>
                <button type="button" class="btn btn-outline-dark" id="choiceSignup">Sign Up</button>
            </div>
        </div>
    </div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script>
        // --- Fulfillment toggle: show pickup fields OR delivery address ---
        document.querySelectorAll('.ff-radio').forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('.ff-option').forEach(opt => {
                    opt.style.borderColor = 'var(--light-gray)';
                    opt.style.background = 'var(--white)';
                });
                const box = radio.nextElementSibling;
                box.style.borderColor = 'var(--primary)';
                box.style.background = 'var(--primary-light)';

                const isPickup = radio.value === 'pickup';
                const pickupFields = document.getElementById('pickupFields');
                const deliveryFields = document.getElementById('deliveryFields');
                if (pickupFields)   pickupFields.style.display   = isPickup ? '' : 'none';
                if (deliveryFields) deliveryFields.style.display = isPickup ? 'none' : '';
            });
        });

        // --- Payment method highlight ---
        document.querySelectorAll('.pay-radio').forEach(radio => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('.pay-option').forEach(opt => {
                    opt.style.borderColor = 'var(--light-gray)';
                    opt.style.background = 'var(--white)';
                });
                radio.nextElementSibling.style.borderColor = 'var(--primary)';
                radio.nextElementSibling.style.background = 'var(--primary-light)';
            });
        });

        // The server supplies today's date in Asia/Kuala_Lumpur.

        // --- Place Order: guests get the choice modal first ---
        const checkoutForm = document.getElementById('checkoutForm');
        const guestFlag    = document.getElementById('guestFlag');
        const choiceOverlay = document.getElementById('choiceOverlay');

        // Front-end checkout validation (UX only — checkout.php re-validates everything on the server).
        // Reuses the shared engine in script.js, which validates every visible [data-validate] field
        // (it automatically skips the hidden pickup/delivery fieldset).
        function validateCheckout() {
            if (!window.MCKLValidate) return true;   // safety: if script.js failed to load, let the server validate
            return window.MCKLValidate.validateForm(checkoutForm);
        }

        if (checkoutForm) {
            checkoutForm.addEventListener('submit', (e) => {
                // Stop the submission if any field is invalid.
                if (!validateCheckout()) { e.preventDefault(); return; }
                const loggedIn = checkoutForm.dataset.loggedIn === '1';
                if (!loggedIn && guestFlag.value === '0') {
                    e.preventDefault();
                    choiceOverlay.classList.add('show');
                }
            });
        }

        function closeChoice() { if (choiceOverlay) choiceOverlay.classList.remove('show'); }

        const choiceGuest  = document.getElementById('choiceGuest');
        const choiceLogin  = document.getElementById('choiceLogin');
        const choiceSignup = document.getElementById('choiceSignup');
        const choiceClose  = document.getElementById('choiceClose');

        if (choiceGuest) choiceGuest.addEventListener('click', () => {
            guestFlag.value = '1';   // submit as guest
            closeChoice();
            // Re-check fields before the programmatic submit (form.submit() skips the submit event).
            if (validateCheckout()) checkoutForm.submit();
        });
        if (choiceLogin)  choiceLogin.addEventListener('click', () => { closeChoice(); window.openAuthPanel && window.openAuthPanel('login'); });
        if (choiceSignup) choiceSignup.addEventListener('click', () => { closeChoice(); window.openAuthPanel && window.openAuthPanel('signup'); });
        if (choiceClose)  choiceClose.addEventListener('click', closeChoice);
        if (choiceOverlay) choiceOverlay.addEventListener('click', (e) => { if (e.target === choiceOverlay) closeChoice(); });

        // --- Guest "sign up to track order" modal (after a guest order) ---
        const guestOverlay   = document.getElementById('guestSignupOverlay');
        const guestSignupBtn  = document.getElementById('guestSignupBtn');
        const guestDismissBtn = document.getElementById('guestDismissBtn');
        const guestModalClose = document.getElementById('guestModalClose');

        function hideGuestModal() { if (guestOverlay) guestOverlay.classList.remove('show'); }

        if (guestSignupBtn) guestSignupBtn.addEventListener('click', () => {
            const email = guestSignupBtn.dataset.email || '';
            hideGuestModal();
            // Open the existing Account panel on the Sign Up tab, pre-filled with the guest email.
            if (window.openAuthPanel) window.openAuthPanel('signup', email);
        });
        if (guestDismissBtn) guestDismissBtn.addEventListener('click', hideGuestModal);
        if (guestModalClose) guestModalClose.addEventListener('click', hideGuestModal);
        if (guestOverlay) guestOverlay.addEventListener('click', (e) => { if (e.target === guestOverlay) hideGuestModal(); });
    </script>
</body>
</html>
