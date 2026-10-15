<?php
/**
 * User login.
 * MCKL Mart — CSC1215 Web Computing Final Project
 */
require_once __DIR__ . '/includes/bootstrap.php'; // shared secure session configuration
require_once __DIR__ . '/includes/db.php';         // load the database connection ($pdo)
require_once __DIR__ . '/includes/functions.php';  // load helper functions (is_logged_in, set_flash, e, etc.)

// Already logged in? Send them to the home page instead of showing the login form.
if (is_logged_in()) {
    header('Location: index.php');                 // redirect = tell the browser to go to another page
    exit;                                          // stop running this script after redirecting
}

$errors = [];                                      // collects any validation/login error messages
$email  = '';                                      // remembers the typed email so the form can show it again

// Only run the login logic when the form was actually submitted (POST request).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $email    = sanitizeInput($_POST['email'] ?? '');
    $password = input_string($_POST['password'] ?? '');
    $demoLease = demo_guard($pdo, 'login', $email);

    // Validation: make sure the email looks valid and the password isn't empty.
    // NOTE: login does NOT enforce password strength — that only matters at registration.
    if (!validateEmail($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if ($password === '') {
        $errors['password'] = 'Please enter your password.';
    }

    // No input errors, so try to log the user in.
    if (empty($errors)) {
        // Look up the user by email using a prepared statement (the ? is filled in safely, preventing SQL injection).
        $stmt = $pdo->prepare("SELECT user_id, name, password FROM users WHERE email = ?");
        $stmt->execute([$email]);                  // run the query with the typed email
        $user = $stmt->fetch();                    // get the matching user row (or false if none)

        // password_verify() compares the typed password against the stored hash without ever unscrambling it.
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate the session ID to prevent session fixation attacks.
            session_regenerate_id(true);
            $_SESSION['user_id']   = (int) $user['user_id'];   // save the user's ID in the session = they are now logged in
            $_SESSION['user_name'] = $user['name'];            // save the name for greeting them later
            $_SESSION['demo_account_email'] = is_demo_email(strtolower($email)) ? strtolower($email) : '';

            set_flash('Welcome back, ' . explode(' ', $user['name'])[0] . '!', 'success');  // one-time success message (uses first name)

            // Decide where to send the user after login (only allow known local pages for safety).
            $dest = safe_redirect($_POST['redirect'] ?? '', safe_redirect($_SESSION['redirect_after_login'] ?? ''));
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $dest);              // redirect to the chosen page
            exit;
        }
        // Wrong email or password: show one generic message so attackers can't tell which field was wrong.
        $errors['general'] = 'Incorrect email or password.';
    }
}

$page_title  = 'Login | MCKL Mart';                // page title shown in the browser tab
$active_page = 'login';                            // tells the header which nav link to highlight
require_once __DIR__ . '/includes/header.php';     // load the shared page top (HTML head + navbar)
?>

    <header class="page-hero" role="banner">
        <div class="container">
            <h1><span class="highlight">Login</span></h1>
            <p>Sign in to place orders and view your order history</p>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a> <span>›</span> <span>Login</span>
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">
        <section class="checkout-section">
            <div class="container" style="max-width:520px;">
                <div class="checkout-form-card">
                    <div class="card-header">
                        <h2>Welcome Back</h2>
                        <p>Enter your credentials to continue</p>
                    </div>
                    <div class="card-body">
                        <?php if (isset($errors['general'])): ?>
                            <div class="form-feedback error" style="margin-bottom:1rem;">⚠ <?= e($errors['general']) ?></div>
                        <?php endif; ?>

                        <!-- Login form: sends the email and password to this same page using POST -->
                        <form method="post" action="login.php" aria-label="Login form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <?php demo_honeypot(); ?>
                            <!-- Hidden field: remembers which page to return to after a successful login -->
                            <input type="hidden" name="redirect" value="<?= e($_POST['redirect'] ?? ($_GET['redirect'] ?? '')) ?>">
                            <div class="form-group">
                                <label class="form-label" for="email">Email Address <span class="req">*</span></label>
                                <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'invalid' : '' ?>"
                                    value="<?= e($email) ?>" placeholder="Your saved demo email" autocomplete="email" data-validate="email">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="form-feedback error">⚠ <?= e($errors['email']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="password">Password <span class="req">*</span></label>
                                <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'invalid' : '' ?>"
                                    placeholder="Your password" autocomplete="current-password" data-validate="required">
                                <?php if (isset($errors['password'])): ?>
                                    <div class="form-feedback error">⚠ <?= e($errors['password']) ?></div>
                                <?php endif; ?>
                            </div>
                            <button type="submit" class="btn btn-primary"
                                style="width:100%;padding:1rem;justify-content:center;border-radius:var(--radius-sm);">Login</button>
                        </form>

                        <p style="text-align:center;margin-top:1.5rem;font-size:0.9rem;color:var(--gray);">
                            Don't have an account? <a href="register.php" style="color:var(--primary);font-weight:600;">Register here</a>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
