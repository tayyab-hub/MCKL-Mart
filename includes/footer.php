<?php
/**
 * Shared site footer + scripts.
 * MCKL Mart — CSC1215 Web Computing Final Project
 *
 * Also turns any pending flash message into the site's toast popup,
 * so success/error feedback after a redirect keeps the original UX.
 */
$flash = get_flash(); // grab any one-time message saved before a redirect (e.g. "Logged out")
?>
    <!-- Site footer shown at the bottom of every page -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <!-- Brand column: logo, short description and social media links -->
                <div class="footer-brand">
                    <a href="index.php" class="nav-logo">
                        <img src="Logo of the website.png" alt="MCKL Mart" class="nav-logo-img">
                    </a>
                    <p>Unofficial student-built e-commerce demonstration. Orders and payments are simulated locally; no real store, delivery or payment service is provided.</p>
                </div>
                <!-- Quick links to the main pages -->
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <nav class="footer-links" aria-label="Footer navigation">
                        <a href="index.php">Home</a>
                        <a href="products.php">Products</a>
                        <a href="cart.php">Cart</a>
                        <a href="contact.php">Contact</a>
                    </nav>
                </div>
                <!-- Links that filter the products page by category -->
                <div class="footer-col">
                    <h4>Categories</h4>
                    <nav class="footer-links" aria-label="Product categories">
                        <a href="products.php?category=drinks">Drinks</a>
                        <a href="products.php?category=snacks">Snacks</a>
                        <a href="products.php?category=meals">Meals</a>
                        <a href="products.php?category=essentials">Essentials</a>
                    </nav>
                </div>
                <div class="footer-col">
                    <h4>About this demo</h4>
                    <p style="font-size:0.83rem;color:rgba(255,255,255,0.75);">Built for an academic Web Computing project. No live payment gateway or mailing list is connected.</p>
                </div>
            </div>
        </div>
        <!-- Bottom bar: copyright and project credit -->
        <div class="footer-bottom">
            <div class="container" style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:0.5rem;width:100%;">
                <p>&copy; 2026 MCKL Mart. All rights reserved.</p>
                <p>Muhammad Tayyab — Individual Web Computing Project</p>
            </div>
        </div>
    </footer>

    <script src="script.js"></script> <!-- main site JavaScript (menus, toasts, etc.) -->
    <?php if ($flash): // if a flash message exists, pop it up as a toast once the page loads ?>
    <script>
        window.addEventListener('load', function () {
            showToast(<?= json_encode($flash['message'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= json_encode($flash['type'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
        });
    </script>
    <?php endif; ?>
