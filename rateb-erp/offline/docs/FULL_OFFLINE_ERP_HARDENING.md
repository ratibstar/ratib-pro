# Full Offline ERP R/W — Hardening & E2E Checklist

**Date:** 2026-07-12  
**Scope:** Option 2 (same UI + draft create/edit + sync). Money / GL post / final approve / payroll calculate stay online-only.

## Regression gates (must stay green)

| Gate | Expectation | Where |
|------|-------------|--------|
| Admin navigate always `respondWith` | Offline admin HTML never shows Chrome native “لا يتوفر اتصال” | `public/pos-sw.js` fetch navigate + `clients.claim` on activate |
| POS ownership | `/pos` not stolen by ERP SW | `rateb-offline-sw.js` returns early on POS paths |
| Ops allowlist runtime | Paths loaded from `ops-page-allowlist.json`, not short hardcoded-only list | `pos-sw.js` `loadErpOpsAllowlist` |
| Ops cache match | Pathname + `ignoreSearch` + key scan (+ company_id preference) | `erpOpsPageFallback` / `opsPageFallback` |
| Writable ops strip | `data-rateb-offline-writable`; deny money/post/approve | `shell-adapter.js` `stripSensitiveOpsPage` |
| Queue badge | `N عمليات بانتظار المزامنة` | `erp-shell-bootstrap.js` |
| Logout offline | `destroyWarmSession` + return to `offline-shell.html` when offline | `auth-lock-adapter.js` |

## E2E checklist

1. Online: enroll PIN, open Admin once (SW install + CSS warm).
2. Online: visit PR / PO / inventory / HR attendance (captures ops HTML).
3. Offline: open last URL or unlock → same module UI (not generic “وضع عدم الاتصال” home for allowlisted paths).
4. Offline: create PR draft → queue badge increments.
5. Reconnect → flush → draft appears server-side.
6. Offline logout → warm session destroyed → unlock screen.
7. Attempt approve/post/pay offline → blocked with online-only message.

## Production flags (docs only — do not invent secrets)

Enable gradually in project-root `.env` (never commit secrets):

```env
RATEB_OFFLINE_ENABLED=1
RATEB_OFFLINE_READ_CACHE=1
RATEB_OFFLINE_PILOT_OPS_PAGES=1
RATEB_OFFLINE_AUTH_UNLOCK=1
RATEB_OFFLINE_RBAC_CACHE=1
RATEB_OFFLINE_MASTER_DATA=1
RATEB_OFFLINE_INVENTORY_MOVEMENTS=1
RATEB_OFFLINE_HR_ATTENDANCE=1
RATEB_OFFLINE_PROCUREMENT=1
```

Keep OFF unless certified: payroll calculate/post, accounting journal post, approval final decide, payment movements.

## Cache / quota notes

- Ops HTML lives in `rateb-erp-ops-pages-v14` (Cache API) + IndexedDB snapshots.
- Shell CSS/JS warmed into `rateb-erp-coexist-v6`.
- Eviction: activate migrates then deletes stale `rateb-erp-coexist-*` / `rateb-erp-ops-pages-*` / allowlist caches.
- Canonical URLs come from `rateb_app_route()` only (see `ops-page-allowlist.json` → `routes`). Never assume `/admin/ops/{path}` for every entry (e.g. `hr/attendance` → `admin/hr/attendance`).
- Warm/prefetch skips HTTP ≠ 200 and logs `INVALID ROUTE`.
- First offline hit still requires an online visit per allowlisted path (or idle prefetch of sidebar/canonical links).

## Automated smoke

```bash
php rateb-erp/offline/tests/ErpOfflinePhase14PilotTest.php
php rateb-erp/offline/scripts/verify-offline-activation.php
```

See also: `OFFLINE_ACTIVATION_BLOCKERS.md`, `PHASE_14_ENTERPRISE_DAILY_OPS_PILOT.md`, `PHASE_8_PRODUCTION_ROLLOUT.md`.
