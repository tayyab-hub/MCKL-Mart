# Manual InfinityFree release

Target: `https://mcklmart.site.je/`, document root `/htdocs`. This document describes the unpushed `codex/public-demo-hardening` candidate. The current live site and published `main` are the earlier baseline until the owner uploads and verifies the release.

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

1. Review the [deployment findings](LIVE_DEPLOYMENT_REVIEW.md), runtime diff and manifest. The current live `.htaccess` pasted by the owner matched the original local rules. The candidate preserves every existing rule and appends an eight-line, domain-scoped HTTPS redirect block.
2. In WinSCP, connect using the existing saved site privately. Do not send FTP/database passwords in chat. Confirm `/htdocs` contains the live `index.php`. Enable display of hidden files so `.htaccess` is visible.
3. Save rollback copies of just the affected existing runtime files **outside the public web root** on your computer. Do not create `.bak` copies on the server. Keep the live `includes/config.local.php` and any generated `includes/.demo-state.php` untouched.
4. Choose a quiet period. Upload `includes/demo.php` first, then `includes/bootstrap.php`, then the four root PHP files and the header/footer/CSS. Upload `.htaccess` last. The helper is backwards-compatible during this short manual transition, but FTP is not an atomic deployment; verify promptly afterward.
5. Do not upload docs, tests, database scripts, `.local` contents, the manifest, local config or the generated limiter state. Do not import any SQL. The existing include-directory protection already applies to the new helper/state file.
6. Confirm HTTPS still loads. Verify HTTP redirects to the **same path on `https://mcklmart.site.je`**, with no loop. The redirect is deliberately temporary (302) during rollout. It checks the native HTTPS flag and the hosting proxy's forwarded scheme as recommended by [InfinityFree](https://forum.infinityfree.com/t/how-to-force-all-traffic-to-https/49322/); hosted behavior must be checked rather than assumed from local Apache tests. It does not redirect localhost.
7. In browser developer tools, inspect the HTTPS document response: `MCKLMARTSESSID` must have `Secure`, `HttpOnly` and `SameSite=Lax`. Verify that PHP's hosting setting `display_errors` is disabled; application error handling cannot hide a parse/startup error that occurs before it runs.
8. Use only generated fictional data and a temporary password. Test signup, logout/login using the generated email, product search, cart, simulated checkout and owner-only history. Contact uses a fixed sample message. Test a missing/invalid CSRF token and a controlled rate-limit case; confirm 403/429 and no extra record. Do not flood the free host.
9. Check mobile layout, private-path protection and the homepage afterward. Confirm there are no generic 503 protection failures. If protection storage fails, leave it fail-closed and investigate filesystem support; do not disable the guard or change database permissions as a workaround.
10. Tell the reviewer what was uploaded and the test outcome. Deployment success is not claimed until the owner verifies the uploaded version. Review and approve the GitHub push/merge separately after local and hosted checks pass. Then run GitHub Actions for that exact revision and reconcile the deployment manifest with it.

## Rollback

If a newly uploaded redirect loops, restore only the previous `.htaccess` from the private rollback copy and recheck HTTPS. If the runtime release fails, restore the previous nine changed existing runtime files; `includes/demo.php` is the only new runtime file. Keep configuration and database records untouched. Preserve the state file for investigation rather than resetting limits. A rollback restores the earlier behavior, including its known public-demo gaps; do not label that release hardened.

## Remove the temporary access markers

Live marker checks are complete. Remove only these eight files via WinSCP; retain the directories and legitimate files. No live cleanup was performed by the reviewer.

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
