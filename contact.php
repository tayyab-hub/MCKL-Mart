<?php
/**
 * Contact Us — stores enquiries in the contact_messages table.
 * MCKL Mart — CSC1215 Web Computing Final Project
 */
require_once __DIR__ . '/includes/bootstrap.php'; // shared secure session configuration
require_once __DIR__ . '/includes/db.php';        // gives us the $pdo database connection
require_once __DIR__ . '/includes/functions.php'; // helper functions (escaping, etc.)

$errors = [];   // collects validation error messages to show next to each field
$old    = ['firstName' => '', 'lastName' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => '']; // remembers typed values to re-fill the form
if (public_demo_mode()) $old = array_replace($old, ['firstName'=>'Demo', 'lastName'=>'Visitor', 'email'=>demo_email(), 'message'=>demo_message()]);

// Readable labels for the subject dropdown values.
$subjectLabels = [
    'product'     => 'Product Enquiry',
    'order'       => 'Order / Pickup Issue',
    'feedback'    => 'General Feedback',
    'complaint'   => 'Complaint',
    'partnership' => 'Business Partnership',
    'other'       => 'Other',
];

// This block only runs when the contact form is submitted (an HTTP POST request).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $demoLease = demo_guard($pdo, 'contact');
    demo_prepare_post('contact');
    // Read each submitted field from $_POST; trim() removes extra spaces, ?? '' gives a default if missing.
    $firstName = sanitizeInput($_POST['cFirstName'] ?? '');
    $lastName  = sanitizeInput($_POST['cLastName'] ?? '');
    $email     = sanitizeInput($_POST['cEmail'] ?? '');
    $phone     = sanitizeInput($_POST['cPhone'] ?? '');
    $subject   = input_string($_POST['cSubject'] ?? '');
    $message   = sanitizeInput($_POST['cMessage'] ?? '');

    // Save all the typed values so we can re-show them if validation fails.
    $old = compact('firstName', 'lastName', 'email', 'phone', 'subject', 'message');

    // --- Server-side validation: check each field and store a message in $errors if it's wrong ---
    // Names: required, letters only, 2–100 characters.
    if (!validateName($firstName)) {
        $errors['cFirstName'] = 'Enter a valid first name (letters only, 2–100 characters).';
    }
    if (!validateName($lastName)) {
        $errors['cLastName'] = 'Enter a valid last name (letters only, 2–100 characters).';
    }
    if (mb_strlen($firstName . ' ' . $lastName) > 100) {
        $errors['cLastName'] = 'Your combined first and last name must be 100 characters or fewer.';
    }
    // Email: required and valid format.
    if (!validateEmail($email)) {
        $errors['cEmail'] = 'Please enter a valid email address.';
    }
    // Phone is optional, but if given it must be a valid Malaysian mobile number.
    $phoneNormalized = null;
    if ($phone !== '') {
        $phoneNormalized = validateMalaysiaPhone($phone);
        if ($phoneNormalized === false) {
            $errors['cPhone'] = 'Enter a valid Malaysian mobile number, e.g. 012-3456789.';
        }
    }
    // Subject must be one of the options in the dropdown.
    if (!array_key_exists($subject, $subjectLabels)) {
        $errors['cSubject'] = 'Please select a subject.';
    }
    // Message: required, between 10 and 1000 characters.
    $msgLen = mb_strlen($message);
    if ($msgLen < 10) {
        $errors['cMessage'] = 'Message must be at least 10 characters.';
    } elseif ($msgLen > 1000) {
        $errors['cMessage'] = 'Message must be 1000 characters or fewer.';
    }

    // No validation errors: save the message into the database.
    if (empty($errors)) {
        try {
        // Each ? is a placeholder safely filled by execute() — a prepared statement that prevents SQL injection.
        $stmt = $pdo->prepare(
            "INSERT INTO contact_messages (name, email, phone, subject, message)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $firstName . ' ' . $lastName,
            $email,
            $phoneNormalized,                 // cleaned Malaysian number, or null if left blank
            $subjectLabels[$subject],
            $message,
        ]);

        // PRG (Post/Redirect/Get): redirect after saving so a page refresh doesn't resubmit the message.
        $_SESSION['contact_sent'] = true;
        header('Location: contact.php?sent=1');   // redirect: send the browser to the success version of this page
        exit;                                     // stop running this script after a redirect
        } catch (PDOException $error) {
            error_log('MCKL Mart: contact submission failed');
            $errors['general'] = 'Your message could not be saved. Please try again.';
        }
    }
}

$justSent = !empty($_SESSION['contact_sent']);
unset($_SESSION['contact_sent']);

$page_title  = 'Contact Us | MCKL Mart';
$active_page = 'contact';
require_once __DIR__ . '/includes/header.php';   // outputs the shared page top (navbar, <head>, etc.)
?>

    <header class="page-hero" role="banner">
        <div class="container">
            <h1><span class="highlight">Contact Us</span></h1>
            <p>Explore how a demo enquiry form works using fictional test data.</p>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="index.php">Home</a> <span>›</span> <span>Contact</span>
            </nav>
        </div>
    </header>

    <main id="main-content" tabindex="-1">
        <section class="contact-section">
            <div class="container">
                <div class="contact-grid">

                    <div class="contact-info">
                        <h2>About this <span class="highlight">Demo</span></h2>
                        <p>MCKL Mart is an unofficial Web Computing coursework project. It is not a real store, does not operate physical branches, and does not offer customer support or deliveries.</p>
                        <p>This form demonstrates server-side validation and database persistence. Please use fictional sample details only. Messages are stored in the demo database and are not forwarded to anyone.</p>
                        <div style="background:var(--light);padding:1.2rem;border-radius:var(--radius-sm);margin-top:1rem;">
                            <strong>Privacy reminder:</strong> Do not enter a real address, telephone number or sensitive message when trying the demo.
                        </div>
                    </div>

                    <div class="contact-form-card">
                        <div class="card-header">
                            <h2>Try a Sample Message</h2>
                            <p>Academic demonstration — saved in the demo database; no email will be sent</p>
                        </div>

                        <div class="contact-success <?= $justSent ? 'show' : '' ?>" id="contactSuccess" role="status" aria-live="polite">
                            <h3>Demo Message Saved!</h3>
                            <p>This academic demo stores sample messages. No email is sent and no reply is provided.</p>
                            <p style="margin-top:0.5rem;font-size:0.85rem;">Your message has been saved successfully.</p>
                            <a class="btn btn-primary" style="margin-top:1.5rem;" href="contact.php">Send Another Message</a>
                        </div>

                        <div class="card-body" id="contactFormWrapper" style="<?= $justSent ? 'display:none;' : '' ?>">
                            <?php if (isset($errors['general'])): ?>
                                <p class="form-feedback error" role="alert"><?= e($errors['general']) ?></p>
                            <?php endif; ?>
                            <!-- Contact form: collects the enquiry and submits it back to this page -->
                            <form id="contactForm" method="post" action="contact.php" aria-label="Contact support form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <?php demo_honeypot(); ?>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="cFirstName">First Name <span class="req">*</span></label>
                                        <input type="text" id="cFirstName" <?= public_demo_mode() ? 'readonly' : '' ?> name="cFirstName" class="form-control <?= isset($errors['cFirstName']) ? 'invalid' : '' ?>"
                                            value="<?= e($old['firstName']) ?>" placeholder="Your first name" data-validate="name">
                                        <?php if (isset($errors['cFirstName'])): ?>
                                            <div class="form-feedback error">⚠ <?= e($errors['cFirstName']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="cLastName">Last Name <span class="req">*</span></label>
                                        <input type="text" id="cLastName" <?= public_demo_mode() ? 'readonly' : '' ?> name="cLastName" class="form-control <?= isset($errors['cLastName']) ? 'invalid' : '' ?>"
                                            value="<?= e($old['lastName']) ?>" placeholder="Your last name" data-validate="name">
                                        <?php if (isset($errors['cLastName'])): ?>
                                            <div class="form-feedback error">⚠ <?= e($errors['cLastName']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="cEmail">Email Address <span class="req">*</span></label>
                                    <input type="email" id="cEmail" <?= public_demo_mode() ? 'readonly' : '' ?> name="cEmail" class="form-control <?= isset($errors['cEmail']) ? 'invalid' : '' ?>"
                                        value="<?= e($old['email']) ?>" placeholder="your@email.com" data-validate="email">
                                    <?php if (isset($errors['cEmail'])): ?>
                                        <div class="form-feedback error">⚠ <?= e($errors['cEmail']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="cPhone">Phone Number</label>
                                    <input type="tel" id="cPhone" <?= public_demo_mode() ? 'readonly' : '' ?> name="cPhone" class="form-control <?= isset($errors['cPhone']) ? 'invalid' : '' ?>"
                                        value="<?= e($old['phone']) ?>" placeholder="e.g. 012-3456789 (optional)" data-validate="phone" data-optional>
                                    <?php if (isset($errors['cPhone'])): ?>
                                        <div class="form-feedback error">⚠ <?= e($errors['cPhone']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="cSubject">Subject <span class="req">*</span></label>
                                    <select id="cSubject" name="cSubject" class="form-control <?= isset($errors['cSubject']) ? 'invalid' : '' ?>" data-validate="select">
                                        <option value="">-- Select a subject --</option>
                                        <?php foreach ($subjectLabels as $val => $label): ?>
                                            <option value="<?= $val ?>" <?= $old['subject'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['cSubject'])): ?>
                                        <div class="form-feedback error">⚠ <?= e($errors['cSubject']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="cMessage">Sample Message <span class="req">*</span></label>
                                    <textarea id="cMessage" <?= public_demo_mode() ? 'readonly' : '' ?> name="cMessage" class="form-control <?= isset($errors['cMessage']) ? 'invalid' : '' ?>" rows="5"
                                        placeholder="Type your message here..." style="resize:vertical;" data-validate="message"><?= e($old['message']) ?></textarea>
                                    <?php if (isset($errors['cMessage'])): ?>
                                        <div class="form-feedback error">⚠ <?= e($errors['cMessage']) ?></div>
                                    <?php endif; ?>
                                    <div style="font-size:0.75rem;color:var(--gray);margin-top:0.3rem;text-align:right;">
                                        <span id="charCount">0</span> / 1000 characters
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary"
                                    style="width:100%;padding:1rem;font-size:1rem;border-radius:var(--radius-sm);justify-content:center;">
                                    Save Demo Message
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </section>

    </main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
    <script>
        // Live character counter for the message box (visual nicety from the prototype).
        const cMessage = document.getElementById('cMessage');
        const charCount = document.getElementById('charCount');
        if (cMessage && charCount) {
            const sync = () => {
                if (cMessage.value.length > 1000) cMessage.value = cMessage.value.substring(0, 1000);
                charCount.textContent = [...cMessage.value].length;
            };
            cMessage.addEventListener('input', sync);
            sync();
        }
    </script>
</body>
</html>
