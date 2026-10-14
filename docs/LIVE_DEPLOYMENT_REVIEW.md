# Live deployment review — 14 October 2026

Timezone: Asia/Kuala_Lumpur. Live site: https://mcklmart.site.je/. Source: https://github.com/tayyab-hub/MCKL-Mart.

## Release status

The live baseline is accessible. The hardening candidate is prepared locally on `codex/public-demo-hardening`, based on published commit `d6adb518ba9a39ee4d324cf326fbdc3f4c7b471e`. It has not been pushed, merged or uploaded. No existing production website file or production database record was changed by this review. Browser cart testing changed only the review session and the cart was cleared afterward. The owner uploaded harmless access markers; their removal is still an owner action.

The owner reported passing homepage/images, search/filtering, registration/login, cart, simulated checkout, order history and logout. These are **user-reported**, not independent tests of this new release.

## Important findings and prepared fixes

| Finding | Evidence and response |
| --- | --- |
| HTTP was not universally redirected | A direct HTTP GET of the public control returned 200, marker text, and no Location header. Browser navigation upgraded to HTTPS, so it was not sufficient evidence of server enforcement. Prepared a fixed-host 302 rule while preserving all original rules. Hosted verification remains pending. |
| Persistent abuse controls were absent | Added per-client/account/global limits, a bounded locked state file, form spam trap, 16-KiB POST bound and record caps. No database migration is needed. |
| Forms accepted visitor personal data | Public demo mode now replaces personal fields with generated/fixed sample values and uses a fixed contact message. The UI labels these choices and asks for a temporary password. Original validation stays available in explicit local coursework mode. |
| Proxy-dependent cookie detection | The known public hostname now always issues Secure cookies without trusting arbitrary forwarding headers. HttpOnly, SameSite=Lax and session rotation remain. Actual new hosted cookie flags must be checked after upload. |
| Guest confirmation wording | A regression test caught missing explicit simulated-fulfillment wording in the guest dialog; it now says Demo Order Saved and that no real payment/delivery occurs. |
| Existing data and stock need owner operations | Caps count all records and pause writes without deleting anything. Existing private data is not scrubbed, and simulated orders still deduct sample stock. A selective manual cleanup procedure is documented. |

## Independently executed hosted checks

| Check | Result |
| --- | --- |
| TLS certificate validation | Passed via a standard verified TLS connection: TLS 1.3, ZeroSSL ECC DV SSL CA 2 issuer, SAN includes `*.site.je`, valid 16 August–14 November 2026. Monitor renewal through the hosting provider. |
| Homepage | Rendered in a real browser with academic-demo notice. |
| Search | `green tea` returned one matching product. |
| Category and images | Meals returned four products; all four images loaded. |
| Cart | Added Tuna Melt Sandwich, observed quantity 1 / RM 8.90, then cleared the session cart. |
| Checkout | Form/Place Demo Order control rendered; no hosted order was submitted. |
| Mobile | Catalogue and checkout checked at a 390×844 viewport; document width 375, no horizontal overflow. |
| Public control | Harmless marker text visible after a cache-busting recheck. An earlier 404 was cached; it was not counted as a passed control check. |
| Seven protected paths | All showed InfinityFree's 403 Forbidden page: includes, database, tests, tools, docs, `.local`, and a root `.bak` marker. The owner reported uploading the files with their directory structure. Browser denial proves access was blocked, not direct filesystem presence. |
| Existing live rules | Owner pasted `/htdocs/.htaccess`; it matched the local baseline. No live rule was overwritten. |
| Direct HTTP control | **Failed redirect expectation:** HTTP 200 without Location, confirming a remaining hosted redirect gap. |

Initial command-line HTTPS/HTTP fetches were interrupted or received InfinityFree's JavaScript browser challenge. Those HTTP-200 challenge responses were **not** counted as application success or private-file access results. A real browser completed the marker checks. No secret-bearing URL or real config was requested.

Hosted negative CSRF tests, authentication/authorization tests, runtime error injection, actual session response attributes and new rate/storage limits are not independently verified. Production record-writing tests were deliberately left for the owner to run after reviewing/uploading the candidate; local tests cover those paths. Server PHP startup error settings and filesystem locking support still need host validation.

## Executed local candidate checks

- Original integration suite: 116 checks passed.
- Extended integration suite: 162 total checks, including 46 public-demo checks. Covers generated identities, replacement of forged personal fields, CSRF, cookie policy, login after signup, guest-order linking, authorization, session-independent limits, spoofed client headers, body/quantity bounds, all storage caps, simultaneous last-slot registration and fail-closed corrupted state.
- Dedicated limiter/privacy suite: 32 checks, including eight concurrent PHP processes competing for a three-attempt quota, expiry, account/IP changes and bounded/corrupt/unavailable state.
- Helper suite: 21 checks. Static privacy/asset suite: 33 checks.
- PHP syntax: 25 tracked/new PHP source files; no failures. Four external/rendered JavaScript blocks parsed. Workflow accepted by actionlint.
- Local Apache: harmless control HTTP 200; seven protected markers HTTP 403. Fixed public Host produced HTTP 302 to the fixed HTTPS domain, while localhost remained accessible. All eight local markers were removed afterward.
- Mobile browser preview used only newly created disposable databases. Signup with generated fields, cart and simulated delivery/card checkout worked. Public hosting records were not used.
- Mobile order history and logout also worked. Spam-trap controls were verified hidden and excluded from visible keyboard focus. Both disposable schemas and preview servers were cleaned up after testing.

## Exact candidate files

Runtime upload (10): `.htaccess`, `checkout.php`, `contact.php`, `login.php`, `register.php`, `style.css`, `includes/bootstrap.php`, `includes/demo.php` (new), `includes/footer.php`, `includes/header.php`.

Repository-only changes (13): `.github/workflows/ci.yml`, `.gitignore`, `README.md`, `PORTFOLIO_CHANGES.md`, `docs/PUBLICATION_REVIEW.md`, `docs/TESTING.md`, `docs/DEMO_OPERATIONS.md` (new), `docs/DEPLOYMENT.md` (new), `docs/LIVE_DEPLOYMENT_REVIEW.md` (new), `tests/integration_test.php`, `tests/demo_scenarios.php` (new), `tests/demo_test.php` (new), `tools/prepare-deployment.ps1` (new).

No product image, screenshot, schema, seed, private configuration or other repository is changed.

Final packaging checks passed: 81 staged candidate files, 41 resolved relative documentation/image links, unchanged hashes for all 17 product images and eight screenshots, and byte-identical preserved Mermaid blocks. Exactly ten runtime files match the staged source and SHA-256 upload manifest. Redacted Gitleaks scans found no leaks in either the source candidate or upload package. The existing private database password was compared privately against the candidate and was absent; the private config's baseline hash is unchanged.

The published baseline's [GitHub Actions run](https://github.com/tayyab-hub/MCKL-Mart/actions/runs/38567544084) passed. The modified workflow adds the new limiter tests and extended integration checks, but **no hosted CI result is claimed for this unpushed branch**. The eight gallery screenshots, architecture diagrams and product assets remain preserved. New source/documentation link checks and package privacy checks are part of the release handoff.

## Readiness

Ready for owner review and a controlled manual upload, **not yet verified as deployed hardening**. Confirm the HTTPS redirect, Secure cookie flags, writable locked limiter state, actual hosted workflows and marker cleanup before calling the live release ready for recruiters. Free-hosting outages/resource limits, shared-IP throttling, deliberate quota exhaustion, provider logs, reused visitor passwords and legacy records remain practical limitations. No production-security certification is claimed.

See [exact upload/removal instructions](DEPLOYMENT.md), [demo limits and cleanup](DEMO_OPERATIONS.md) and [test commands](TESTING.md). GitHub push/merge requires the owner's approval after the local and hosted review.
