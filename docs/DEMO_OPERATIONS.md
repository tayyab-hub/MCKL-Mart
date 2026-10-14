# Public demo operations

This release candidate is for an unofficial academic demonstration. It does not process payments, deliveries or support emails. Its controls require a writable `includes` directory and PHP `flock()` support on one shared application filesystem; hosted verification is required before declaring the release ready.

## Privacy choices and trade-offs

Public demo mode defaults to on. The server replaces submitted personal fields **before validation, error-page reflection and database insertion**:

- Signup: `Demo Visitor`, a generated `demo-` + 12 hexadecimal digits + `@example.test` address, and no phone.
- Checkout: `Demo Visitor`, the account's generated demo email (or the session's generated identity), `0100000000`, and `10 Example Street, Demo City` for delivery.
- Contact: the generated identity, no phone, and the fixed message `This is a fictional demonstration message. No reply or service is requested.` The subject remains selectable.

The fields are read-only in the browser, and forged POST values are replaced on the server. Passwords remain visitor-chosen and hashed; the UI asks visitors to choose a temporary password they do not use elsewhere. Signup still links the same session's guest order. The Account panel shows the generated email for later login.

This removes free-form personal-data entry from the public demonstration while preserving signup, login, cart, checkout and history. It limits how much visitors can exercise input-validation errors; the original workflow and validation remain tested with the trusted environment variable `MCKL_DEMO_MODE=0`. Do not set that variable to `0` on the public host. This setting never comes from a URL, cookie or form field.

Old accounts and records are not modified, scrubbed or deleted. Existing users can still log in. Provider access logs and PHP session storage remain outside this application's record caps. Visitors can still reuse a real password despite the notice; the application cannot determine whether a password is reused elsewhere.

## Persistent limits

All counts are attempts with a valid CSRF token, including invalid forms and filled spam traps. Rejected attempts do not extend an existing time window. HTTP 429 includes `Retry-After` for time-based limits; the lifetime account-order limit requires owner cleanup instead of a misleading retry time.

| Action | Per client address | Additional limit |
| --- | --- | --- |
| Login | 10 per 15 minutes | 10 per account identifier per 15 minutes; 200 globally per 15 minutes |
| Signup | 3 per 24 hours | 50 globally per 24 hours |
| Contact | 5 per hour | 100 globally per 24 hours |
| Checkout | 10 per hour and 20 per 24 hours | 100 globally per 24 hours; 20 saved orders per account |

The limiter uses `REMOTE_ADDR`; spoofed `X-Forwarded-For` and `Client-IP` values do not bypass it. College Wi-Fi, NAT or a hosting proxy may cause multiple visitors to share a limit. This is a conservative trade-off for a low-traffic portfolio. The global limits and storage caps also bound ordinary database growth when an attacker changes IP addresses. This is not DDoS protection; a visitor can temporarily exhaust a quota.

State is kept in ignored `includes/.demo-state.php`, guarded by existing directory/dotfile rules and an executable PHP denial wrapper. It contains a random HMAC key and hashed counter keys, not raw IPs, emails, passwords or form contents. There is one file, at most 256 KiB and 1,024 active buckets. Expired buckets are pruned during later submissions; there is no cron task. A crash, full/corrupt file, unavailable lock or write failure pauses submissions with a generic 503 rather than bypassing protection. Browsing stays available.

Do not upload or regularly delete the state file: replacing it resets quotas. Do not grant world-writable permissions. If a test reports a 503, check host filesystem support/permissions privately and inspect server logs. A different storage design would need a separate review if the host cannot support file locking. Test-only state overrides use the trusted `MCKL_DEMO_STATE_PATH` environment variable.

## Storage and inventory bounds

| Table | Maximum existing rows before new writes pause |
| --- | ---: |
| `users` | 200 |
| `orders` | 500 |
| `order_details` | 5,000, including the proposed order's lines |
| `contact_messages` | 200 |

All existing rows count, including pre-release records. At capacity, new submissions return a generic 503; nothing is automatically deleted. The limiter's exclusive file lock is held through the count and database commit, preventing two application workers from exceeding a cap concurrently. Direct administrator writes or another application do not use this guard.

Orders allow at most 10 different products and 1–3 units of each. POST bodies are limited to 16 KiB by the application. Existing stock checks and transactional stock deduction remain intact, so demonstration orders still reduce sample inventory. The owner must review any restocking separately; this release does not reset products, orders or database tables.

## Manual record review and cleanup

No cleanup has been performed on production. Review demonstration records periodically, for example weekly, and whenever storage reaches a cap. A seven-day review threshold is an operational suggestion, not an automated retention promise.

Use the host's phpMyAdmin after signing in privately. First select only records created by this restricted demo, inspect their IDs and counts, and choose a small explicit batch. Never infer that all old records are safe to remove merely because their email ends in `.test`.

```sql
-- Read-only candidates; review IDs without exporting personal data.
SELECT order_id, created_at FROM orders
WHERE customer_name = 'Demo Visitor'
  AND customer_phone = '0100000000'
  AND customer_email REGEXP '^demo-[a-f0-9]{12}@example[.]test$'
  AND created_at < CURRENT_TIMESTAMP - INTERVAL 7 DAY
ORDER BY order_id LIMIT 50;

SELECT contact_id, created_at FROM contact_messages
WHERE name = 'Demo Visitor' AND phone IS NULL
  AND email REGEXP '^demo-[a-f0-9]{12}@example[.]test$'
  AND message = 'This is a fictional demonstration message. No reply or service is requested.'
  AND created_at < CURRENT_TIMESTAMP - INTERVAL 7 DAY
ORDER BY contact_id LIMIT 50;

SELECT user_id, created_at FROM users u
WHERE name = 'Demo Visitor' AND phone IS NULL
  AND email REGEXP '^demo-[a-f0-9]{12}@example[.]test$'
  AND created_at < CURRENT_TIMESTAMP - INTERVAL 7 DAY
  AND NOT EXISTS (SELECT 1 FROM orders o WHERE o.user_id = u.user_id)
ORDER BY user_id LIMIT 50;
```

Before deletion, obtain the owner's explicit approval for the exact IDs and take an appropriately protected backup if needed. Delete approved orders first (their order lines cascade), approved contact records next, and only approved accounts with no remaining orders. In a transaction, repeat the same predicates **and** constrain the deletion to the reviewed IDs; inspect the affected row count before committing. Use `ROLLBACK` if anything differs. Do not run blanket `DELETE`, `TRUNCATE`, `DROP`, schema re-imports or password exports. Older records outside these predicates need a separate manual review. Deleting an order does not restore stock automatically.

No schema migration or remote database change is required to deploy the runtime protections.
