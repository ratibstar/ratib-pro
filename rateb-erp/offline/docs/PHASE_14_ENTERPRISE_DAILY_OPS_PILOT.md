# Phase 14 — Enterprise Daily Ops Offline Pilot

**Date:** 2026-07-11  
**Flags (all default OFF):** `offline.enabled`, `offline.read_cache`, `offline.pilot.ops_pages`, module write flags, optional unlock / RBAC / master_data  
**Scope:** Daily ops continuity (POS + Inv + HR drafts + Proc drafts + allowlisted page browse) on frozen Phases 10–13.1  
**Out of scope:** Accounting / GL, payroll, payments / N-Genius, approvals workflows, live dashboards/stats, ReplayEngine / Auth redesign

## Pilot matrix

| Area | Offline allowlist | Actions | Notes |
|------|-------------------|---------|-------|
| POS register | Existing T0 / `pos-sw.js` | Sale / checkout queue | Separate stack; SW coexist required |
| Inventory | `stock-movements`, `warehouse-transfers`, `inventory-audits`, browse `inventory` / `warehouses` | `stock_movement.create`, `warehouse_transfer.create`, `stock_count.create` | Via `RatebOfflineInventoryAdapter` hooks |
| HR | `hr/attendance`, `hr/attendance/bulk`, `hr/leaves` | `attendance.create`, `attendance.bulk`, `leave_request.draft` | Drafts / records only — no payroll |
| Procurement | `purchase-requests`, `rfq`, `purchase-orders` (create/draft) | `*.draft` only | No approvals / payments |
| Shell / RBAC | Chrome snapshot + nav catalog | Browse | Phases 10–12 |
| Master data | Directories in `entity_cache` | Read / pickers | Phase 13; surface `page_limit_reached` / `migration_required` |
| Ops pages | Allowlist in `offline/config/ops-page-allowlist.php` | Snapshot browse when offline | Flag `offline.pilot.ops_pages` |

### Explicit exclusions (stay online-only)

- Accounting / journal posting / GL
- Payroll runs and salary posting
- Payments / N-Genius / gateways
- Approval / workflow decisions
- Super-admin / cross-tenant screens
- Live reports and fresh KPI widgets
- Non-allowlisted admin routes (SW falls back to `offline-shell.html`)

### Conflict policy

- Server `OfflineReplayEngine` + existing module replay services remain authoritative
- Idempotency keys unchanged; rejected / conflicted queue items are **not** cleared client-side
- Device must be ACTIVE; tenant / branch guards on push (Phase 7.1+) unchanged
- Accounting / payroll / payment endpoints remain rejected by offline routing

## Flag enablement order (staging → pilot)

All flags remain **OFF** in production until soak sign-off.

1. `RATEB_OFFLINE_ENABLED=1`
2. `RATEB_OFFLINE_READ_CACHE=1`  
   Optional next: `RATEB_OFFLINE_AUTH_UNLOCK=1` → `RATEB_OFFLINE_RBAC_CACHE=1` → `RATEB_OFFLINE_MASTER_DATA=1`
3. `RATEB_OFFLINE_PILOT_OPS_PAGES=1` (allowlisted page snapshots + SW serve)
4. Write modules one at a time (first verticals for full offline R/W):  
   `RATEB_OFFLINE_INVENTORY_MOVEMENTS=1` → then `RATEB_OFFLINE_HR_ATTENDANCE=1` → then `RATEB_OFFLINE_PROCUREMENT=1`  
   Optional later: remaining Tier-1 module env keys documented in `PHASE_8_PRODUCTION_ROLLOUT.md` / module PHASE_*B docs — enable only after Inv/HR/Proc are green.  
   **Do not** invent production `.env` secrets in git; set flags on the server / local `.env` only.
5. POS offline remains its own stack; after each step verify `pos-sw.js` still owns `/pos` and ERP fallback coexist works

### Recommended first-vertical block (docs template)

```env
RATEB_OFFLINE_ENABLED=1
RATEB_OFFLINE_READ_CACHE=1
RATEB_OFFLINE_PILOT_OPS_PAGES=1
RATEB_OFFLINE_INVENTORY_MOVEMENTS=1
RATEB_OFFLINE_HR_ATTENDANCE=1
RATEB_OFFLINE_PROCUREMENT=1
```

Hardening checklist: `offline/docs/FULL_OFFLINE_ERP_HARDENING.md`.

## Delivered (Phase 14)

| Item | Location |
|------|----------|
| Pilot matrix + runbook | `offline/docs/PHASE_14_ENTERPRISE_DAILY_OPS_PILOT.md` |
| Ops page allowlist | `offline/config/ops-page-allowlist.php` |
| Flag `offline.pilot.ops_pages` | `offline/config/feature-flags.php`, `OfflineFeatureFlagService` |
| Inv / HR / Proc form hooks | `offline/client/adapters/ops-forms-adapter.js`, `public/assets/offline/erp-ops-forms-bootstrap.js` |
| Ops page capture + strip | `shell-adapter.js` (`captureOpsPage`, `stripSensitiveOpsPage`) |
| SW / POS coexist serve | `public/rateb-offline-sw.js`, `public/pos-sw.js` |
| Master-data pickers | `master-data-adapter.js` `listCached` / `pickerOptions` |
| Queue cap + sync badge | `queue-manager.js` `client_queue_max`, shell bootstrap badge |
| Smoke tests | `offline/tests/ErpOfflinePhase14PilotTest.php` |

## Staging / pilot soak runbook

### Pre-flight

- [ ] Migrations through Phase 13.1 applied (incl. warehouses `updated_at` if master_data on)
- [ ] Device enrolled ACTIVE for pilot company / branch
- [ ] No accounting / payroll / payment offline flags exist (none should)
- [ ] Confirm `.env` has no pilot flags until this runbook reaches enable steps
- [ ] Run gate suites (see below) — all CLEAR

### Soak steps (one company, limited users)

1. Enable master + `read_cache` only. Online: open Admin shell once; confirm chrome snapshot + SW register (or POS coexist warm). Offline: open Admin → shell / offline-shell, badge **غير متصل**.
2. Enable unlock / RBAC / master_data as needed. Confirm directory pull; if `migration_required` or `page_limit_reached`, stop and fix before write flags.
3. Enable `pilot.ops_pages`. Visit allowlisted Inv / HR / Proc pages online; go offline; navigate same paths → cached HTML (not live stats). Non-allowlisted → shell fallback.
4. Enable inventory movements. Offline: submit stock movement / transfer → queued. Online: flush; verify tenant scope.
5. Enable HR attendance. Offline: attendance + leave draft → queue. Confirm payroll screens still online-only.
6. Enable procurement. Offline: PR / RFQ / PO **draft** only. Confirm approvals / payments blocked.
7. POS sale offline with coexist SW still owning `/pos`.
8. Disable all `RATEB_OFFLINE_*` → production behavior unchanged.

### Sign-off criteria

- Offline: shell + allowlisted ops browse + enqueue Inv / HR / Proc draft + POS sale
- Online: flush succeeds; no cross-tenant bleed; accounting endpoints still rejected
- Queue depth respects `client_queue_max` (500); sync badge visible when offline SDK active
- Flags OFF: no layout injection / no SW ownership change for ERP-only installs

### Test commands

```bash
php offline/tests/run-offline-foundation-tests.php
php offline/tests/run-erp-shell-offline-tests.php
php offline/tests/run-erp-offline-auth-tests.php
php offline/tests/run-erp-offline-rbac-tests.php
php offline/tests/run-erp-offline-master-data-tests.php
php offline/tests/run-erp-offline-phase131-tests.php
php offline/tests/run-erp-offline-phase14-tests.php
php offline/tests/run-inventory-offline-tests.php
php offline/tests/run-hr-offline-tests.php
php offline/tests/run-procurement-offline-tests.php
```

## Safety / non-changes

- No redesign of `OfflineReplayEngine`, `LoginController`, Auth, `ErpAuthMiddleware`, POS business logic, or Tier-0 architecture
- `Designed/` untouched
- Feature flags default OFF
- POS SW remains authoritative for `/pos`; ERP SW never `clients.claim`
