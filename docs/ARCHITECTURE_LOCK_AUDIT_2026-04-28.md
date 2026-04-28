# Architecture Lock Audit (2026-04-28)

## Scope
- `includes/*`
- `pages/*`
- `api/*`
- `app/*`
- `routes/*`

## Violations Identified Before Fixes

### 1) SQL outside repository layer
- `includes/*`: 10 files
  - `includes/config.php`
  - `includes/control_lookup_conn.php`
  - `includes/government-labor.php`
  - `includes/modal_permissions.php`
  - `includes/payment_control_registration_sync.php`
  - `includes/payment_orders_schema.php`
  - `includes/permissions.php`
  - `includes/simple_modal.php`
  - `includes/support_chat_db.php`
  - `includes/TenantLoader.php`
- `pages/*`: 33 files with SQL patterns
- `api/*`: 300+ files with SQL/DB patterns (legacy procedural endpoints)

### 2) Duplicate workflow submissions / endpoint variants
- Legacy onboarding route variant existed in `routes/worker_platform.php` (removed in hardening pass).
- JS workflow submission duplicates in worker script were previously removed.
- Current onboarding endpoint reference in JS is unified to:
  - `/workflows/worker-onboarding`

### 3) UI layer business logic
- `includes/*` and `pages/*` still contain substantial conditional/domain logic in many legacy files.

## Hardening Fixes Applied in this pass

1. Added architecture checker:
   - `tools/architecture_lock_check.php`
2. Checker enforces:
   - no SQL in `includes/` and `pages/`
   - single onboarding endpoint in JS
   - no duplicate worker AI submit helpers
3. Confirmed pass:
   - single onboarding endpoint usage in JS
   - no duplicate worker AI submit helpers
4. Confirmed fail (known remaining legacy debt):
   - SQL in `includes/` and `pages/` with explicit file lists above

## Notes
- This audit documents baseline violations for controlled migration batches.
- Onboarding architecture remains locked to one endpoint and one JS submission system.
