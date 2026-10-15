# Reproducing the checks

Run from the repository root. PHP needs `pdo_mysql`, `mbstring` and `curl`; Node.js is needed for JavaScript checks. The application itself needs no npm or Composer installation.

## Fast checks

```powershell
& C:\xampp\php\php.exe tests/helpers_test.php
& C:\xampp\php\php.exe tests/static_test.php
& C:\xampp\php\php.exe tests/demo_test.php
node --check script.js
node --check tests/javascript_test.cjs
node --check tools/render-logo.cjs
Get-ChildItem -Recurse -Filter *.php -File |
    Where-Object { $_.FullName -notlike '*\.local\*' } |
    ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

## Disposable database and HTTP suite

`tests/integration_test.php` requires a local test administrator with CREATE/DROP DATABASE, table/trigger permissions and PROCESS for concurrent-request observations. Do not expand the storefront account's permissions for testing.

For a local XAMPP administrator that **already** accepts an empty password:

```powershell
$env:MCKL_TEST_DB_USER = 'root'
& C:\xampp\php\php.exe tests/integration_test.php --run
Remove-Item Env:MCKL_TEST_DB_USER
```

If the test account has a password, set `MCKL_TEST_DB_PASSWORD` privately. In PowerShell 7:

```powershell
$env:MCKL_TEST_DB_PASSWORD = Read-Host 'Test administrator password' -MaskInput
# Run the suite, then remove the value from this process.
Remove-Item Env:MCKL_TEST_DB_PASSWORD
```

Optional `MCKL_TEST_DB_HOST` / `MCKL_TEST_DB_PORT` default to `127.0.0.1:3306`. Only loopback hosts are accepted. The runner creates two random `mckl_mart_test_*` schemas, verifies combined and split imports, starts two temporary PHP servers, and drops only schemas created by that invocation. It never imports into the portfolio or coursework database.

Coverage includes authentication, carts, search, validation, CSRF, authorization, contact storage, prices, inventory, duplicate submission, concurrent registration and competing checkout for the final item. A disposable failure-injection trigger verifies rollback of orders, lines and stock.

The baseline servers explicitly use `MCKL_DEMO_MODE=0` to preserve the original 116 regression checks. Two additional servers enable public demo mode on the other newly created schema with a shared, isolated rate-state file. An additional 46 checks verify the demo's identity replacement, limits, capacity, cookie policy and failures. Both schemas are removed by the runner. The separate 32-check `demo_test.php` suite exercises concurrent filesystem locking without a database.

## Browser preview and rendered scripts

Add `--serve` to keep the disposable preview running for up to 30 minutes. The command prints a browser URL and test-directory path. Use fictional details only.

Add `--demo-preview` alongside `--serve` to preview the hardened public forms. After the capacity tests, only that invocation's disposable demo records/state are cleared for browser QA; the existing portfolio and hosted databases are never accessed. Do not run automated capacity tests against the public website.

```powershell
node tests/javascript_test.cjs http://127.0.0.1:PRINTED_PORT
New-Item -ItemType File -Path 'PRINTED_TEST_DIRECTORY/stop'
```

Creating `stop` triggers normal cleanup. Logs, sessions and the created-schema manifest remain under ignored, HTTP-blocked `.local/`. If the process is forcefully killed, inspect that invocation's `created-databases.json` before removing only its exact newly created schemas. Never drop databases based only on a broad name prefix.

The integration suite deliberately changes its own product fixtures. For presentation screenshots, the clean combined-import catalogue was copied back into the disposable preview's catalogue; existing project databases were untouched. Browser orders used a separate fictional customer.

## CI and evidence

The GitHub workflow uses Ubuntu, PHP 8.2, Node 22 and an isolated MariaDB 10.4 service. It uses no local Windows paths or private credentials. The empty database password belongs only to that disposable CI service. Workflow permissions are limited to reading repository contents.

The 15 October 2026 local rerun passed 162 integration checks, 32 limiter/privacy checks, 21 helper checks and 33 static checks. All 25 tracked PHP files and three JavaScript sources parsed, and four external/rendered script blocks passed the disposable-preview check. Both test databases were dropped and the temporary PHP servers stopped normally.

Local workflow syntax/semantics passed actionlint 1.7.12. Gitleaks 8.30.1 provides redacted scans of the publication tree and outgoing Git history. These tools were obtained from their official releases and their archive checksums verified; tool archives and outputs are excluded from Git. The workflow runs on both pushes and pull requests; the pull request's Checks tab identifies the result for its exact revision, independently of the README's `main` badge.

The [live deployment review](LIVE_DEPLOYMENT_REVIEW.md) records 21 hosted checks plus HTTPS, browser and marker-cleanup verification from 15 October 2026. Production record-creating workflows were not repeated: successful registration/login, checkout, contact storage, database caps and concurrent behavior are covered by disposable local tests. The GitHub documentation update did not repeat live probes or modify the production database.

Original publication evidence remains in [PUBLICATION_REVIEW.md](PUBLICATION_REVIEW.md), and earlier repair findings remain in [REPAIR_REPORT.md](REPAIR_REPORT.md). `layout-checks.json` is historical evidence of 32 viewport measurements, not a fresh accessibility certification.
