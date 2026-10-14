<?php
/**
 * Logout — destroy the session safely and return home.
 * MCKL Mart — CSC1215 Web Computing Final Project
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use the Logout button to sign out.');
}
require_valid_csrf();

// Clear all session data (empties everything stored about the logged-in user).
$_SESSION = [];

// Remove the session cookie from the browser so the login is fully forgotten.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();   // get the current cookie settings
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

session_destroy(); // wipe the session data on the server

// Start a fresh session just to carry a goodbye flash message.
session_start();
session_regenerate_id(true); // give the new session a new ID (safer)
require_once __DIR__ . '/includes/functions.php'; // loads set_flash() used below
set_flash('You have been logged out.', 'success'); // message shown on the next page

header('Location: index.php'); // send the user back to the home page
exit;                          // stop the script so the redirect happens immediately
