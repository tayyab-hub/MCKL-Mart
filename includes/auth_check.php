<?php
/**
 * Login guard for protected pages (e.g. order history).
 * MCKL Mart — CSC1215 Web Computing Final Project
 *
 * Include this AFTER the session is started. If the visitor is not
 * logged in, they are redirected to the login page with a friendly
 * message and a note of where they were trying to go.
 */

// Start the session only if one isn't already running (avoids an error).
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/functions.php';   // load helpers like is_logged_in()

// If the visitor is NOT logged in, block the page and send them to login.
if (!is_logged_in()) {
    set_flash('Please log in to view your orders.', 'error');
    // Remember the page the user wanted so we can return after login.
    $_SESSION['redirect_after_login'] = basename($_SERVER['PHP_SELF']);
    // Send guests home and auto-open the Account popup on the Login tab.
    header('Location: index.php?auth=login');
    exit;   // stop the rest of the protected page from running
}
