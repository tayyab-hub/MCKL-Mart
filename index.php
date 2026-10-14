<?php
/**
 * Homepage — store intro + dynamic featured products.
 * MCKL Mart — CSC1215 Web Computing Final Project
 */
$page_title  = 'MCKL Mart | Smart Convenience Store';   // text shown in the browser tab
$active_page = 'home';                                   // tells the menu which link to highlight
require_once __DIR__ . '/includes/header.php';           // loads the top of the page + gives us $pdo (database connection)

// Featured products (is_featured = 1), joined with their category.
// query() runs the SQL and JOIN pulls the matching category name/slug for each product.
$featuredStmt = $pdo->query(
    "SELECT p.*, c.name AS category_name, c.slug AS category_slug
     FROM products p
     JOIN categories c ON c.category_id = p.category_id
     WHERE p.is_featured = 1
     ORDER BY p.product_id"
);
$featured = $featuredStmt->fetchAll();                   // array holding every featured product row

// Total product count for the stats strip.
// fetchColumn() returns just the single COUNT number; (int) makes sure it's a whole number.
$totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
?>

    <main id="main-content" tabindex="-1">
        <p class="demo-notice">Unofficial academic demo · No real orders, payments or deliveries</p>
        <section class="hero" aria-label="Promotional banners">
            <div class="hero-slides">
                <div class="hero-slide active" role="group" aria-label="Slide 1">
                    <div class="hero-slide-content container">
                        <div class="hero-text">
                            <span class="hero-badge">New Arrivals</span>
                            <h1 class="hero-title">Fresh Picks,<br><span class="highlight">Every Day</span></h1>
                            <p class="hero-desc">Start your day right with our curated selection of fresh beverages, wholesome snacks, and ready-to-eat meals — all under one roof.</p>
                            <div class="hero-btns">
                                <a href="products.php" class="btn btn-primary">Shop Now →</a>
                                <a href="#featured" class="btn btn-outline">View Featured</a>
                            </div>
                        </div>
                        <div class="hero-visual" aria-hidden="true">🛒</div>
                    </div>
                </div>
                <div class="hero-slide" inert aria-hidden="true" role="group" aria-label="Slide 2">
                    <div class="hero-slide-content container">
                        <div class="hero-text">
                            <span class="hero-badge">Healthy Options</span>
                            <h1 class="hero-title">Eat Smart,<br><span class="highlight">Live Better</span></h1>
                            <p class="hero-desc">Discover our range of nutritious drinks, energy snacks, and balanced meals crafted for your active lifestyle. Explore the sample catalogue.</p>
                            <div class="hero-btns">
                                <a href="products.php" class="btn btn-primary">Explore Products →</a>
                                <a href="contact.php" class="btn btn-outline">About the Demo</a>
                            </div>
                        </div>
                        <div class="hero-visual" aria-hidden="true">🛒</div>
                    </div>
                </div>
                <div class="hero-slide" role="group" aria-label="Slide 3">
                    <div class="hero-slide-content container">
                        <div class="hero-text">
                            <span class="hero-badge">Demo Shopping</span>
                            <h1 class="hero-title">Big Savings,<br><span class="highlight">Great Taste</span></h1>
                            <p class="hero-desc">Explore sample prices for your favourite snacks and drinks. Try a simulated checkout using fictional details.</p>
                            <div class="hero-btns">
                                <a href="products.php" class="btn btn-primary">Browse Products →</a>
                                <a href="cart.php" class="btn btn-outline">Order Now</a>
                            </div>
                        </div>
                        <div class="hero-visual" aria-hidden="true">🛒</div>
                    </div>
                </div>
            </div>
            <div class="slide-arrows">
                <button class="slide-arrow" id="prevSlide" aria-label="Previous slide">&#8249;</button>
                <button class="slide-arrow" id="nextSlide" aria-label="Next slide">&#8250;</button>
            </div>
            <div class="slide-indicators" role="group" aria-label="Slide controls">
                <button class="indicator active" data-slide="0" aria-label="Slide 1"></button>
                <button class="indicator" data-slide="1" aria-label="Slide 2"></button>
                <button class="indicator" data-slide="2" aria-label="Slide 3"></button>
                <button id="pauseSlides" class="slide-pause" aria-pressed="false">Pause slides</button>
            </div>
        </section>

        <div class="stats-strip" aria-label="Store highlights">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
                                <line x1="3" y1="6" x2="21" y2="6"/>
                                <path d="M16 10a4 4 0 0 1-8 0"/>
                            </svg>
                        </div>
                        <span class="stat-number"><?= $totalProducts ?></span><!-- show the live product count from the database -->
                        <span class="stat-label">Products</span>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                        </div>
                        <span class="stat-number">4</span>
                        <span class="stat-label">Categories</span>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                            </svg>
                        </div>
                        <span class="stat-number">PHP</span>
                        <span class="stat-label">Server-side Orders</span>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon-wrap">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                <circle cx="12" cy="10" r="3"/>
                            </svg>
                        </div>
                        <span class="stat-number">Demo</span>
                        <span class="stat-label">Academic Project</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Featured products section: cards generated from the database above -->
        <section class="featured" id="featured" aria-labelledby="featured-title">
            <div class="container">
                <h2 class="section-title" id="featured-title">Featured <span class="highlight">Products</span></h2>
                <p class="section-subtitle">A selection from the demonstration catalogue</p>
                <div class="products-grid" id="featured-grid">
                    <?php if ($featured): // only show the grid if we actually found featured products ?>
                        <?php foreach ($featured as $product): // loop through each featured product ?>
                            <?php render_product_card($product); // helper that prints one product card ?>
                        <?php endforeach; ?>
                    <?php else: // shown when there are no featured products ?>
                        <p class="section-subtitle">No featured products available right now.</p>
                    <?php endif; ?>
                </div>
                <div style="text-align:center;margin-top:3rem;">
                    <a href="products.php" class="btn btn-primary" style="font-size:1rem;padding:1rem 2.5rem;">View All Products →</a>
                </div>
            </div>
        </section>

        <section class="promo-section" aria-labelledby="promo-title">
            <div class="container">
                <div class="promo-content">
                    <div class="promo-text">
                        <span class="hero-badge">Why Choose Us</span>
                        <h2 id="promo-title">More Than Just a <span class="highlight">Convenience Store</span></h2>
                        <p>Explore an academic convenience-store prototype with product search, a session cart, and transaction-safe demo orders.</p>
                        <div class="promo-features">
                            <div class="promo-feature"><div class="promo-feature-icon">✓</div><span>Live database stock checks</span></div>
                            <div class="promo-feature"><div class="promo-feature-icon">✓</div><span>Prices calculated on the server</span></div>
                            <div class="promo-feature"><div class="promo-feature-icon">✓</div><span>Quick and easy ordering process</span></div>
                            <div class="promo-feature"><div class="promo-feature-icon">✓</div><span>Guest and account checkout</span></div>
                        </div>
                        <a href="products.php" class="btn btn-primary">Start Shopping →</a>
                    </div>
                    <div class="promo-visual">
                        <div class="promo-card"><strong>Drinks</strong><p>Sample beverages</p></div>
                        <div class="promo-card"><strong>Snacks</strong><p>Local &amp; imported</p></div>
                        <div class="promo-card"><strong>Meals</strong><p>Ready to eat</p></div>
                        <div class="promo-card"><strong>Essentials</strong><p>Daily needs</p></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; // loads the shared bottom of the page ?>
    <script>
        // Hero slider (visual only — unchanged from the original prototype)
        let currentSlide = 0;
        const slides = document.querySelectorAll('.hero-slide');
        const indicators = document.querySelectorAll('.indicator');
        let slideInterval;
        const hero = document.querySelector('.hero');
        const pauseSlides = document.getElementById('pauseSlides');
        let slidesPaused = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function goToSlide(index) {
            slides[currentSlide].classList.remove('active');
            indicators[currentSlide].classList.remove('active');
            currentSlide = (index + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
            indicators[currentSlide].classList.add('active');
            slides.forEach((slide, i) => {
                slide.inert = i !== currentSlide;
                slide.setAttribute('aria-hidden', String(i !== currentSlide));
                indicators[i].setAttribute('aria-pressed', String(i === currentSlide));
            });
        }

        function nextSlide() { goToSlide(currentSlide + 1); }
        function prevSlide() { goToSlide(currentSlide - 1); }
        function startAutoSlide() {
            clearInterval(slideInterval);
            if (!slidesPaused && !hero.contains(document.activeElement)) slideInterval = setInterval(nextSlide, 4500);
        }
        function resetAutoSlide() { clearInterval(slideInterval); startAutoSlide(); }

        document.getElementById('nextSlide').addEventListener('click', () => { nextSlide(); resetAutoSlide(); });
        document.getElementById('prevSlide').addEventListener('click', () => { prevSlide(); resetAutoSlide(); });
        indicators.forEach((btn, i) => { btn.addEventListener('click', () => { goToSlide(i); resetAutoSlide(); }); });
        document.querySelector('.hero').addEventListener('mouseenter', () => clearInterval(slideInterval));
        document.querySelector('.hero').addEventListener('mouseleave', startAutoSlide);
        hero.addEventListener('focusin', () => clearInterval(slideInterval));
        hero.addEventListener('focusout', () => setTimeout(startAutoSlide, 0));
        function syncPauseButton() {
            pauseSlides.textContent = slidesPaused ? 'Play slides' : 'Pause slides';
            pauseSlides.setAttribute('aria-pressed', String(slidesPaused));
        }
        pauseSlides.addEventListener('click', () => {
            slidesPaused = !slidesPaused;
            syncPauseButton();
            resetAutoSlide();
        });
        goToSlide(0);
        syncPauseButton();

        startAutoSlide();
    </script>
</body>
</html>
