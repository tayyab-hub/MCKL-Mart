<?php
/**
 * Cart controller — add / update / remove / clear.
 * MCKL Mart — CSC1215 Web Computing Final Project
 *
 * The cart lives in $_SESSION['cart'] as [product_id => quantity].
 * Product validity, price and stock are ALWAYS verified against the
 * database — never trusted from the client.
 *
 * Supports two modes:
 *   - AJAX (ajax=1)  -> returns JSON {success, message, cartCount}
 *   - Normal request -> sets a flash message and redirects back
 */

require_once __DIR__ . '/includes/bootstrap.php'; // shared secure session configuration
require_once __DIR__ . '/includes/db.php';        // database connection ($pdo)
require_once __DIR__ . '/includes/functions.php'; // helper functions (e.g. cart_item_count, set_flash)

if (empty($_SESSION['cart'])) {
    $_SESSION['cart'] = [];                    // make sure the cart exists as an empty array first
}

// All mutations are POST-only and require a valid session CSRF token.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use the cart form to change your cart.');
}
$isAjax = (($_POST['ajax'] ?? '') === '1');
require_valid_csrf($isAjax);
$action = input_string($_POST['action'] ?? '');
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

$success = false;                              // did the action work? default to no
$message = 'Something went wrong.';            // message shown to the user (overwritten below)
$type    = 'error';                            // message style: error / info / success

/** Look up a product (returns row or false). */
function find_product(PDO $pdo, $id)
{
    if (!$id) {
        return false;
    }
    // The ? is a placeholder filled in safely by execute() — this prevents SQL injection.
    $stmt = $pdo->prepare("SELECT product_id, name, stock FROM products WHERE product_id = ?");
    $stmt->execute([$id]);                    // run the query with the user's id plugged into the ?
    return $stmt->fetch();                     // return the matching row, or false if none
}

// Run the right block of code based on which button/link the user clicked.
switch ($action) {

    case 'add':                                // add one of this product to the cart
        $product = find_product($pdo, $productId);
        if (!$product) {
            $message = 'That product could not be found.';
            break;
        }
        $current = $_SESSION['cart'][$productId] ?? 0; // how many of this item are already in the cart
        $newQty  = $current + 1;               // add one more

        if ($product['stock'] <= 0) {          // nothing left in stock to add
            $message = $product['name'] . ' is currently out of stock.';
            break;
        }
        if ($newQty > 99 && $product['stock'] >= 99) {
            $newQty = 99;
            $message = 'Maximum 99 per item.';
            $type    = 'info';
            $_SESSION['cart'][$productId] = $newQty;
            $success = true;
            break;
        }
        if ($newQty > $product['stock']) {     // don't let them order more than is available
            $newQty  = (int) $product['stock']; // cap at the max stock
            $message = 'Only ' . $product['stock'] . ' of ' . $product['name'] . ' in stock.';
            $type    = 'info';
        } else {
            $message = '"' . $product['name'] . '" added to cart';
            $type    = 'success';
        }
        $_SESSION['cart'][$productId] = $newQty; // save the new quantity into the session cart
        $success = true;
        break;

    case 'update':                             // set this item's quantity to a value the user typed
        $product = find_product($pdo, $productId);
        if (!$product) {
            $message = 'That product could not be found.';
            break;
        }
        if ($quantity === false || $quantity === null || $quantity < 1) {
            $message = 'Enter a whole quantity from 1 to 99. Use Remove to delete an item.';
            break;
        }
        if ($quantity > 99) {                  // never allow more than 99 of the same item
            $quantity = 99;
        }
        if ($quantity > $product['stock']) {   // can't have more than is in stock
            $quantity = (int) $product['stock']; // cap at available stock
            $message  = 'Quantity adjusted to available stock (' . $product['stock'] . ').';
            $type     = 'info';
        } else {
            $message = 'Cart updated.';
            $type    = 'success';
        }
        if ($quantity <= 0) {
            unset($_SESSION['cart'][$productId]); // remove unavailable products instead of storing zero quantity
        } else {
            $_SESSION['cart'][$productId] = $quantity;
        }
        $success = true;
        break;

    case 'remove':                             // delete a single item from the cart
        if ($productId && isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]); // remove that product from the session cart
        }
        $message = 'Item removed from cart.';
        $type    = 'info';
        $success = true;
        break;

    case 'clear':                              // empty the whole cart
        $_SESSION['cart'] = [];                 // reset the cart back to an empty array
        $message = 'Cart cleared.';
        $type    = 'info';
        $success = true;
        break;

    default:                                   // any action we don't recognise
        $message = 'Unknown cart action.';
        break;
}

$cartCount = cart_item_count();                // total number of items now in the cart

// --- Respond ---
if ($isAjax) {                                 // if JavaScript called us, send back JSON instead of a page
    header('Content-Type: application/json');  // tell the browser the reply is JSON
    echo json_encode([                          // turn the result into a JSON string
        'success'   => $success,
        'message'   => $message,
        'cartCount' => $cartCount,
        'type'      => $type,
    ]);
    exit;                                       // stop here — no redirect for AJAX
}

// Non-AJAX: flash + redirect back to a safe local page.
set_flash($message, $type);                    // store the message so the next page can show it once

$redirect = 'cart.php';
// Never trust a full Referer URL: use only a known, relative PHP page.
$referer = parse_url($_SERVER['HTTP_REFERER'] ?? '');
$originHost = parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
if (is_array($referer) && isset($referer['host']) && isset($originHost)
    && strcasecmp($referer['host'], $originHost) === 0) {
    $page = basename($referer['path'] ?? '');
    if (in_array($page, ['cart.php', 'products.php', 'product_details.php', 'index.php'], true)) {
        $redirect = $page;
    }
}
header('Location: ' . $redirect);              // send the browser to the chosen page
exit;                                           // stop the script after redirecting
