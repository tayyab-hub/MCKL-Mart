# Publication review

**Muhammad Tayyab — Individual Web Computing Project**
Reviewed 14 October 2026 · Asia/Kuala_Lumpur

This records the original GitHub publication. The subsequent verified InfinityFree hardening deployment of 15 October 2026 and its separate GitHub review are documented in [LIVE_DEPLOYMENT_REVIEW.md](LIVE_DEPLOYMENT_REVIEW.md). Historical test counts and publication statements below describe the original release.

## Current status

The local application is working. Ten normal Apache public page/search routes returned HTTP 200; the application connection uses the dedicated `mckl_mart_app` account and reads four categories and 17 products. The original missing-password issue is resolved. Its history remains in [REPAIR_REPORT.md](REPAIR_REPORT.md).

The author explicitly approved publishing reviewed commit `9a00bd8` on 14 October 2026, including the necessary provenance update and CI corrections. The public repository is now [tayyab-hub/MCKL-Mart](https://github.com/tayyab-hub/MCKL-Mart), with `main` as its default branch. Its absence was rechecked immediately before creation. The first push was commit `9604985`, which adds only the confirmed ChatGPT image provenance and approval record to the reviewed source. History was preserved; no force-push or changes to other repositories occurred.

Official browser/device authorization completed as `tayyab-hub`; GitHub CLI stores its credential in the operating-system keyring. No password or access token was requested in chat. The repository was also opened in a signed-out browser, confirming public access.

## Privacy gate

- Private configuration remains local and unchanged, verified by a before/after SHA-256 comparison without printing its contents or hash.
- `git check-ignore -v` confirms exclusion of the real config, `.local/`, ZIP archives, private SQL exports and `.env`.
- Only `database/schema.sql`, `seed.sql` and `setup.sql` are public SQL candidates. They contain the schema and sample catalogue; no user, order, order-detail or contact rows, and no password hashes.
- Test credentials in source are deliberately fictional fixtures; they do not authenticate against the portfolio database.
- Screenshots use only disposable fictional records and were visually reviewed. No password fields, private config, real customer data or browser extensions are shown. JPEGs contain no EXIF data.
- No Git history existed when work began. A new local repository was initialized, and the candidate was reviewed before the first commit. The two-commit history passed a redacted Gitleaks scan before the first push.
- The 74-file staged candidate passed Gitleaks 8.30.1 with redaction enabled: no leaks found. Its exact Git index was exported locally for the scan; the private config was absent from the index and export. All 29 relative Markdown/image links resolved, and all 17 product-image hashes matched the retained originals.
- After publication, the GitHub tree matched all 74 reviewed files with no extra files. Private configuration, database credentials and real customer records were excluded. The existing local password was also checked for absence from the publication candidate without displaying it.

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
| Passed | GitHub Actions execution | [Run 38567081681](https://github.com/tayyab-hub/MCKL-Mart/actions/runs/38567081681) succeeded for `9604985`: both jobs passed, including 21 helper, 33 static and 116 integration checks. No CI correction was required. The README badge tracks `main`. |
| Passed | GitHub README rendering | All eight screenshot images loaded with their expected dimensions, including the expanded checkout/mobile/tablet gallery. Both architecture and six-table ER Mermaid diagrams rendered in GitHub's viewer. |
| Passed | Published documentation links | All seven README documentation/workflow destinations returned HTTP 200 without authentication; all 19 README section/permalink anchors resolved. |
| Not verified | Broader deployment | HTTPS hosting, other database engines, real devices, accessibility certification, load/security assessment. |

No unresolved application test failure was found in this publication-preparation run. Tool setup required explicit access to official download hosts; checksum-verified Gitleaks, actionlint and GitHub CLI archives remain ignored under `.local/`. The logo renderer emitted font-cache permission notices but produced a valid, visually inspected PNG.

## Artwork decision

All 17 product images are retained unchanged based on the author's statement that he generated them with AI for coursework/portfolio use. They show fictional project packaging; no obvious unrelated store logos were found. The author now confirms the source as ChatGPT Image Generation for his individual Web Computing assignment, without downloaded or copied third-party images. Exact model/version and prompts are unrecorded; legal clearance is not claimed.

The new standalone logo is an original SVG code-and-cart design with an **Academic Demo** wordmark, without the official college flame emblem. Blue/gold page styling is preserved. [ASSETS.md](ASSETS.md) records provenance and the comparison with the official college website. No blanket license was added.

## Files changed or added for publication preparation

Modified: `README.md`, `PORTFOLIO_CHANGES.md`, `docs/REPAIR_REPORT.md`, `docs/SCREENSHOTS.md`, `.gitignore`, `.github/workflows/ci.yml`, `Logo of the website.png`, and the three existing screenshots.

Added: `.gitattributes`, `assets/project-logo.svg`, `tools/render-logo.cjs`, `docs/ASSETS.md`, `docs/ASSET_MANIFEST.json`, `docs/TESTING.md`, `docs/PUBLICATION_REVIEW.md`, `docs/SCREENSHOT_MANIFEST.json`, and five screenshots: homepage, product desktop, cart, checkout and order history.

Application PHP behavior, CSS, JavaScript, SQL seed records, all 17 product image files and existing database contents were preserved during this publication-preparation stage. No other projects or repositories were modified.

## Publication outcome

The approved source and documentation are public. The original Academic Demo logo and all 17 product images remain unchanged from `9a00bd8`; the image origin is documented as the author's ChatGPT Image Generation coursework. The README clearly states that the project is not operated, sponsored or endorsed by Methodist College Kuala Lumpur.

Both jobs passed in the first hosted CI run. The publication-results update changes documentation and adds a real workflow badge; subsequent runs are available on the [Actions page](https://github.com/tayyab-hub/MCKL-Mart/actions/workflows/ci.yml).

Repository description: “Academic PHP and MariaDB e-commerce demo with authentication, a session cart, transactional checkout, stock validation and responsive design.”

Repository topics: `php`, `mysql`, `mariadb`, `javascript`, `ecommerce`, `web-development`, `portfolio-project`.

No publication blocker remains. The documented application limitations still apply: this is a locally runnable academic demo, with simulated payments and fulfillment, not a deployed production store.
