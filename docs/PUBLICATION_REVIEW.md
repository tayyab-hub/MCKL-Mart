# Publication review

**Muhammad Tayyab — Individual Web Computing Project**
Reviewed 14 October 2026 · Asia/Kuala_Lumpur

## Current status

The local application is working. Ten normal Apache public page/search routes returned HTTP 200; the application connection uses the dedicated `mckl_mart_app` account and reads four categories and 17 products. The original missing-password issue is resolved. Its history remains in [REPAIR_REPORT.md](REPAIR_REPORT.md).

The author explicitly approved publishing reviewed commit `9a00bd8` on 14 October 2026, including the necessary provenance update and CI corrections. Publication proceeds from that commit without rewriting history. The target is `tayyab-hub/MCKL-Mart`; no public repository has been created or pushed. The connected GitHub account identifies as `tayyab-hub`; the repository metadata lookup returned 404, so existence/access must be rechecked immediately before creation. Never overwrite or force-push if a repository appears in the meantime.

The official GitHub CLI login was started but did not finish authorization; a final check still reported no authenticated CLI host. The pending login was stopped. A fresh official sign-in is needed before repository creation. No password or access token was requested in chat.

## Privacy gate

- Private configuration remains local and unchanged, verified by a before/after SHA-256 comparison without printing its contents or hash.
- `git check-ignore -v` confirms exclusion of the real config, `.local/`, ZIP archives, private SQL exports and `.env`.
- Only `database/schema.sql`, `seed.sql` and `setup.sql` are public SQL candidates. They contain the schema and sample catalogue; no user, order, order-detail or contact rows, and no password hashes.
- Test credentials in source are deliberately fictional fixtures; they do not authenticate against the portfolio database.
- Screenshots use only disposable fictional records and were visually reviewed. No password fields, private config, real customer data or browser extensions are shown. JPEGs contain no EXIF data.
- No Git history existed when work began. A new local repository was initialized; the candidate is reviewed before the first commit.
- The 74-file staged candidate passed Gitleaks 8.30.1 with redaction enabled: no leaks found. Its exact Git index was exported locally for the scan; the private config was absent from the index and export. All 29 relative Markdown/image links resolved, and all 17 product-image hashes matched the retained originals.

## Executed checks

| Status | Check | Result |
| --- | --- | --- |
| Passed | PHP syntax | 23 application/test/config PHP files; no failures. Private config syntax checked without displaying contents. |
| Passed | Helper tests | 21 checks. |
| Passed | Static checks | 33 checks, including both SQL-file composition and case-sensitive image paths. Repeated after logo/documentation work. |
| Passed | Integration suite | 116 checks on newly created disposable schemas. Includes concurrent registration, final-item checkout and rollback fault injection. |
| Passed | JavaScript | `script.js`, test runner and logo-renderer syntax; four external/rendered application script blocks parsed. |
| Passed | Setup reproducibility | Combined and split SQL imports match: six InnoDB tables, four categories, 17 products and no private rows. |
| Passed | Normal Apache | Ten public page/search routes HTTP 200; five private/internal paths HTTP 403. |
| Passed | Browser workflow | Add products, cart, guest delivery/card selection, demo order, signup/order linking, account history and logout. No captured console errors/warnings. |
| Passed | Presentation | Eight genuine screenshots after logo replacement; desktop/mobile/tablet views. Fresh mobile catalogue did not overflow. |
| Passed | Actions validation | actionlint 1.7.12 accepted the updated workflow; no Windows-only CI paths or private credentials. |
| Passed | Cleanup | Both schemas created by this run were removed; an exact-name read-only check returned zero remaining. |
| Not verified | GitHub Actions execution | No hosted run before approval/publication. No passing CI badge is displayed. |
| Not verified | GitHub README rendering | Remote image/Mermaid rendering must be verified after the approved push. |
| Not verified | Broader deployment | HTTPS hosting, other database engines, real devices, accessibility certification, load/security assessment. |

No unresolved application test failure was found in this publication-preparation run. Tool setup required explicit access to official download hosts; checksum-verified Gitleaks, actionlint and GitHub CLI archives remain ignored under `.local/`. The logo renderer emitted font-cache permission notices but produced a valid, visually inspected PNG.

## Artwork decision

All 17 product images are retained unchanged based on the author's statement that he generated them with AI for coursework/portfolio use. They show fictional project packaging; no obvious unrelated store logos were found. The author now confirms the source as ChatGPT Image Generation for his individual Web Computing assignment, without downloaded or copied third-party images. Exact model/version and prompts are unrecorded; legal clearance is not claimed.

The new standalone logo is an original SVG code-and-cart design with an **Academic Demo** wordmark, without the official college flame emblem. Blue/gold page styling is preserved. [ASSETS.md](ASSETS.md) records provenance and the comparison with the official college website. No blanket license was added.

## Files changed or added for publication preparation

Modified: `README.md`, `PORTFOLIO_CHANGES.md`, `docs/REPAIR_REPORT.md`, `docs/SCREENSHOTS.md`, `.gitignore`, `.github/workflows/ci.yml`, `Logo of the website.png`, and the three existing screenshots.

Added: `.gitattributes`, `assets/project-logo.svg`, `tools/render-logo.cjs`, `docs/ASSETS.md`, `docs/ASSET_MANIFEST.json`, `docs/TESTING.md`, `docs/PUBLICATION_REVIEW.md`, `docs/SCREENSHOT_MANIFEST.json`, and five screenshots: homepage, product desktop, cart, checkout and order history.

Application PHP behavior, CSS, JavaScript, SQL seed records, all 17 product image files and existing database contents were preserved during this publication-preparation stage. No other projects or repositories were modified.

## Approval and remaining publication steps

1. The author reviewed and approved the project, logo and retained AI-generated images.
2. Public publication to `https://github.com/tayyab-hub/MCKL-Mart` is explicitly authorized.
3. Complete the official GitHub sign-in flow privately when needed; never paste credentials into chat.
4. Recheck target existence, then create/update only this repository, push without force, set an accurate description and relevant topics, and verify the README, images, Mermaid diagrams and Actions run.
5. Investigate any real CI failure before adding a workflow status badge. No fake deployment, release or GitHub Pages application link will be created.

Proposed description: “Academic PHP and MariaDB e-commerce demo with authentication, a session cart, transactional checkout, stock validation and responsive design.”

Proposed topics: `php`, `mysql`, `mariadb`, `javascript`, `ecommerce`, `web-development`, `portfolio-project`.
