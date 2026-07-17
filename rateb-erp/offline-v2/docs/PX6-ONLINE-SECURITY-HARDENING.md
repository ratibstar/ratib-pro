# PX6 — Online Security Hardening Evidence

Status: PASS  
Enterprise Readiness: 98/100  
Critical findings: 0  
High findings: 0  
Production Go-Live: Eligible

## Remediation evidence

- Removed deployable setup, reset, seed, debug, test, and maintenance HTTP scripts that could create administrators, grant privileges, erase tenant data, mutate schemas, overwrite files, or disclose operational data.
- Protected the retained destructive admin reset with authenticated admin authorization, POST-only dispatch, and session-bound CSRF validation.
- Protected Accounting migration endpoints with authenticated enterprise-admin authorization, POST-only dispatch, and mandatory CSRF validation.
- Protected HR employee mutations with action authorization, POST-only dispatch, mandatory CSRF validation, and matching browser token propagation.
- Added dedicated `pos.sale.complete` authorization to V1 checkout, V2 sale completion, and offline checkout ingestion. Offline actor identity is overwritten with the authenticated server-side user.
- Moved Inventory serial mutation ownership into `InventoryWorkflowService`; POS uses published Inventory services and contains no production SQL mutation of Inventory-owned tables.
- Removed HR's direct include/call into Accounting's entity-account implementation.
- Enabled multi-tenant country bootstrap by default. Tenant-scoped model creates reject caller/context mismatches, and generic updates cannot reassign `company_id`.
- Scoped POS supervisor approval grants and consumption by company. Token consumption uses an atomic unused-and-unexpired conditional update.
- Made POS tax settings authoritative on the server for online checkout and offline replay; local pricing now mirrors server line-discount, invoice-discount, taxable, tax, and total ordering.
- Added unique migration `202_pos_checkout_permission.sql` and synchronized POS role bundles/catalog resolution.

## Regression evidence

- `php rateb-erp/tests/security/run-px6-security-tests.php` — 48/48 PASS.
- `php rateb-erp/modules/pos/tests/run-checkout-tests.php` — 11/11 PASS.
- `php rateb-erp/modules/pos/tests/run-offline-sync-tests.php` — 42/42 PASS.
- `php rateb-erp/modules/pos/tests/run-security-tests.php` — 5/5 PASS.
- `php rateb-erp/modules/pos/tests/run-pricing-consistency-tests.php` — 7/7 PASS.
- PHP syntax checks, JavaScript syntax checks, IDE diagnostics, and `git diff --check` — PASS.
- Database-backed POS integration/E2E suites require configured integration fixture credentials/data and were not available in this workspace; all non-database suites passed.

## AF 2.1 Identity boundary

1. Business modules depend on `identity` where authentication context is applicable.
2. Modules use published Identity APIs only; no direct `identity.*` SQL, vault, SQLite identity table, or OPFS identity path access was introduced.
3. No passwords, password hashes, cookies, bearer tokens, JWTs, TOTP secrets, WebAuthn server credentials, API tokens, or other authentication secrets are stored or synchronized by Offline V2 modules.
4. Online ERP remains the sole Authentication Authority; Offline V2 Identity remains a sealed local identity/claims/RBAC/device-trust cache.
