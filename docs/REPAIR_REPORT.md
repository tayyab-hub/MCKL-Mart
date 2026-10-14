# Repair and verification report

**Muhammad Tayyab — Individual Web Computing Project**
14 October 2026 · Asia/Kuala_Lumpur

## Current status — publication preparation

On 14 October 2026, the normal Apache homepage and nine additional public page/search routes returned HTTP 200. The dedicated mckl_mart_app account was verified through the application connection; its database has four categories and 17 products. The private configuration was preserved without printing its contents. The former missing-password blocker is resolved.

The following repair notes retain the original investigation and test history. See [PUBLICATION_REVIEW.md](PUBLICATION_REVIEW.md) for current publication checks, artwork changes and approval status.

## Status at the end of the original repair

The existing code has been repaired, documented and tested on disposable databases. **The ordinary Apache homepage is still HTTP 503 because includes/config.local.php still contains the placeholder password.** No successful connection using the dedicated application account is claimed. That one private configuration step is required before the local installation can be signed off.

No GitHub repository, commit or push was created. No .git repository existed in this project at inspection. SCAMGUARD and ResumeIQ were not accessed or modified. The existing portfolio and original coursework databases were not reset, overwritten or used for write-based tests.

## Original configuration failure

includes/db.php reads environment variables and the exact file includes/config.local.php. The local file did not exist and no MCKL_DB_* environment variables were supplied. The code therefore had no username/password and deliberately returned 503 with the configuration message. The folder also contained config.example.php.php, which the application never reads.

Actions taken:

- Created the ignored local file from config.example.php; preserved environment overrides and password privacy.
- Kept localhost:3306, mckl_mart_portfolio and the existing mckl_mart_app account.
- Removed only the redundant misnamed example file.
- Added explicit placeholder detection so a placeholder is reported as unconfigured.
- Opened the private file for local editing without displaying its contents after asking for the password step.
- Verified MariaDB 10.4.32, PHP 8.2.12, pdo_mysql and mbstring availability; Apache and MariaDB are running.
- Verified mckl_mart_app has SELECT, INSERT, UPDATE and DELETE on the portfolio schema and only USAGE globally. Passwordless access to that account is rejected.
- Read the existing catalogue using the available local administrative connection: four categories and 17 products. This is not a successful application-account connection.

The last check still found the literal placeholder in config.local.php; its saved contents had not changed. No password was guessed, reset, printed or substituted with root credentials.

## Findings and fixes

Severity describes practical impact in this local academic application; it is not a penetration-test score.

| Severity | Finding | Implemented correction |
| --- | --- | --- |
| High — availability | Missing local configuration prevents every DB page from loading. | Correct private file created; duplicate removed; placeholder diagnosed. Actual password remains a user action. |
| Medium | Array-shaped HTTP fields can reach trim(), string functions, array keys or redirects and crash pages. | Shared string validation rejects arrays, NUL and malformed UTF-8; controllers use it. Output escaping substitutes malformed UTF-8 safely. |
| Medium | Accepted emails and combined first/last names can exceed database column sizes. | Enforced 150-byte email and 100-character combined-name limits with form feedback. |
| Medium | bcrypt silently truncates passwords longer than 72 bytes. | Registration and browser validation enforce a 72-byte maximum and explain it. |
| Medium | Two registrations can pass the duplicate-email pre-check and race into an unhandled unique-key error. | Catch duplicate-key errors; transactionally create the account and link any eligible guest order; establish authentication only after commit. Concurrent regression test added. |
| Medium | Generic development errors could disclose technical detail; contact/database failures had no controlled response. | Disabled browser error display, added generic exception responses and non-sensitive logs, plus contact/registration feedback and rollback. |
| Medium | The default PHP session name/path could collide with neighboring XAMPP applications. | Dedicated MCKLMARTSESSID cookie scoped to the application's URL directory; existing strict mode, HttpOnly, SameSite, HTTPS Secure flag and ID rotation retained. Explicit no-store responses. |
| Medium | Only exact config filenames were blocked; internal directories, SQL files, listings and backup variants lacked comprehensive HTTP protection. | Root and directory .htaccess rules deny internal/dot directories, backups, SQL and listings. Explicit public-SQL ignore exceptions replace a broad exception. |
| Medium | Adding to a 99-item cart could return before considering newly reduced stock. | Recheck the current stock cap; regression verifies the 99-item scenario after stock reduction. |
| Low | Invalid quantities silently removed cart entries; deleted products could leave invisible quantities. | Invalid values leave the cart unchanged, Remove is explicit, and missing products are pruned with feedback. |
| Medium — defense in depth | Session fallback redirects were not consistently allowlisted; guest confirmation permission was broader than guest-only orders. | One exact local-page allowlist; guest permission additionally requires user_id IS NULL. |
| Low | Unknown product/confirmation IDs returned success statuses. | Return 404 while preserving safe public feedback. |
| Medium — data integrity | New database installs lacked nonnegative/quantity/arithmetic CHECK constraints. | Added checks to schema.sql and rebuilt setup.sql. Existing portfolio schema was intentionally not altered. |
| Low | PHP and database display timezones could differ from Kuala Lumpur; browser date minimum used UTC. | Explicit Asia/Kuala_Lumpur / +08:00 and a server-rendered pickup date minimum. Future-slot validation retained. |
| Medium — usability | Hidden radio controls were unreachable by keyboard; dialogs did not manage focus/background; hidden navigation/slides could remain focusable. | Visually hidden native radios with visible focus, modal focus trapping/restoration, inert backgrounds, tab navigation and proper menu state. |
| Low | Product details forced two columns on phones; mobile search compressed to an icon; fixed navigation covered headings. | Responsive detail/cart layouts, full-width phone search and measured header offsets. Verified at four widths. |
| Low | Slideshow had no persistent pause; image click targets were divs; feedback lacked live roles. | Pause/reduced-motion support, keyboard links and status/alert toasts. |
| Medium — accuracy | Homepage invented customer/location metrics; contact displayed pretend branch addresses and promised a response. | Replaced claims with project facts, removed fictitious locations, and clearly described local-only message storage and simulated payments. |
| Low — performance | Order history queried details once per order. | Fetch authorized lines in a single joined query, grouped by order. No measured performance gain is claimed. |

Existing protections were retained: prepared statements, CSRF on state-changing forms/AJAX, POST-only cart/logout, hashed passwords, owner-restricted orders, escaped output, transaction rollback, stable row-lock order and conditional stock deduction.

## Executed tests — passed

Environment: Windows XAMPP, PHP 8.2.12, MariaDB 10.4.32, Node 24.18.0 and the Codex in-app Chromium browser. No additional application packages were installed.

| Test | Result |
| --- | --- |
| PHP syntax | 23 PHP files parsed successfully, including private local config syntax without exposing its contents. |
| JavaScript syntax | script.js and tests/javascript_test.cjs passed node --check. Four external/rendered script blocks parsed successfully through the JS helper. |
| Helper regressions | 21 checks passed. |
| Static privacy/configuration/assets | 33 checks passed. |
| Database and HTTP integration | **116 checks passed** on the final run. |
| Fresh database imports | Both setup.sql and schema.sql + seed.sql produced matching catalogue data, six InnoDB tables, four categories, 17 products and zero private records. |
| Constraints | Foreign keys, unique slug, nonnegative price/stock checks and public-schema relationships verified. |
| Authentication | Registration, session rotation, hashing, wrong/correct passwords, logout, duplicate emails and simultaneous signup race verified. |
| Cart | Add/update/remove/clear, stock caps, 99-item limit, invalid quantities and deleted-product cleanup verified. |
| Checkout | Guest delivery, account pickup, current-price authority, invalid inputs, duplicate submission, inventory changes, confirmation and history ownership verified. |
| Failure rollback | A test-only trigger failed an order-detail insert after stock update. Order, lines and stock all rolled back; no injected DB message reached the response. |
| Final-item concurrency | Two separate PHP processes and sessions overlapped at SELECT ... FOR UPDATE on the final item. Exactly one checkout succeeded, one got stock feedback, stock ended at zero and one order line existed. |
| Duplicate-email concurrency | Two insert attempts overlapped using a disposable fixture trigger. One account was created, one request received validation feedback; no unhandled error. |
| Contact | Valid local save, malformed input, length rejection, genuine one-time success state and forged-success URL behavior verified. |
| Security regressions | CSRF denial across six endpoints, GET mutation denial, output escaping, invalid IDs/SQL-like IDs, safe redirect handling, owner separation and cookie attributes verified. |
| Linked resources | All **47** local linked pages/assets discovered in representative rendered pages returned HTTP 200. |
| Apache static assets | All 17 product images returned HTTP 200; all 17 WebP headers decoded successfully. |
| Apache access restrictions | Nine internal/config/backup/dotfile/listing paths returned HTTP 403, including the private test-artifact directory. |
| Browser workflow | Mobile AJAX add → cart → guest delivery checkout → confirmation → signup → linked history → logout completed with fictional test data. |
| Browser keyboard | Account focus cycling/Escape/restoration, mobile menu state, and arrow-key fulfillment/payment selection verified. |
| Responsive layout | 32 measurements: eight pages at 1440, 768, 390 and 320 pixels. No document overflow; headings cleared fixed navigation. Three screenshots saved. |
| Browser console | No errors or warnings returned in the captured browser log check. |
| Cleanup | Newly created test databases removed; final test-schema count was zero. Stop-file preview shutdown was executed successfully. |

The concurrency harness scopes process observations to its new random database. This MariaDB build reported locking prepared statements in PROCESSLIST while INNODB_LOCK_WAITS remained empty; the test therefore observes both real locking queries before releasing its blocking transaction and independently checks the resulting rows/stock. It does not replace the application locks with mocks.

## Failed / blocked at the end of the original repair

- **Ordinary Apache dynamic site: HTTP 503 remains.** The private config still has its placeholder; the dedicated account cannot connect until the actual password is saved. Successful disposable-server tests do not remove this installation blocker.
- An exploratory Node parallel link checker hit an internal Undici assertion. It was replaced by a PHP/cURL link check in the integration suite, which passed all 47 targets. No unresolved application link failure was found.
- Earlier test-harness attempts exposed Windows empty-child-environment handling and differences in MariaDB lock/trigger observations. These were corrected in test-only setup/observability; the final automated suites passed.

## Original unverified items and limitations

- Final dynamic integration through Apache using the real mckl_mart_app credentials.
- HTTPS deployment/Secure cookies, external hosting, Safari/Firefox/real devices, assistive-technology certification, load/performance benchmarks, external penetration testing and GitHub-hosted CI.
- MySQL (as distinct from the installed MariaDB) and failure recovery after a database/process crash at commit time.
- Payment processing, actual delivery and support email are intentionally not implemented.
- Persistent anti-abuse/rate limits, email verification and password recovery remain future work.
- New CHECK constraints are available for fresh imports, but were not applied to existing tables. Do not re-import to add them; use a separately reviewed migration if wanted.
- The local administrative root connection accepts an empty password. This pre-existing XAMPP setting was not changed; it must be addressed before sharing or deploying the server. The normal application remains configured for its restricted account.
- Existing artwork/branding reuse rights require the owner's review before public release. No open-source license was invented for third-party assets.

## Changed and new files

This is an inventory of this repair, not a Git diff: the directory had no Git metadata.

Modified:

- .gitignore
- .github/workflows/ci.yml
- cart.php, cart_action.php, checkout.php, contact.php
- index.php, login.php, register.php
- products.php, product_details.php, order_history.php
- includes/.htaccess, includes/bootstrap.php, includes/db.php
- includes/functions.php, includes/header.php, includes/footer.php
- script.js, style.css
- database/schema.sql, database/setup.sql, database/README.md
- tests/helpers_test.php
- README.md, PORTFOLIO_CHANGES.md

Created:

- .htaccess
- includes/config.local.php — **private, ignored, not for publication**
- database/.htaccess, tests/.htaccess
- tests/integration_test.php, tests/router.php, tests/static_test.php, tests/javascript_test.cjs
- docs/REPAIR_REPORT.md, docs/SCREENSHOTS.md, docs/layout-checks.json
- docs/screenshots/catalogue-desktop.jpg
- docs/screenshots/catalogue-mobile.jpg
- docs/screenshots/product-tablet.jpg

Removed: includes/config.example.php.php (duplicate placeholder example).

Retained: the original public seed catalogue, product artwork, logo, logout behavior and authentication guard. Temporary fixture logs/sessions were confined to ignored, HTTP-blocked .local and cleaned after verification.

## Original launch actions (credential step now resolved)

1. Open C:\xampp\htdocs\MCKL-Mart\includes\config.local.php in a local editor.
2. Replace only REPLACE_WITH_YOUR_LOCAL_PASSWORD with the existing mckl_mart_app password, preserve valid PHP quoting, and save. Do not send the password in chat. If forgotten, use the phpMyAdmin password-change steps in README.md; no database reset is needed.
3. Start Apache and MySQL in XAMPP; open http://localhost/MCKL-Mart/. Confirm that the homepage and catalogue load. The existing portfolio database is already imported: four categories, 17 products and zero private rows were verified.
4. Re-run the documented tests or use the disposable preview for write-based manual testing. Do not use real personal details.
5. Review assets and proposed files, then explicitly approve any GitHub repository creation or push. No publication has been performed.

**Historical readiness assessment:** prepared for local portfolio review, with reproducible tests, source corrections and documentation. Publication sign-off remains pending the private credential fix, normal Apache verification and asset-rights review. This is an academic demo, not a production storefront.
