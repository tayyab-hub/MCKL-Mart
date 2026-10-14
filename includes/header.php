<?php
/**
 * Shared site header (HTML head + greeting bar + navbar + account panel).
 * MCKL Mart — CSC1215 Web Computing Final Project
 *
 * Each page sets two optional variables BEFORE including this file:
 *   $page_title  — browser tab title
 *   $active_page — one of: home | products | checkout | contact (for nav highlight)
 */

require_once __DIR__ . '/bootstrap.php'; // shared secure session defaults

require_once __DIR__ . '/db.php';        // loads the $pdo database connection
require_once __DIR__ . '/functions.php'; // loads helper functions (login checks, cart, etc.)

$page_title  = $page_title  ?? 'MCKL Mart | Smart Convenience Store'; // use given title, or a default
$active_page = $active_page ?? '';                                    // which nav link to highlight
$cartCount   = cart_item_count();                                     // how many items are in the cart

// Page to return to after logging in / signing up from the slide-in panel.
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MCKL Mart — unofficial student e-commerce demonstration built using PHP and MySQL.">
    <title><?= e($page_title) ?></title> <!-- browser tab title (e() escapes it for safety) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>

    <!-- Thin greeting strip shown above the navbar -->
    <div class="greeting-bar">
        <div class="container">
            <p id="greeting-text">Welcome to MCKL Mart!</p>
        </div>
    </div>

    <!-- Main navigation bar shown at the top of every page -->
    <nav class="navbar" role="navigation" aria-label="Main navigation">
        <div class="container">
            <a href="index.php" class="nav-logo" aria-label="MCKL Mart Home">
                <img src="Logo of the website.png" alt="MCKL Mart" class="nav-logo-img">
            </a>
            <!-- Navigation links; the chosen page gets the "active" class -->
            <ul class="nav-menu" id="main-menu">
                <li><a href="index.php" class="nav-link <?= $active_page === 'home' ? 'active' : '' ?>">Home</a></li>
                <li><a href="products.php" class="nav-link <?= $active_page === 'products' ? 'active' : '' ?>">Products</a></li>
                <li><a href="contact.php" class="nav-link <?= $active_page === 'contact' ? 'active' : '' ?>">Contact</a></li>
                <?php if (is_logged_in()): // only show "My Orders" when a user is logged in ?>
                    <li><a href="order_history.php" class="nav-link <?= $active_page === 'orders' ? 'active' : '' ?>">My Orders</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav-actions">
                <!-- Account button: opens the slide-in panel (no page change) -->
                <button class="account-btn" id="accountBtn" aria-label="Account" aria-haspopup="dialog">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <!-- Show the user's first name if logged in, otherwise just "Account" -->
                <span class="account-btn-text"><?= is_logged_in() ? e(current_user_first_name()) : 'Account' ?></span>
                </button>

                <!-- Cart button; the number shows how many items are in the cart -->
                <a class="nav-cart" href="cart.php" aria-label="View cart">
                    🛒 Cart <span class="cart-count"><?= $cartCount ?></span>
                </a>

                <!-- Hamburger button: toggles the menu on small/mobile screens -->
                <button class="hamburger" aria-label="Toggle menu" aria-expanded="false" aria-controls="main-menu">
                    <span></span><span></span><span></span>
                </button>
            </div>
        </div>
    </nav>

    <?php if (public_demo_mode()): ?>
    <div class="demo-notice" role="note">
        <div class="container"><strong>Academic demo — fictional details only.</strong>
        Personal fields use generated sample data. Use a temporary password you do not use elsewhere.
        Orders, payments and delivery are simulated. Demo records may be removed by the owner.</div>
    </div>
    <?php endif; ?>

    <!-- ============================================================= -->
    <!--  Account slide-in panel (Login / Sign Up tabs OR account menu) -->
    <!-- ============================================================= -->
    <div class="auth-overlay" id="authOverlay" aria-hidden="true"></div>
    <aside class="auth-panel" id="authPanel" role="dialog" aria-modal="true" aria-label="Account" aria-hidden="true">
        <button class="auth-close" id="authClose" aria-label="Close account panel">&times;</button>

        <?php if (is_logged_in()): // logged in -> show account menu; otherwise show login/signup forms ?>
            <!-- Logged-in: account menu -->
            <div class="auth-account">
                <!-- Avatar circle showing the first letter of the user's name -->
                <div class="auth-avatar"><?= e(strtoupper(mb_substr(current_user_name(), 0, 1))) ?></div>
                <h3>Hi, <?= e(current_user_first_name()) ?>!</h3>
                <p>You are signed in to MCKL Mart.</p>
                <?php if (public_demo_mode() && !empty($_SESSION['demo_account_email'])): ?>
                    <p>Save this demo email for your next login:<br><strong style="overflow-wrap:anywhere;"><?= e($_SESSION['demo_account_email']) ?></strong></p>
                <?php endif; ?>
                <a href="order_history.php" class="btn btn-primary auth-btn-block">My Orders</a>
                <form method="post" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button type="submit" class="btn btn-outline-dark auth-btn-block">Logout</button>
                </form>
            </div>
        <?php else: ?>
            <!-- Logged-out: Login / Sign Up tabs -->
            <div class="auth-header">
                <div class="auth-brand" aria-label="MCKL Mart">
                    <span class="auth-brand-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                    </span>
                    <span class="auth-brand-text"><span class="auth-brand-mckl">MCKL</span><span class="auth-brand-mart">Mart</span></span>
                </div>
                <p>Welcome — sign in or create an account</p>
                <?php if (public_demo_mode()): ?><p>Save your generated demo email and choose a temporary password. No email is sent.</p><?php endif; ?>
            </div>

            <div class="auth-tabs" role="tablist">
                <button class="auth-tab active" id="login-tab" data-tab="login" role="tab" aria-controls="tab-login" aria-selected="true">Login</button>
                <button class="auth-tab" id="signup-tab" data-tab="signup" role="tab" aria-controls="tab-signup" aria-selected="false" tabindex="-1">Sign Up</button>
            </div>

            <!-- Login form -> existing login.php backend -->
            <div class="auth-tab-content active" id="tab-login" role="tabpanel" aria-labelledby="login-tab">
                <form method="post" action="login.php" class="auth-form" aria-label="Login form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <?php demo_honeypot(); ?>
                    <!-- Remember which page to return to after a successful login -->
                    <input type="hidden" name="redirect" value="<?= e($currentPage) ?>">
                    <div class="form-group">
                        <label class="form-label" for="al-email">Email Address</label>
                        <input type="email" id="al-email" name="email" class="form-control" placeholder="you@email.com" required autocomplete="email" data-validate="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="al-password">Password</label>
                        <input type="password" id="al-password" name="password" class="form-control" placeholder="Your password" required autocomplete="current-password" data-validate="required">
                    </div>
                    <button type="submit" class="btn btn-primary auth-btn-block">Login</button>
                    <p class="auth-switch">New to MCKL Mart? <a href="#" data-goto="signup">Create an account</a></p>
                </form>
            </div>

            <!-- Sign-up form -> existing register.php backend -->
            <div class="auth-tab-content" id="tab-signup" role="tabpanel" aria-labelledby="signup-tab">
                <form method="post" action="register.php" class="auth-form" aria-label="Sign up form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <?php demo_honeypot(); ?>
                    <!-- Remember which page to return to after creating an account -->
                    <input type="hidden" name="redirect" value="<?= e($currentPage) ?>">
                    <div class="form-group">
                        <label class="form-label" for="as-name">Full Name</label>
                        <input type="text" id="as-name" <?= public_demo_mode() ? 'readonly' : '' ?> value="<?= public_demo_mode() ? 'Demo Visitor' : '' ?>" name="name" class="form-control" placeholder="e.g. Ahmad Razali" required autocomplete="name" data-validate="name">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="as-email">Email Address</label>
                        <input type="email" id="as-email" <?= public_demo_mode() ? 'readonly' : '' ?> value="<?= public_demo_mode() ? e(demo_email()) : '' ?>" name="email" class="form-control" placeholder="you@email.com" required autocomplete="email" data-validate="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="as-phone">Phone <span style="color:var(--gray);font-weight:400;">(optional)</span></label>
                        <input type="tel" id="as-phone" <?= public_demo_mode() ? 'readonly' : '' ?> name="phone" class="form-control" placeholder="e.g. 012-3456789" autocomplete="tel" data-validate="phone" data-optional>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="as-password">Password</label>
                        <input type="password" id="as-password" name="password" class="form-control" placeholder="Min 8 characters" required autocomplete="new-password" data-validate="password">
                        <!-- Live password checklist (script.js ticks each rule as it's met) -->
                        <ul class="password-rules" hidden>
                            <li class="password-rule" data-rule="len">8+ characters; max 72 bytes</li>
                            <li class="password-rule" data-rule="upper">Uppercase letter</li>
                            <li class="password-rule" data-rule="lower">Lowercase letter</li>
                            <li class="password-rule" data-rule="number">Number</li>
                            <li class="password-rule" data-rule="special">Special character</li>
                        </ul>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="as-confirm">Confirm Password</label>
                        <input type="password" id="as-confirm" name="confirm_password" class="form-control" placeholder="Re-type password" required autocomplete="new-password" data-validate="confirm">
                    </div>
                    <button type="submit" class="btn btn-primary auth-btn-block">Create Account</button>
                    <p class="auth-switch">Already have an account? <a href="#" data-goto="login">Login here</a></p>
                </form>
            </div>
        <?php endif; ?>
    </aside>
