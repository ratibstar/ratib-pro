# Accounting Hardening Runbook

This runbook covers full rollout for accounting security/integrity hardening.

## Scope

- CSRF enforcement on accounting POST APIs
- Country-scope authorization checks for ID-based accounting actions
- Transactional approval state updates
- CSV formula-injection protection
- Safer modal HTML rendering (XSS hardening)
- GL reference collision protection (app lock + DB unique key)
- Dashboard/report transaction-type consistency fixes

## Files Changed

- `control-panel/api/control/accounting.php`
- `control-panel/api/control/accounting-registration-revenue-export.php`
- `control-panel/includes/control/accounting-content.php`
- `control-panel/includes/control/accounting-financial-report-data.php`
- `control-panel/js/accounting-modals.js`
- `control-panel/js/accounting-page.js`
- `config/migrations/separate_control_panel_db/02_create_tables.sql`
- `config/migrations/accounting_001_unique_journal_reference.sql`

## Pre-Deployment Checklist

1. Confirm maintenance window and approvers.
2. Backup database:
   - `outratib_control_panel_db`
3. Backup code / create release tag.
4. Ensure you can run emergency rollback SQL and redeploy previous code.

## Database Precheck (Mandatory)

Run this first in production:

```sql
USE outratib_control_panel_db;

SELECT reference, COUNT(*) AS cnt
FROM control_journal_entries
WHERE reference IS NOT NULL AND TRIM(reference) <> ''
GROUP BY reference
HAVING COUNT(*) > 1
ORDER BY cnt DESC, reference ASC;
```

- If rows are returned, resolve duplicates before adding unique index.
- If no rows returned, continue.

## Migration Execution Order

1. Apply existing-db migration:
   - `config/migrations/accounting_001_unique_journal_reference.sql`
2. For new environments only:
   - `config/migrations/separate_control_panel_db/02_create_tables.sql` already includes unique key.

## Application Deployment Order

Deploy all changed files together in one release:

1. `control-panel/api/control/accounting.php`
2. `control-panel/api/control/accounting-registration-revenue-export.php`
3. `control-panel/includes/control/accounting-content.php`
4. `control-panel/includes/control/accounting-financial-report-data.php`
5. `control-panel/js/accounting-modals.js`
6. `control-panel/js/accounting-page.js`

## Post-Deployment Verification

### A) Security

1. Open accounting page and perform a normal POST action (create journal) -> should succeed.
2. Send a POST to `api/control/accounting.php` without CSRF token -> must return failure.
3. As country-scoped user, try viewing/editing/deleting journal IDs from another country -> must fail.

### B) Accounting Integrity

1. Create two journals quickly from two sessions:
   - References must be unique.
2. Approve/reject entries:
   - Approval status and journal status must move together.
3. Run financial books report:
   - Debit/Credit direction should match transaction type (not sign-only).

### C) Export Safety

1. Export registration revenue CSV.
2. Open in spreadsheet app.
3. Fields beginning with `=`, `+`, `-`, `@` should be prefixed and not execute formulas.

## Monitoring (First 24 Hours)

- Watch application logs for:
  - `Invalid CSRF token`
  - accounting API 4xx/5xx spikes
  - journal creation failures / reference reservation failures
- Query for duplicate references (should remain zero):

```sql
SELECT reference, COUNT(*) AS cnt
FROM control_journal_entries
WHERE reference IS NOT NULL AND TRIM(reference) <> ''
GROUP BY reference
HAVING COUNT(*) > 1;
```

## Rollback Plan

### Code Rollback

1. Redeploy previous known-good release.
2. Clear OPcache/restart PHP service if required.

### DB Rollback

Only if necessary and if you confirmed no duplicate refs:

```sql
USE outratib_control_panel_db;
ALTER TABLE control_journal_entries
  DROP INDEX uq_control_journal_entries_reference;
```

Note: app-level lock remains in code unless code is rolled back.

## Known Behavior Changes

- POST endpoints now require CSRF token.
- Country-restricted users cannot mutate out-of-scope journals/approvals even by ID.
- Journal reference generation may fail fast with a retry message if lock is unavailable.

## Support / Incident Quick Steps

1. Capture failing request endpoint and payload (without secrets).
2. Check server logs around timestamp.
3. Validate session + CSRF token presence.
4. Verify user country scope and target journal country.
5. Retry operation after confirming lock contention is transient.

