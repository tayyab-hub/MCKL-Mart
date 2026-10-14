<?php
/**
 * User registration.
 * MCKL Mart — CSC1215 Web Computing Final Project
 */
require_once __DIR__ . '/includes/bootstrap.php'; // shared secure session configuration
require_once __DIR__ . '/includes/db.php';         // load the database connection ($pdo)
require_once __DIR__ . '/includes/functions.php';  // load helper functions (is_logged_in, set_flash, e, etc.)

// Already logged in? No need to register again, so send them home.
if (is_logged_in()) {
    header('Location: index.php');                 // redirect to the home page
    exit;
}

$errors = [];                                      // collects validation error messages
$old    = ['name' => '', 'email' => '', 'phone' => ''];  // keeps the typed values so the form can refill them

// Only run the registration logic when the form was submitted (POST request).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    // Read each field from the form; trim removes extra spaces. ?? '' avoids errors if a field is missing.
    $name     = sanitizeInput($_POST['name'] ?? '');
    $email    = sanitizeInput($_POST['email'] ?? '');
    $phone    = sanitizeInput($_POST['phone'] ?? '');
    $password = input_string($_POST['password'] ?? '');
    $confirm  = input_string($_POST['confirm_password'] ?? '');

    $old = ['name' => $name, 'email' => $email, 'phone' => $phone];  // remember inputs to re-show in the form

    // --- Server-side validation: check the inputs are correct before saving ---
    // Name: required, 2–100 letters/spaces/'-. only (no numbers or random symbols).
    if (!validateName($name)) {
        $errors['name'] = 'Enter a valid name (2–100 letters; no numbers or symbols).';
    }
    // Email: required and must be a real email format.
    if (!validateEmail($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    // Phone is optional here, but if given it must be a valid Malaysian mobile number.
    $phoneNormalized = null;                         // will hold the cleaned "01..." number
    if ($phone !== '') {
        $phoneNormalized = validateMalaysiaPhone($phone);
        if ($phoneNormalized === false) {
            $errors['phone'] = 'Enter a valid Malaysian mobile number, e.g. 012-3456789.';
        }
    }
    // Password must be strong: 8+ chars with upper, lower, number and a symbol.
    if (!validatePassword($password)) {
        $errors['password'] = 'Use 8+ characters (maximum 72 bytes) with uppercase, lowercase, a number and a symbol.';
    }
    if ($password !== $confirm) {                   // the two password fields must match
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // --- Prevent duplicate emails: check the database for an existing account with this email ---
    if (!isset($errors['email'])) {
        // Prepared statement with ? keeps the query safe from SQL injection.
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {                      // if a row is found, the email is already taken
            $errors['email'] = 'An account with this email already exists.';
        }
    }

    // All checks passed, so create the account.
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            // password_hash() scrambles the password one-way — we never store the plain text password.
            $hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert the new user with a prepared statement (the ? placeholders prevent SQL injection).
            $stmt = $pdo->prepare(
                "INSERT INTO users (name, email, password, phone) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$name, $email, $hash, $phoneNormalized]);  // store the cleaned phone, or null if left blank

            $newUserId = (int) $pdo->lastInsertId();    // get the ID the database just gave the new user
            $linkedGuestOrder = false;

            // If this signup came right after a guest checkout, link that order to
            // the new account — but only when the order is still a guest order
            // (user_id IS NULL) AND its email matches the new account's email.
            if (!empty($_SESSION['last_guest_order_id'])) {
                $guestOrderId = (int) $_SESSION['last_guest_order_id'];
                // Attach the guest order to this new account (prepared statement keeps it safe from SQL injection).
                $link = $pdo->prepare(
                    "UPDATE orders SET user_id = ?
                     WHERE order_id = ? AND user_id IS NULL AND customer_email = ?"
                );
                $link->execute([$newUserId, $guestOrderId, $email]);
                $linkedGuestOrder = $link->rowCount() > 0;
            }
            $pdo->commit();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (($error->errorInfo[1] ?? 0) === 1062) {
                $errors['email'] = 'An account with this email already exists.';
            } else {
                error_log('MCKL Mart: registration failed');
                $errors['general'] = 'Your account could not be created. Please try again.';
            }
        }
        if (empty($errors)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['user_name'] = $name;

            if (!empty($_SESSION['last_guest_order_id'])) {

                // Clear the guest-order info from the session whether or not it linked successfully.
                unset($_SESSION['last_guest_order_id'], $_SESSION['guest_last_order'], $_SESSION['guest_email']);

                if ($linkedGuestOrder) {
                    set_flash('Account created. Your order is now linked to your account.', 'success');
                } else {
                    set_flash('Account created — welcome to MCKL Mart!', 'success');
                }
                header('Location: order_history.php');  // redirect to the order history page
                exit;
            }

            set_flash('Account created — welcome to MCKL Mart!', 'success');  // one-time welcome message

            // Decide where to send the user after registering (only allow known local pages for safety).
            $dest = safe_redirect($_POST['redirect'] ?? '', safe_redirect($_SESSION['redirect_after_login'] ?? ''));
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $dest);              // redirect to the chosen page
            exit;
        }
    }
}

$page_title  = 'Register | MCKL Mart';             // page title shown in the browser tab
$active_page = 'register';                         // tells the header which nav link to highlight
require_once __DIR__ . '/includes/header.php';     // load the shared page top (HTML head + navbar)
?>

    <header class="page-hero" role="banner">
        <div class="container">
            <h1><span class="highlight">Register</span></h1>
            <p>Create an account to start shopping with MCKL Mart</p>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a> <span>›</span> <span>Register</span>
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">
        <section class="checkout-section">
            <div class="container" style="max-width:560px;">
                <div class="checkout-form-card">
                    <div class="card-header">
                        <h2>Create Your Account</h2>
                        <p>It only takes a minute</p>
                    </div>
                    <div class="card-body">
                        <?php if (isset($errors['general'])): ?>
                            <p class="form-feedback error" role="alert"><?= e($errors['general']) ?></p>
                        <?php endif; ?>
                        <!-- Registration form: sends all the fields to this same page using POST -->
                        <form method="post" action="register.php" aria-label="Registration form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <!-- Hidden field: remembers which page to return to after registering -->
                            <input type="hidden" name="redirect" value="<?= e($_POST['redirect'] ?? ($_GET['redirect'] ?? '')) ?>">
                            <div class="form-group">
                                <label class="form-label" for="name">Full Name <span class="req">*</span></label>
                                <input type="text" id="name" name="name" class="form-control <?= isset($errors['name']) ? 'invalid' : '' ?>"
                                    value="<?= e($old['name']) ?>" placeholder="e.g. Ahmad Razali" autocomplete="name" data-validate="name">
                                <?php if (isset($errors['name'])): ?>
                                    <div class="form-feedback error">⚠ <?= e($errors['name']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="email">Email Address <span class="req">*</span></label>
                                <input type="email" id="email" name="email" class="form-control <?= isset($errors['email']) ? 'invalid' : '' ?>"
                                    value="<?= e($old['email']) ?>" placeholder="you@email.com" autocomplete="email" data-validate="email">
                                <?php if (isset($errors['email'])): ?>
                                    <div class="form-feedback error">⚠ <?= e($errors['email']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="phone">Phone Number <span style="color:var(--gray);font-weight:400;">(optional)</span></label>
                                <input type="tel" id="phone" name="phone" class="form-control <?= isset($errors['phone']) ? 'invalid' : '' ?>"
                                    value="<?= e($old['phone']) ?>" placeholder="e.g. 012-3456789" autocomplete="tel" data-validate="phone" data-optional>
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="form-feedback error">⚠ <?= e($errors['phone']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="password">Password <span class="req">*</span></label>
                                    <input type="password" id="password" name="password" class="form-control <?= isset($errors['password']) ? 'invalid' : '' ?>"
                                        placeholder="Min 8 characters" autocomplete="new-password" data-validate="password">
                                    <?php if (isset($errors['password'])): ?>
                                        <div class="form-feedback error">⚠ <?= e($errors['password']) ?></div>
                                    <?php endif; ?>
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
                                    <label class="form-label" for="confirm_password">Confirm Password <span class="req">*</span></label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control <?= isset($errors['confirm_password']) ? 'invalid' : '' ?>"
                                        placeholder="Re-type password" autocomplete="new-password" data-validate="confirm">
                                    <?php if (isset($errors['confirm_password'])): ?>
                                        <div class="form-feedback error">⚠ <?= e($errors['confirm_password']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary"
                                style="width:100%;padding:1rem;justify-content:center;border-radius:var(--radius-sm);">Create Account</button>
                        </form>

                        <p style="text-align:center;margin-top:1.5rem;font-size:0.9rem;color:var(--gray);">
                            Already have an account? <a href="login.php" style="color:var(--primary);font-weight:600;">Login here</a>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
