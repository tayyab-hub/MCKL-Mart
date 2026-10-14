<?php
/**
 * Shared helper functions
 * MCKL Mart — CSC1215 Web Computing Final Project
 *
 * Small, reusable helpers for security (escaping), formatting,
 * flash messages, session cart handling and product-card rendering.
 */

/** Generate an unpredictable token tied to this user's PHP session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function valid_csrf_token($submitted): bool
{
    return is_string($submitted)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

/** Abort invalid state-changing requests before writing to session/database. */
function require_valid_csrf(bool $ajax = false): void
{
    if (valid_csrf_token($_POST['csrf_token'] ?? null)) {
        return;
    }
    http_response_code(403);
    header('Cache-Control: no-store');
    if ($ajax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Session expired. Refresh this page and try again.', 'cartCount' => cart_item_count(), 'type' => 'error']);
    } else {
        echo 'The form has expired. Go back, refresh the page and try again.';
    }
    exit;
}

/* ------------------------------------------------------------------ *
 *  Security / formatting helpers
 * ------------------------------------------------------------------ */

/** Escape output to prevent XSS (use everywhere we echo DB/user data). */
function e($value)
{
    // Turns characters like < > " into safe text so user data can't run as HTML/JS.
    return htmlspecialchars(is_scalar($value) ? (string) $value : '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Format a number as a price string, e.g. 3.5 -> "3.50". */
function money($value)
{
    // Always show 2 decimal places, e.g. 3 -> "3.00".
    return number_format((float) $value, 2);
}

/* ------------------------------------------------------------------ *
 *  Reusable input-validation helpers (used by every form's backend)
 *
 *  These are the SECURITY layer: PHP always re-checks the data here,
 *  even though script.js also checks it in the browser for nicer UX.
 *  Never trust the browser — always validate again on the server.
 * ------------------------------------------------------------------ */

/** Trim surrounding spaces from a submitted value (basic clean-up). */
function sanitizeInput($value)
{
    return trim(input_string($value));
}

/** HTTP fields must be strings, not arrays; reject invalid UTF-8 and NUL bytes. */
function input_string($value, string $default = ''): string
{
    return is_string($value) && mb_check_encoding($value, 'UTF-8') && !str_contains($value, "\0")
        ? $value : $default;
}

function safe_redirect($value, string $default = 'index.php'): string
{
    $allowed = ['index.php', 'products.php', 'contact.php', 'cart.php', 'checkout.php', 'order_history.php'];
    return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
}

/**
 * Valid person name: 2–100 characters, letters/spaces/apostrophes/hyphens/dots only.
 * Rejects numbers and random symbols. \p{L} matches letters in any language.
 */
function validateName($name)
{
    $len = mb_strlen($name);
    if ($len < 2 || $len > 100) {
        return false;                                  // too short or too long
    }
    // Must start with a letter, then only letters, spaces, ' . - allowed.
    return (bool) preg_match("/^\p{L}[\p{L}\p{M} '.\-]*$/uD", $name);
}

/** Valid email: relies on PHP's built-in email format check. */
function validateEmail($email)
{
    // FILTER_VALIDATE_EMAIL rejects values like "abc", "abc@", "abc@gmail".
    return strlen($email) <= 150 && (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Strong password for registration:
 * at least 8 characters with an uppercase, a lowercase, a number and a symbol.
 */
function validatePassword($password)
{
    return mb_strlen($password) >= 8                   // length
        && strlen($password) <= 72                    // bcrypt must not silently truncate
        && !str_contains($password, "\0")
        && preg_match('/[A-Z]/', $password)            // uppercase letter
        && preg_match('/[a-z]/', $password)            // lowercase letter
        && preg_match('/[0-9]/', $password)            // a number
        && preg_match('/[^A-Za-z0-9]/', $password);    // a symbol (e.g. @ # $ ! ?)
}

/**
 * Validate a Malaysian mobile number and return it in a clean local form
 * (e.g. "0123456789"), or false if it is not a valid number.
 *
 * Accepts 0123456789, 01123456789, +60123456789, +601123456789, 60123456789.
 */
function validateMalaysiaPhone($raw)
{
    // Remove spaces, hyphens, dots and brackets so we only look at the digits.
    $p = preg_replace('/[\s\-().]/', '', input_string($raw));

    // Convert the international +60 / 60 prefix into the local "0..." form.
    if (strpos($p, '+60') === 0) {
        $p = '0' . substr($p, 3);
    } elseif (strpos($p, '60') === 0) {
        $p = '0' . substr($p, 2);
    }

    // A valid Malaysian mobile number starts with 01 and is 10–11 digits long.
    if (preg_match('/^01\d{8,9}$/D', $p)) {
        return $p;                                     // normalized number
    }
    return false;                                      // letters, too short/long, wrong start
}

/**
 * Valid delivery address: 5–255 characters using letters, numbers,
 * spaces and common address punctuation ( , . / - # ( ) ).
 */
function validateAddress($address)
{
    $len = mb_strlen($address);
    if ($len < 5 || $len > 255) {
        return false;                                  // empty/too short or too long
    }
    return (bool) preg_match("/^[\p{L}\p{N}\s,.\/\-#()]+$/u", $address);
}

/* ------------------------------------------------------------------ *
 *  Authentication helpers (session must already be started)
 * ------------------------------------------------------------------ */

/** Is a user currently logged in? */
function is_logged_in()
{
    // True if a user_id was saved in the session during login.
    return isset($_SESSION['user_id']);
}

/** Logged-in user's display name (or null). */
function current_user_name()
{
    return $_SESSION['user_name'] ?? null;
}

/** First word of the user's name, for a compact navbar greeting. */
function current_user_first_name()
{
    $name = current_user_name();
    if (!$name) {
        return '';                         // no name stored, return empty string
    }
    $parts = explode(' ', trim($name));    // split the full name on spaces
    return $parts[0];                      // return only the first word
}

/* ------------------------------------------------------------------ *
 *  Flash messages (one-off messages shown after a redirect)
 * ------------------------------------------------------------------ */

function set_flash($message, $type = 'success')
{
    // Save a message in the session to show on the NEXT page (after a redirect).
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function get_flash()
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);   // remove it so it only shows once
        return $flash;
    }
    return null;                      // nothing to show
}

/* ------------------------------------------------------------------ *
 *  Shopping cart (stored in the session as [product_id => quantity])
 * ------------------------------------------------------------------ */

/** Total number of items (sum of quantities) in the cart. */
function cart_item_count()
{
    if (empty($_SESSION['cart'])) {
        return 0;                         // cart is empty
    }
    return array_sum($_SESSION['cart']);  // add up all the quantities
}

/**
 * Load full cart details from the database.
 * Prices/stock always come from the DB — never from the client.
 *
 * @return array{items: array, subtotal: float}
 */
function get_cart_details(PDO $pdo)
{
    $items = [];
    $subtotal = 0.0;

    if (empty($_SESSION['cart'])) {
        return ['items' => $items, 'subtotal' => 0.0];
    }

    $ids = array_keys($_SESSION['cart']);  // the product IDs in the cart
    // Make one "?" placeholder per ID, e.g. "?,?,?" — filled in safely below.
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // JOIN combines product rows with their category to also get the category slug.
    $sql = "SELECT p.product_id, p.name, p.price, p.image, p.stock, c.slug AS category_slug
            FROM products p
            JOIN categories c ON c.category_id = p.category_id
            WHERE p.product_id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);                  // ? placeholders prevent SQL injection

    // fetchAll() returns every matching product as an array of rows; loop over them.
    foreach ($stmt->fetchAll() as $row) {
        $qty = (int) $_SESSION['cart'][$row['product_id']];  // how many of this item
        $lineSubtotal = $row['price'] * $qty;                // price for this line
        $subtotal += $lineSubtotal;                          // add to the running total

        $items[] = [
            'product_id'    => (int) $row['product_id'],
            'name'          => $row['name'],
            'price'         => (float) $row['price'],
            'image'         => $row['image'],
            'stock'         => (int) $row['stock'],
            'category_slug' => $row['category_slug'],
            'quantity'      => $qty,
            'subtotal'      => $lineSubtotal,
        ];
    }

    // Products removed since the last visit must not leave invisible cart items.
    $availableIds = array_column($items, 'product_id');
    foreach ($ids as $id) {
        if (!in_array((int) $id, $availableIds, true)) {
            unset($_SESSION['cart'][$id]);
            set_flash('An unavailable product was removed from your cart.', 'info');
        }
    }

    return ['items' => $items, 'subtotal' => $subtotal];
}

/* ------------------------------------------------------------------ *
 *  Presentation helpers
 * ------------------------------------------------------------------ */

/** Fallback emoji per category slug (only shown if an image fails to load). */
function category_icon($slug)
{
    // Map each category to an emoji.
    $icons = [
        'drinks'     => '🥤',
        'snacks'     => '🍪',
        'meals'      => '🍱',
        'essentials' => '🧴',
    ];
    return $icons[$slug] ?? '🛒';   // use a generic cart icon if slug isn't listed
}

/**
 * Render a single product card — identical markup/classes to the
 * original prototype so the design stays exactly the same.
 *
 * $p must contain: product_id, name, price, image, badge,
 *                  description, category_slug, category_name, stock
 */
function render_product_card(array $p)
{
    // Prepare each value; e() escapes text so it's safe to show on the page.
    $id    = (int) $p['product_id'];
    $slug  = e($p['category_slug']);
    $name  = e($p['name']);
    $icon  = category_icon($p['category_slug']);
    $img   = e($p['image']);
    $price = money($p['price']);
    $cat   = e($p['category_name']);
    $stock = (int) $p['stock'];

    // Short description (match the prototype's ~65-char teaser).
    $shortDesc = trim((string) $p['description']);
    if (mb_strlen($shortDesc) > 65) {
        $shortDesc = mb_substr($shortDesc, 0, 65) . '…';   // cut long text and add "…"
    }
    $shortDesc = e($shortDesc);

    $detailUrl = "product_details.php?id=$id";   // link to this product's detail page

    // Output the card's HTML piece by piece using the values prepared above.
    echo '<article class="product-card" aria-label="' . $name . ' - RM ' . $price . '">';

    if (!empty($p['badge'])) {
        echo '<div class="product-badge">' . e($p['badge']) . '</div>';   // optional label e.g. "New"
    }

    echo '<a class="product-img ' . $slug . '" href="' . $detailUrl . '">'
        . '<img src="' . $img . '" alt="' . $name . '" loading="lazy" '
        . 'onerror="this.style.display=\'none\';this.parentElement.classList.add(\'img-error\')">'
        . '<span class="product-icon">' . $icon . '</span>'
        . '</a>';

    echo '<div class="product-info">';
    echo '<div class="product-category">' . $cat . '</div>';
    echo '<a href="' . $detailUrl . '" style="color:inherit;"><h3 class="product-name">' . $name . '</h3></a>';
    echo '<p class="product-desc-short">' . $shortDesc . '</p>';
    echo '<div class="product-footer">';
    echo '<span class="product-price">RM ' . $price . '</span>';

    if ($stock > 0) {
        // In stock: show a working "Add to Cart" button.
        echo '<button class="add-cart-btn" data-id="' . $id . '" '
            . 'onclick="addToCart(' . $id . ', this)" '
            . 'aria-label="Add ' . $name . ' to cart">Add to Cart</button>';
    } else {
        // No stock: show a disabled "Out of Stock" button instead.
        echo '<button class="add-cart-btn" disabled '
            . 'style="background:var(--gray);cursor:not-allowed;">Out of Stock</button>';
    }

    echo '</div></div></article>';
}
