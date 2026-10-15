# InfinityFree deployment and rollback

Target: [mcklmart.site.je](https://mcklmart.site.je/), document root `/htdocs`. Runtime commit `772b90fb615ee01785dcdf13928fdddcee20e0c1` was deployed on **15 October 2026** through an already-authenticated WinSCP session. Nine existing files were backed up locally outside the web root before replacement; all ten uploaded files matched the source manifest by size and SHA-256 readback. Twenty-one live checks passed, the HTTP-to-HTTPS redirect was verified, and all eight temporary security markers were removed. No rollback was needed.

Private configuration and production database records were untouched. Credentials were not extracted, exported or logged. The GitHub follow-up changes documentation only relative to that deployed runtime. GitHub Actions validates source changes; it does not upload to InfinityFree. See [deployment evidence and limitations](LIVE_DEPLOYMENT_REVIEW.md).

## Upload exactly these runtime files

The prepared local folder is `.local/deployment-hardening/upload/`. Transfer its **contents**, preserving the directory layout:

```text
.htaccess
checkout.php
contact.php
login.php
register.php
style.css
includes/bootstrap.php
includes/demo.php
includes/footer.php
includes/header.php
```

The package builder `tools/prepare-deployment.ps1` uses this explicit allowlist, checks changed runtime files against it, and produces a SHA-256 manifest beside the upload directory. No private configuration, credentials, customer data, SQL exports, databases, state files, tests, archives, product artwork or screenshots belong in this upload.

For a future approved release, repeat the following checks against the actual server state; do not assume it still matches an earlier backup.

1. Review the [deployment findings](LIVE_DEPLOYMENT_REVIEW.md), runtime diff and manifest. Compare the existing live `.htaccess` before replacing it. This release preserved every original rule and appended an eight-line, domain-scoped HTTPS redirect block.
2. In WinSCP, reuse an authenticated connection or sign in privately. Do not send FTP/database passwords in chat or place them in scripts/logs. Confirm `/htdocs` contains the live `index.php`. Enable display of hidden files so `.htaccess` is visible. Controlled automation may use this authenticated session; full-folder synchronization is not part of this procedure.
3. Save rollback copies of just the affected existing runtime files **outside the public web root** on your computer, and verify their completeness before uploading. The first hardening release replaced nine existing files and added `includes/demo.php`; future backups must include that helper if it already exists. Do not create `.bak` copies on the server. Keep the live `includes/config.local.php` and any generated `includes/.demo-state.php` untouched.
4. Choose a quiet period. Upload `includes/demo.php` first, then `includes/bootstrap.php`, then the four root PHP files and the header/footer/CSS. Upload `.htaccess` last. The helper is backwards-compatible during this short transition, but FTP is not an atomic deployment; verify promptly afterward. Read back only the approved runtime files and compare their sizes and SHA-256 hashes with the package.
5. Do not upload docs, tests, database scripts, `.local` contents, the manifest, local config or the generated limiter state. Do not import any SQL. The existing include-directory protection already applies to the new helper/state file.
6. Confirm HTTPS still loads with normal certificate validation. Verify HTTP redirects to the **same path and query on `https://mcklmart.site.je`**, with no loop. The verified redirect remains temporary (302); HSTS is not enabled. It checks the native HTTPS flag and the hosting proxy's forwarded scheme as described by [InfinityFree](https://forum.infinityfree.com/t/how-to-force-all-traffic-to-https/49322/); hosted behavior must be checked rather than assumed from local Apache tests. It does not redirect localhost.
7. In browser developer tools, inspect the HTTPS document response: `MCKLMARTSESSID` must have `Secure`, `HttpOnly` and `SameSite=Lax`. Verify that PHP's hosting setting `display_errors` is disabled; application error handling cannot hide a parse/startup error that occurs before it runs.
8. Check product search, cart and checkout preview without creating database records. Where separately authorized, use only generated fictional data and a temporary password to test signup, logout/login, completed simulated checkout, owner-only history and the fixed contact message. These successful record-writing workflows were tested locally, not repeated on production during this deployment. Bounded live negative tests verified invalid CSRF (403), oversized POST (413) and login throttling (429 with `Retry-After`). Do not flood the free host or exercise storage caps against production.
9. Check mobile layout, private-path protection and the homepage afterward. Confirm there are no generic 503 protection failures. If protection storage fails, leave it fail-closed and investigate filesystem support; do not disable the guard or change database permissions as a workaround.
10. Record what was uploaded, backup/readback verification, test outcomes, marker cleanup and any rollback. Distinguish live evidence from local tests and unresolved limitations. Publish source changes on a separate reviewed branch and require passing GitHub Actions plus owner approval before merging. A source-only documentation update requires no additional hosting upload.

## Rollback

If a newly uploaded redirect loops, restore only the previous `.htaccess` from the private rollback copy and recheck HTTPS. For the first hardening release, a runtime rollback restores the nine backed-up existing files; `includes/demo.php` was its only new runtime file and is unused by the restored baseline. For later releases, restore the exact affected files from that release's verified backup, including the helper when present. Keep configuration and database records untouched. Preserve the state file for investigation rather than resetting limits. Restoring the pre-hardening baseline also restores its known public-demo gaps; do not label that release hardened. No rollback was required on 15 October 2026.

## Completed temporary-marker cleanup

These exact eight temporary files were deleted through WinSCP after successful testing on 15 October 2026. A fresh recursive FTP search returned zero matches, and the public control URL displayed InfinityFree's 404 page in the browser. Parent directories and legitimate application files were retained; there is no outstanding cleanup for these markers.

```text
/htdocs/mckl-harmless-check-7f2a81.txt
/htdocs/mckl-harmless-check-7f2a81.bak
/htdocs/includes/mckl-harmless-check-7f2a81.txt
/htdocs/database/mckl-harmless-check-7f2a81.txt
/htdocs/tests/mckl-harmless-check-7f2a81.txt
/htdocs/tools/mckl-harmless-check-7f2a81.txt
/htdocs/docs/mckl-harmless-check-7f2a81.txt
/htdocs/.local/mckl-harmless-check-7f2a81.txt
```

If protection is retested after upload, use fresh harmless marker names, include a public control, and remove those new markers afterward. Never test confidentiality by downloading the real config, password exports or customer records.
