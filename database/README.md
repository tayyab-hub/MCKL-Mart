# Public demo database

Use **mckl_mart_portfolio** and keep the original coursework database separate. Public SQL contains six tables, four categories and seventeen sample products. It contains no user, order, detail or contact-message records, password hashes, credentials or private addresses.

## Safe installation

Create a **new empty** database with utf8mb4_unicode_ci in phpMyAdmin. Import either setup.sql, or schema.sql followed by seed.sql. Do not run both approaches on the same database. Do not re-import into an existing database. The scripts omit DROP, TRUNCATE, REPLACE and automatic database selection.

setup.sql is exactly schema.sql, one newline, then seed.sql. The static suite checks this. Both import paths were executed against fresh disposable MariaDB 10.4.32 databases on 14 October 2026.

## Relationships

| Parent | Child | Deletion behavior |
| --- | --- | --- |
| categories | products | Reject a category deletion while referenced |
| users | orders | Keep the order, set user_id to NULL |
| orders | order_details | Cascade deletion of dependent lines |
| products | order_details | Keep purchased name/price, set product_id to NULL |

All tables use InnoDB, primary keys and auto-increment IDs. Emails and category slugs have unique indexes; foreign-key paths are indexed. Contact messages are independent. Prices/totals use DECIMAL. PHP uses Asia/Kuala_Lumpur and the connection uses +08:00.

New imports add CHECK constraints for nonnegative prices, stock and totals; featured flags; payment-method values; quantities 1–99; and line subtotal arithmetic. **These extra constraints have not been applied to the existing portfolio database.** Existing tables and records were inspected without alteration. The application also validates prices, quantities and stock. Existing installations need a separately reviewed migration, not a setup.sql re-import.

One demo product is intentionally out of stock. Sample stock and catalogue descriptions are not real business metrics.

## Tests and permissions

The integration runner creates fresh random mckl_mart_test_* databases and refuses to reuse an existing one. Fixture updates/deletions, fault-injection triggers and races run only there. Cleanup targets only databases created during that invocation.

The restricted mckl_mart_app account uses SELECT, INSERT, UPDATE and DELETE on the portfolio schema, with no global administration privileges. A separate local test administrator is needed to create/drop temporary databases and observe competing queries. Never switch the normal storefront to root for convenience.
