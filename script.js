// ---- Update every cart-count badge in the navbar with the latest server value ----
function updateCartDisplay(count) {
    // Loop through every element with class "cart-count" and update it
    document.querySelectorAll('.cart-count').forEach(el => {
        el.textContent = count;            // Show the new number on the badge
        el.classList.add('bump');          // Add "bump" class to trigger a little pop animation
        setTimeout(() => el.classList.remove('bump'), 350); // Remove it after 350ms so it can animate again next time
    });
}

// ---- Real "Add to Cart": stores the item server-side (PHP session) via cart_action.php ----
// The product price/stock are validated on the server — never trusted from here.
function addToCart(productId, btn) {
    const original = btn ? btn.innerHTML : '';  // Remember the button's original text to restore later
    if (btn) btn.disabled = true;               // Disable the button so it can't be clicked twice

    // Build the form data to send: which action, which product, and a flag marking this as an AJAX call
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const body = new URLSearchParams({ action: 'add', product_id: productId, ajax: '1', csrf_token: csrfToken });

    // Send the data to the PHP file in the background (without reloading the page)
    fetch('cart_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    })
        .then(res => res.json())   // Convert the server's reply from JSON text into a usable object
        .then(data => {
            updateCartDisplay(data.cartCount);  // Refresh the cart badge with the new count
            if (data.success && btn) {
                // Success: briefly show a confirmation on the button
                btn.innerHTML = '✓ Added!';
                btn.classList.add('added');
                setTimeout(() => {
                    // After 1.5s, put the button back to normal
                    btn.innerHTML = original;
                    btn.classList.remove('added');
                    btn.disabled = false;
                }, 1500);
            } else if (btn) {
                btn.disabled = false;  // Failed: re-enable the button so the user can retry
            }
            // Show a pop-up message telling the user the result
            showToast(data.message, data.type || (data.success ? 'success' : 'error'));
        })
        .catch(() => {
            // Network/server error: re-enable button and warn the user
            if (btn) btn.disabled = false;
            showToast('Could not add to cart. Please try again.', 'error');
        });
}

// ---- Show a small pop-up notification (toast) at the corner of the screen ----
function showToast(message, type = 'info') {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();   // Remove any toast already on screen so they don't stack
    const icons = { success: '✓', error: '✕', info: 'i' };  // Pick an icon based on the message type
    const toast = document.createElement('div');            // Create a new <div> for the toast
    const safeType = Object.prototype.hasOwnProperty.call(icons, type) ? type : 'info';
    toast.className = `toast ${safeType}`;
    toast.setAttribute('role', safeType === 'error' ? 'alert' : 'status');
    const icon = document.createElement('span');
    icon.className = 'toast-icon';
    icon.textContent = icons[safeType];
    const body = document.createElement('span');
    body.textContent = String(message ?? '');
    toast.append(icon, body);
    document.body.appendChild(toast);  // Add the toast to the page
    // Wait two animation frames, then add "show" so the toast slides in smoothly
    requestAnimationFrame(() => { requestAnimationFrame(() => toast.classList.add('show')); });
    // After 3 seconds, hide it and then remove it from the page entirely
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 3000);
}

// ---- Add a shadow/"scrolled" style to the navbar once the user scrolls down ----
const navbar = document.querySelector('.navbar');  // Grab the navbar element
if (navbar) {
    // Listen for scrolling: turn "scrolled" class on/off depending on how far down the page is
    window.addEventListener('scroll', () => { navbar.classList.toggle('scrolled', window.scrollY > 50); });
}

// ---- Mobile hamburger menu open/close logic ----
const hamburger = document.querySelector('.hamburger');  // The hamburger (☰) button
const navMenu = document.querySelector('.nav-menu');     // The menu it shows/hides
if (hamburger && navMenu) {
    function setMenuOpen(open) {
        hamburger.classList.toggle('active', open);
        navMenu.classList.toggle('open', open);
        hamburger.setAttribute('aria-expanded', String(open));
    }
    // Clicking the hamburger toggles the menu open or closed
    hamburger.addEventListener('click', () => {
        setMenuOpen(!navMenu.classList.contains('open'));
    });
    // Clicking any link inside the menu closes the menu
    navMenu.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', () => {
            setMenuOpen(false);
        });
    });
    // Clicking anywhere outside the navbar also closes the menu
    document.addEventListener('click', (e) => {
        if (!navbar.contains(e.target)) {
            setMenuOpen(false);
        }
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && navMenu.classList.contains('open')) {
            setMenuOpen(false);
            hamburger.focus();
        }
    });
}

// ---- Highlight the navbar link for the page you are currently on ----
// (This runs immediately on load — it's a self-running function)
(function markActiveNav() {
    // Get the current page's file name from the URL (e.g. "products.php")
    const page = window.location.pathname.split('/').pop() || 'index.php';
    document.querySelectorAll('.nav-link').forEach(link => {
        const href = link.getAttribute('href');
        // If a link points to the current page, mark it as "active"
        if (href === page || (page === '' && href === 'index.php')) link.classList.add('active');
    });
})();

// ---- Show a time-based greeting (Morning/Afternoon/Evening/Night) ----
function setGreeting() {
    const greetEl = document.getElementById('greeting-text');  // Find the greeting text element
    if (!greetEl) return;                  // Stop if this page has no greeting area
    const hour = new Date().getHours();    // Get the current hour (0–23)
    let greeting;
    // Choose the greeting based on the time of day
    if (hour < 12) greeting = 'Good Morning';
    else if (hour < 17) greeting = 'Good Afternoon';
    else if (hour < 21) greeting = 'Good Evening';
    else greeting = 'Good Night';
    greetEl.innerHTML = `<span>${greeting}!</span> Welcome to MCKL Mart — fresh deals await you today.`;
}
setGreeting();  // Run it right away so the greeting appears on page load
// Fixed navigation and the wrapping greeting must not cover page headings.
if (navbar && window.ResizeObserver) {
    const greetingBar = document.querySelector('.greeting-bar');
    const syncHeaderSize = () => {
        const height = navbar.getBoundingClientRect().height;
        document.documentElement.style.setProperty('--nav-height', height + 'px');
        document.documentElement.style.setProperty('--header-height', (height + (greetingBar?.getBoundingClientRect().height || 0)) + 'px');
    };
    const headerObserver = new ResizeObserver(syncHeaderSize);
    headerObserver.observe(navbar);
    if (greetingBar) headerObserver.observe(greetingBar);
    syncHeaderSize();
}

// Keep focus inside an open dialog and restore it when the dialog closes.
// Observe the existing show/hide controls so checkout and account dialogs agree.
(function dialogFocus() {
    const dialogs = [...document.querySelectorAll('[role="dialog"]')];
    let active = null;
    let returnFocus = null;
    const background = new Map();
    function focusable(dialog) {
        return [...dialog.querySelectorAll('a[href], button, input, select, textarea, [tabindex="0"]')]
            .filter(el => !el.disabled && !el.closest('[inert]') && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
    }
    function sync() {
        const shown = dialogs.filter(el => el.classList.contains('show'));
        const next = shown.find(el => el.id === 'authPanel') || shown[shown.length - 1] || null;
        if (next === active) return;
        background.forEach((inert, el) => { el.inert = inert; });
        background.clear();
        if (!active && next) returnFocus = document.activeElement;
        active = next;
        dialogs.forEach(el => {
            el.inert = el !== active;
            el.setAttribute('aria-hidden', String(el !== active));
        });
        if (active) {
            for (const el of document.body.children) {
                if (el === active || el.id === 'authOverlay' || ['SCRIPT', 'STYLE'].includes(el.tagName)) continue;
                background.set(el, el.inert);
                el.inert = true;
            }
            document.body.style.overflow = 'hidden';
            (focusable(active)[0] || active).focus();
        } else {
            document.body.style.overflow = '';
            if (returnFocus?.isConnected && !returnFocus.closest('[inert]')) returnFocus.focus();
            returnFocus = null;
        }
    }
    document.addEventListener('keydown', e => {
        if (!active) return;
        if (e.key === 'Escape') {
            const close = active.querySelector('.auth-close, .choice-close');
            if (close) { e.preventDefault(); close.click(); }
        }
        if (e.key !== 'Tab') return;
        const nodes = focusable(active);
        if (!nodes.length) { e.preventDefault(); return; }
        const index = nodes.indexOf(document.activeElement);
        if (e.shiftKey && index <= 0) { e.preventDefault(); nodes[nodes.length - 1].focus(); }
        else if (!e.shiftKey && (index < 0 || index === nodes.length - 1)) { e.preventDefault(); nodes[0].focus(); }
    });
    const observer = new MutationObserver(sync);
    dialogs.forEach(el => observer.observe(el, { attributes: true, attributeFilter: ['class'] }));
    // Inline checkout scripts run after this file and before DOMContentLoaded.
    document.addEventListener('DOMContentLoaded', sync);
})();

/* ----------------------------------------------------------------- *
 *  Account slide-in panel (Login / Sign Up)
 * ----------------------------------------------------------------- */
// Self-running function that sets up the whole login/signup slide-in panel
(function authPanel() {
    // Grab the panel pieces from the page
    const overlay   = document.getElementById('authOverlay');  // Dark background behind the panel
    const panel     = document.getElementById('authPanel');    // The slide-in panel itself
    const openBtn   = document.getElementById('accountBtn');    // Account button that opens it
    const closeBtn  = document.getElementById('authClose');     // X button that closes it
    if (!overlay || !panel) return;   // Stop if this page doesn't have the panel

    // Switch between the Login and Sign Up tabs.
    function switchTab(name) {
        // Highlight the clicked tab and un-highlight the others
        document.querySelectorAll('.auth-tab').forEach(tab => {
            const on = tab.dataset.tab === name;
            tab.classList.toggle('active', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
            tab.tabIndex = on ? 0 : -1;
        });
        // Show only the matching tab's content (the right form)
        document.querySelectorAll('.auth-tab-content').forEach(panelEl => {
            panelEl.classList.toggle('active', panelEl.id === 'tab-' + name);
        });
    }

    // ---- Open the panel (made global so other pages/modals can call it) ----
    // Exposed globally so the checkout / guest modals can open a specific tab.
    // Optional `email` pre-fills the Sign Up email field (used after guest checkout).
    window.openAuthPanel = function (tab, email) {
        if (tab) switchTab(tab);   // Open on the requested tab (login or signup) if given
        if (email) {
            // Pre-fill the email box in the chosen form
            const emailField = document.getElementById(tab === 'signup' ? 'as-email' : 'al-email');
            if (emailField) emailField.value = email;
        }
        overlay.classList.add('show');                  // Show the dark background
        panel.classList.add('show');                    // Slide the panel in
        panel.setAttribute('aria-hidden', 'false');     // Mark it visible for screen readers
        document.body.style.overflow = 'hidden';        // Stop the page behind from scrolling
    };

    // ---- Close the panel (also global) ----
    window.closeAuthPanel = function () {
        overlay.classList.remove('show');
        panel.classList.remove('show');
        panel.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';   // Allow the page to scroll again
    };

    if (openBtn) openBtn.addEventListener('click', () => window.openAuthPanel());  // Account button opens panel
    if (closeBtn) closeBtn.addEventListener('click', window.closeAuthPanel);       // X button closes panel
    overlay.addEventListener('click', window.closeAuthPanel);                      // Clicking the background closes it

    // Clicking a tab switches to that tab
    document.querySelectorAll('.auth-tab').forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.tab));
        tab.addEventListener('keydown', e => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
            e.preventDefault();
            const name = e.key === 'Home' ? 'login' : e.key === 'End' ? 'signup' : tab.dataset.tab === 'login' ? 'signup' : 'login';
            switchTab(name);
            panel.querySelector('[data-tab="' + name + '"]').focus();
        });
    });

    // "Create an account" / "Login here" links inside the forms.
    document.querySelectorAll('[data-goto]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();              // Stop the link from jumping the page
            switchTab(link.dataset.goto);    // Switch to the tab named in its data-goto attribute
        });
    });

    // Close on Escape key (only if the panel is currently open).
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && panel.classList.contains('show')) window.closeAuthPanel();
    });

    // Auto-open the panel when redirected with ?auth=login or ?auth=signup
    // (e.g. a guest who tried to open My Orders / order_history.php).
    const authParam = new URLSearchParams(window.location.search).get('auth');  // Read ?auth= from the URL
    if (authParam === 'login' || authParam === 'signup') {
        window.openAuthPanel(authParam);   // Open the correct tab automatically
    }
})();

/* ----------------------------------------------------------------- *
 *  Live form validation (front-end UX)
 *
 *  Modern behaviour: fields validate on blur, then live while typing once
 *  "touched" (never before the user interacts). Valid fields turn green
 *  with "✓ Looks good!", invalid fields turn red with an error message.
 *  Passwords get a clean live checklist instead of messy hint text.
 *
 *  This is UX only — the PHP backend (includes/functions.php + each page)
 *  ALWAYS re-validates everything for real security.
 *
 *  Fields opt in with a data-validate="..." attribute, so the SAME engine
 *  powers signup, login, contact, checkout and guest checkout.
 * ----------------------------------------------------------------- */
(function formValidation() {

    /* ---- Reusable validators (return true/false) ---- */

    function validateName(v) {
        v = v.trim();
        // 2–100 chars; only letters, spaces, apostrophes and hyphens.
        return [...v].length >= 2 && [...v].length <= 100 && /^\p{L}[\p{L}\p{M} '.-]*$/u.test(v);
    }

    function validateEmail(v) {
        // Rejects jacobgmail.com, abc@, abc@gmail, abc@@gmail.com, etc.
        return new TextEncoder().encode(v.trim()).length <= 150 && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim());
    }

    function validateMalaysiaPhone(v, optional = false) {
        v = v.trim();
        if (v === '') return !!optional;                 // optional + empty = OK
        let p = v.replace(/[\s\-().]/g, '');             // drop spaces, hyphens, brackets, dots
        if (p.startsWith('+60')) p = '0' + p.slice(3);   // +60... -> 0...
        else if (p.startsWith('60')) p = '0' + p.slice(2); // 60... -> 0...
        return /^01\d{8,9}$/.test(p);                    // local mobile: 01 + 8–9 digits
    }

    // Returns the 5 individual password checks (used by the live checklist).
    function passwordChecks(v) {
        return {
            len:     [...v].length >= 8 && new TextEncoder().encode(v).length <= 72,
            upper:   /[A-Z]/.test(v),
            lower:   /[a-z]/.test(v),
            number:  /[0-9]/.test(v),
            special: /[^A-Za-z0-9]/.test(v),
        };
    }
    function validatePassword(v) {
        const c = passwordChecks(v);
        return c.len && c.upper && c.lower && c.number && c.special;
    }
    function validateConfirmPassword(v, passwordValue) {
        return v !== '' && v === passwordValue;
    }
    function validateAddress(v) {
        v = v.trim();
        return v.length >= 5 && v.length <= 255 && /^[\p{L}\p{N}\s,.\/\-#()]+$/u.test(v);
    }
    function validateMessage(v) {
        v = v.trim();
        return v.length >= 10 && v.length <= 1000;
    }
    function validateQuantity(v) {
        v = String(v).trim();
        if (!/^\d+$/.test(v)) return false;   // digits only → rejects 0.5, -1, "abc", symbols
        const n = parseInt(v, 10);
        return n >= 1 && n <= 99;             // whole number from 1 to 99
    }

    /* ---- Rules for the simple field types (everything except password/confirm) ---- */
    const RULES = {
        name:     { test: validateName,           bad: 'Please enter a valid name using letters only.' },
        email:    { test: validateEmail,          bad: 'Please enter a valid email address.' },
        phone:    { test: validateMalaysiaPhone,  bad: 'Please enter a valid Malaysian phone number.' },
        address:  { test: validateAddress,        bad: 'Please enter a complete address (5–255 characters).' },
        message:  { test: validateMessage,        bad: 'Message must be between 10 and 1000 characters.' },
        required: { test: v => v.trim() !== '',   bad: 'This field is required.' },
        select:   { test: v => v !== '',          bad: 'Please select an option.' },
    };

    /* ---- DOM helpers: show/clear the message + border state under a field ---- */

    function fieldContainer(input) {
        return input.closest('.form-group, .field-group') || input.parentElement;
    }

    // Get (or create) the single message element for a field, removing any
    // server-rendered PHP message so we never show two messages at once.
    function messageEl(input) {
        const c = fieldContainer(input);
        const php = c.querySelector('.form-feedback');
        if (php) php.remove();
        let m = c.querySelector('.field-message');
        if (!m) {
            m = document.createElement('div');
            m.className = 'field-message';
            c.appendChild(m);                            // sits at the bottom of the field group
        }
        return m;
    }

    function showError(input, msg) {
        input.setAttribute('aria-invalid', 'true');
        input.classList.remove('input-success', 'valid');
        input.classList.add('input-error');
        const m = messageEl(input);
        m.className = 'field-message error';
        m.textContent = '⚠ ' + msg;
    }

    function showSuccess(input, msg) {
        input.setAttribute('aria-invalid', 'false');
        input.classList.remove('input-error', 'invalid');
        input.classList.add('input-success');
        const m = messageEl(input);
        m.className = 'field-message success';
        m.textContent = '✓ ' + (msg || 'Looks good!');
    }

    function clearValidation(input) {
        input.removeAttribute('aria-invalid');
        input.classList.remove('input-error', 'input-success', 'valid', 'invalid');
        const c = fieldContainer(input);
        const m = c.querySelector('.field-message');
        if (m) m.remove();
        const php = c.querySelector('.form-feedback');
        if (php) php.remove();
    }

    /* ---- Password field: live checklist + strength message ---- */

    function updatePassword(input, touched) {
        const v = input.value;
        const checks = passwordChecks(v);
        const box = fieldContainer(input).querySelector('.password-rules');
        if (box) {
            box.hidden = false;                          // reveal the checklist once we touch the field
            box.querySelectorAll('.password-rule').forEach(li => {
                const ok = !!checks[li.dataset.rule];
                li.classList.toggle('valid', ok);
                li.classList.toggle('invalid', !ok);
            });
        }
        if (!touched) return;
        if (v === '') {
            showError(input, 'Password is required.');
        } else if (validatePassword(v)) {
            showSuccess(input, 'Strong password');
        } else {
            // Weak: red border, but let the checklist explain what's missing (no extra text).
            input.classList.remove('input-success', 'valid');
            input.classList.add('input-error');
            const m = fieldContainer(input).querySelector('.field-message');
            if (m) m.remove();
        }
    }

    function updateConfirm(input, pwInput, touched) {
        if (!touched) return;
        if (input.value === '') {
            showError(input, 'Please confirm your password.');
        } else if (validateConfirmPassword(input.value, pwInput ? pwInput.value : '')) {
            showSuccess(input, 'Passwords match.');
        } else {
            showError(input, 'Passwords do not match.');
        }
    }

    /* ---- Validate a single field according to its data-validate type ---- */

    function validateField(input, touched) {
        const type = input.dataset.validate;
        if (type === 'password') { updatePassword(input, touched); return; }
        if (type === 'confirm') {
            const pw = input.form && input.form.querySelector('[data-validate="password"]');
            updateConfirm(input, pw, touched);
            return;
        }
        if (!touched) return;

        const optional = input.hasAttribute('data-optional');
        const v = input.value;
        if (optional && v.trim() === '') { clearValidation(input); return; } // optional + empty = neutral

        const rule = RULES[type];
        if (!rule) return;
        if (rule.test(v, optional)) showSuccess(input, 'Looks good!');
        else showError(input, rule.bad);
    }

    // A field is "live" only if it's currently visible (skips hidden pickup/delivery fieldsets).
    function isVisible(input) {
        return input.offsetParent !== null;
    }

    /* ---- Validate the whole form (on submit): block + focus first invalid ---- */
    function validateForm(form) {
        const fields = [...form.querySelectorAll('[data-validate]')].filter(isVisible);
        let ok = true, firstBad = null;
        fields.forEach(input => {
            validateField(input, true);
            if (input.classList.contains('input-error')) {
                ok = false;
                if (!firstBad) firstBad = input;
            }
        });
        if (firstBad) firstBad.focus();
        return ok;
    }

    /* ---- Wire up one form: blur validates, input validates live once touched ---- */
    function setupForm(form) {
        const fields = [...form.querySelectorAll('[data-validate]')];
        fields.forEach(input => {
            let touched = false;

            input.addEventListener('blur', () => {
                if (!isVisible(input)) return;
                touched = true;
                validateField(input, true);
            });

            input.addEventListener('input', () => {
                if (!isVisible(input)) return;
                touched = true;                          // user has started typing → validate live from now on
                validateField(input, true);

                // When the password changes, re-check the confirm field if it has content.
                if (input.dataset.validate === 'password') {
                    const cf = form.querySelector('[data-validate="confirm"]');
                    if (cf && cf.value !== '') updateConfirm(cf, input, true);
                }
            });

            // Reveal the password checklist as soon as the field is focused.
            if (input.dataset.validate === 'password') {
                input.addEventListener('focus', () => updatePassword(input, touched));
            }
        });

        form.addEventListener('submit', e => {
            if (!validateForm(form)) e.preventDefault();   // recheck everything, stop if invalid
        });
    }

    // Activate validation on every form that has at least one data-validate field.
    document.querySelectorAll('form').forEach(form => {
        if (form.querySelector('[data-validate]')) setupForm(form);
    });

    /* ---- Cart quantity fields (cart.php) ----
       Wired up here by selecting the existing name="quantity" inputs, so the
       cart markup and logic stay completely untouched. */
    function checkQuantity(input) {
        const ok = validateQuantity(input.value);
        if (ok) showSuccess(input, 'Looks good!');
        else showError(input, 'Please enter a whole number from 1 to 99.');
        return ok;
    }
    document.querySelectorAll('form[action="cart_action.php"] input[name="quantity"]').forEach(input => {
        // blur/input only fire on real interaction, so no "before you type" errors appear.
        input.addEventListener('blur', () => checkQuantity(input));
        input.addEventListener('input', () => checkQuantity(input));
        if (input.form) {
            input.form.addEventListener('submit', e => {
                if (!checkQuantity(input)) { e.preventDefault(); input.focus(); }
            });
        }
    });

    // Expose the validators + helpers so other inline scripts (e.g. checkout.php) can reuse them.
    window.MCKLValidate = {
        validateName, validateEmail, validateMalaysiaPhone,
        validatePassword, validateConfirmPassword, validateAddress, validateMessage, validateQuantity,
        showError, showSuccess, clearValidation,
        validateForm,                                    // validate a whole <form> on demand
    };
})();
