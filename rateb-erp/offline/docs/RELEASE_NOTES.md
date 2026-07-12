# RATEB ERP Offline — Release Notes

**Release:** `erp-offline-v1.0.0`  
**Date:** 2026-07-12  
**Environment:** Production — https://rateb.sa  
**Code commit (feature):** `2c6b0c3275b5a675210d595cabcef987714419a5`  
**Closure commit:** (this release documentation commit)

---

## Features delivered

- Full ERP Offline warm identity (PIN unlock, device trust, keep_vault logout policy)
- Canonical allowlist routing via `rateb_app_route()` / `ops_page_routes` (no hardcoded `/admin/ops/` reconstruction)
- Ops page warm/prefetch/capture with HTTP ≠ 200 skipped as `INVALID ROUTE`
- Offline same-UI for allowlisted modules (procurement, inventory, HR attendance)
- Offline write queue (IndexedDB `sync_queue`) with server replay for:
  - Purchase request drafts (+ line normalization)
  - Stock movements
  - Attendance create
- Queue flush auto-injects `device_id` / branch scope for Device Guard
- Service Worker coexistence: `pos-sw.js` owns ERP ops page + allowlist caches (`v14`)
- SDK **14.2.0**, IndexedDB **`rateb_erp_offline` DB_VERSION = 2**

---

## Bugs fixed

| Issue | Fix |
|-------|-----|
| Warm/validation assumed every path under `/admin/ops/` (HR 404) | Resolve via `rateb_app_route()`; emit `routes` map in allowlist JSON v2 |
| Non-200 pages falsely treated as cached | Skip capture; log `INVALID ROUTE` |
| Sync flush sent empty `device_id` → `Device not allowed` | `resolveFlushDeviceId()` from AuthLock / localStorage |
| Logout wiped PIN vault → no PIN after logout | Default `logout_vault_policy=keep_vault`; honor policy in `destroyWarmSession` |
| PR replay failed on `priority=normal` (DB enum) | Map to `medium` / valid enum values |
| PR line sync failed (`description`/`qty` vs `item_name`/`quantity`) | `normalizePurchaseRequestLines()` in procurement replay |

---

## Production validation summary

Evidence: `C:\Users\Public\pw-rateb-validate\evidence2\` (`report.json`, `14e-mysql.json`, screenshots).

| Metric | Result |
|--------|--------|
| Allowlist pages | 142 |
| HTTP 200 | 69 |
| HTTP 404 | 2 |
| Invalid / non-200 (not cached) | 73 |
| Cache Storage keys | 143 |
| Required cache hits (PR / inventory / attendance) | PASS |
| Certification steps 1–18 | **PASS 18/18** |
| MySQL PR draft | `rateb_purchase_requests` id=16 + line item |
| MySQL stock movement | `rateb_stock_movements` id=65 |
| MySQL attendance | `rateb_attendance_records` id=16 |
| Logout + PIN unlock UI | PASS (`keep_vault`) |

---

## Known non-blocking limitations

1. **Allowlist hygiene:** ~73 logical paths return HTTP 500/other online (e.g. mfg/hrm enterprise stubs). Correctly excluded from cache; not a false-positive cache risk.
2. **2× HTTP 404** in allowlist (`goods-receipts`, opening-balances style paths) — logged `INVALID ROUTE`.
3. **Attendance same-day replay** may surface as conflict (`server_newer`) when replaying twice for the same employee/date; row still present in MySQL.
4. **Tenant fixtures:** Company 22 required seed inventory/employee rows for inv/attendance replay proof (`PROD-VAL-INV` / `PROD-VAL-EMP`).
5. **SDK adapter surface:** Module adapters are accessed as `RatebOffline.procurement()` (function getters), not bare objects.
6. **POS isolation:** POS Offline (`rateb_pos_offline`) remains separate; this release does not change POS checkout contracts.

---

## Maintenance notes

- Rebuild client bundle: `php rateb-erp/offline/scripts/build-rateb-offline-bundle.php`
- Regenerate allowlist routes: same script (calls `sync-ops-page-allowlist.php`)
- Opt into vault wipe on logout: `RATEB_OFFLINE_AUTH_LOGOUT_VAULT=clear_vault`
- Rollback: see `DEPLOYMENT_MANIFEST` / Enterprise Release Certificate
