# CRM Production Runbook (Phase 10)

## Pre-deploy checklist

1. Confirm migrations **231–238** applied on production.
2. Apply **239** (`239_crm_phase10_production_hardening.sql`) during low traffic (index creates).
3. Deploy code (`main` Actions green).
4. Bust OPcache if used (`erp-opcache-bust.php`).
5. Smoke: `/crm/revops`, `/crm/search`, `/crm/customers/{id}`, `/crm/pipeline`, `/crm/insights`.

## Post-deploy smoke

```bash
php rateb-erp/tests/run-crm-phase10-tests.php
php rateb-erp/tools/audit-route-controller-imports.php
```

## Safe operations

- Quality scan: POST `/crm/data-quality/scan` or governance scan (not automatic on GET).
- RevOps automation: requires `crm.revops.run` (not view).
- Insights dismiss: requires `crm.insights.manage`.
- Customer 360 refresh writes: add `?refresh=1` only when needed.
- Duplicate merge: request → review → execute; audit logged.

## Rollback / safety

- **Code rollback:** redeploy previous commit; no DROP required.
- **Migration 239:** indexes/permissions are additive; leave in place if rolling back code.
- **Do not** reverse-delete CRM rows or DROP tables.
- Quote→Invoice remains disabled; Accounting untouched.

## Incident tips

| Symptom | Check |
|---------|--------|
| Notification storm | `automation_safety` cooldown; automation_log `run_lock:*` |
| Slow RevOps | Ensure quality `source=snapshot`; re-run scan offline |
| Export blocked | `export_policy` + `crm.export.manage` |
| Merge failed mid-way | audit `crm.merge.execute`; re-request after fix |
