# Live deployment review — 15 October 2026

Timezone: Asia/Kuala_Lumpur. [Live demo](https://mcklmart.site.je/) · [Source repository](https://github.com/tayyab-hub/MCKL-Mart).

## Release status

Runtime commit `772b90fb615ee01785dcdf13928fdddcee20e0c1` was deployed successfully to InfinityFree's `/htdocs` directory on 15 October 2026. The nine existing files were backed up outside the public web root before replacement. All ten uploads matched the approved package by byte size and SHA-256 readback. `includes/demo.php` was uploaded first and `.htaccess` last. No rollback was needed.

Deployment reused an already-authenticated WinSCP session without extracting, exporting, logging or hard-coding credentials. Private configuration was neither downloaded nor changed. No schema migration, database reset or production-record creation, modification or deletion was performed. Browser cart checks changed only the review session, which was cleared afterward. The login test updated only the application's expected limiter state.

All **21 live checks passed**. HTTPS redirection, browser presentation and marker cleanup were also verified separately. The subsequent GitHub update records these results and reruns local tests; it does not change the deployed runtime or production database. Source changes are proposed through `codex/public-demo-hardening` into `main`, with merge subject to passing checks and owner approval.

## Findings and deployed fixes

| Finding before deployment | Deployed behavior and evidence |
| --- | --- |
| HTTP was not universally redirected | The earlier public control returned HTTP 200 without a Location header. The appended fixed-host rule now returns 302 to the same HTTPS path and query, without a loop. All original denial rules were preserved. |
| Persistent abuse controls were absent | Per-client/account/global limits, a bounded locked state file, spam trap, 16-KiB POST bound and record caps are deployed. Live login throttling and the POST bound passed; other quotas, caps and concurrent locking passed local tests. |
| Forms accepted visitor personal data | Public demo mode replaces personal fields with generated/fixed sample values and a fixed contact message. Live forms displayed these values; disposable local tests verified replacement of forged POST fields before storage. |
| Proxy-dependent cookie detection | The known public hostname always issues Secure cookies. Live HTTPS responses carried Secure, HttpOnly and SameSite=Lax; session rotation remains regression-tested locally. |
| Guest confirmation wording | The guest dialog explicitly says Demo Order Saved and that no real payment or delivery occurs. Its workflow passed locally; no hosted order was submitted during this review. |
| Existing data and stock need owner operations | Caps include all existing records and pause writes without deleting anything. Legacy data is not scrubbed, and demonstration orders still deduct sample inventory. The operations guide describes selective review. |

## The 21 executed live checks

These are deployment-time results, not continuous monitoring or a claim that every hosted workflow was exercised.

| Checks | Result |
| --- | --- |
| 1 — Login page | Actual application response, HTTP 200. |
| 2 — Session cookie | `MCKLMARTSESSID` carried Secure, HttpOnly and SameSite=Lax. |
| 3 — Security headers | `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin`. |
| 4–7 — Invalid CSRF | Login, registration, contact and checkout each rejected an invalid token with HTTP 403. |
| 8 — Oversized POST | Rejected with HTTP 413. |
| 9–10 — Fictional forms | Registration and contact rendered generated identities and read-only personal fields. |
| 11 — Public control | Harmless marker returned HTTP 200 with the expected marker text. |
| 12–18 — Protected markers | The root `.bak` marker and markers under `includes`, `database`, `tests`, `tools`, `docs` and `.local` were inaccessible. Each returned HTTP 302 to `https://errors.infinityfree.net/errors/403/`, the provider's access-denied page; these were not raw HTTP 403 responses. |
| 19 — Persistent login limit | Ten invalid attempts received generic failure responses; the eleventh returned HTTP 429 with `Retry-After` (882 seconds remaining when observed). No successful login or account creation was performed. |
| 20 — Homepage after tests | Actual application remained available with HTTP 200. |
| 21 — Unauthenticated history | Redirected to `index.php?auth=login`, the application's login entry point. |

InfinityFree's JavaScript browser challenge can also return HTTP 200. Challenge bodies were not counted as application success or private-file results. The public control's expected content and the provider's explicit denial destination distinguished protection from general hosting errors. No real configuration, customer record or secret-bearing URL was requested.

The history assertion initially expected `login.php`; it was corrected to the application's actual `index.php?auth=login` behavior and rechecked without changing application code.

## Additional deployment verification

- **HTTPS:** normal TLS certificate validation passed. Direct HTTP returned a fixed-host 302 preserving the path and query; HTTPS loaded without a redirect loop. The temporary redirect remains in place; HSTS is not enabled. The earlier certificate inspection recorded a ZeroSSL certificate valid through 14 November 2026; renewal remains a hosting responsibility.
- **Browser:** the homepage displayed the academic-demo notice; `green tea` search returned one result and all 17 catalogue images loaded. The session cart showed Chilled Green Tea, quantity 1, RM 3.50. Checkout preview displayed fixed read-only personal fields; no order was submitted.
- **Mobile:** checkout was checked at a 390×844 viewport, with document width 375 and no horizontal overflow. The session cart was cleared and the homepage remained healthy after cleanup.
- **Markers:** only the eight explicit paths in the [deployment checklist](DEPLOYMENT.md#completed-temporary-marker-cleanup) were deleted. A fresh recursive FTP search found zero matches, and the former public control displayed the provider's 404 page in a browser. Parent directories and legitimate files were retained.
- **Rollback:** nine original files remain in the private local backup, with a verified manifest. No backup or transfer log is part of the public repository. No rollback was necessary.

Before hardening, the owner reported successful homepage/images, search/filtering, registration/login, cart, simulated checkout, order history and logout. That report is historical owner evidence, not independent verification of successful production writes after this release.

## Local checks rerun for the GitHub review

Executed on 15 October 2026:

- **162 integration checks:** the original 116 plus 46 public-demo checks. Covers generated identities, forged-field replacement, authentication, guest-order linking, authorization, session-independent limits, spoofed client headers, body/quantity bounds, all storage caps, simultaneous last-slot registration and fail-closed corrupted state.
- **32 limiter/privacy checks:** includes eight concurrent PHP processes competing for a three-attempt quota, expiry, account/IP changes and bounded/corrupt/unavailable state.
- **21 helper checks and 33 static privacy/asset checks.**
- **Syntax:** all 25 tracked PHP files, three JavaScript sources and four external/rendered script blocks passed. The Actions workflow passed actionlint.
- **Cleanup:** both newly created disposable schemas were dropped and the temporary PHP servers stopped normally. Existing local and production databases were not used for test records.

The earlier 14 October local Apache marker checks and disposable mobile signup/checkout/history/logout checks also passed. They remain local historical evidence, not additional hosted checks. See [reproduction instructions](TESTING.md).

## Source, assets and privacy review

Runtime files (10): `.htaccess`, `checkout.php`, `contact.php`, `login.php`, `register.php`, `style.css`, `includes/bootstrap.php`, `includes/demo.php` (new), `includes/footer.php`, `includes/header.php`.

Repository-only changes (13): `.github/workflows/ci.yml`, `.gitignore`, `README.md`, `PORTFOLIO_CHANGES.md`, `docs/PUBLICATION_REVIEW.md`, `docs/TESTING.md`, `docs/DEMO_OPERATIONS.md` (new), `docs/DEPLOYMENT.md` (new), `docs/LIVE_DEPLOYMENT_REVIEW.md` (new), `tests/integration_test.php`, `tests/demo_scenarios.php` (new), `tests/demo_test.php` (new), `tools/prepare-deployment.ps1` (new).

The 81-file public tree excludes local configuration, credentials, limiter state, customer records, backups, logs, temporary probes and tool downloads. The SQL files contain only schema and sample catalogue data. Redacted Gitleaks scans cover the publication tree and outgoing Git history. All 17 product-image hashes, all eight screenshot hashes and both Mermaid blocks are preserved; relative documentation and image links resolve. The screenshots remain genuine captures of the original reviewed interface and are explicitly labeled as predating the additional demo restrictions.

The published baseline's [GitHub Actions run](https://github.com/tayyab-hub/MCKL-Mart/actions/runs/38567544084) passed. The workflow now includes the new limiter/privacy and extended integration suites. The pull request's Checks tab is the authoritative CI result for its exact revision; the README badge tracks `main`. Publishing or merging source does not deploy files to InfinityFree.

## Remaining limitations

- Successful account login, registration, completed checkout, authenticated owner history and contact writes were not independently repeated against production after deployment. They passed disposable local tests; production records were intentionally preserved.
- Only login rate limiting was exercised live. Other quotas, database caps, concurrent locking and injected failures retain local coverage. Operational login state does not prove every host concurrency scenario.
- Hosting-level PHP startup/parse-error settings were not independently inspected. Application exception handling cannot hide errors that occur before bootstrap runs.
- Free-hosting outages/resource limits, shared-IP throttling, deliberate quota exhaustion and distributed abuse remain possible. This is not DDoS protection or an uptime commitment.
- Legacy records are not anonymized, provider logs/session storage are outside application row caps, and visitors can still reuse a real password despite the temporary-password notice. Owner review and sample-inventory maintenance remain manual.
- TLS renewal needs ongoing hosting support; no automatic deployment/monitoring, HSTS, production load test or security certification is claimed.

See [deployment and rollback](DEPLOYMENT.md), [demo limits and cleanup](DEMO_OPERATIONS.md), and [test commands](TESTING.md).
