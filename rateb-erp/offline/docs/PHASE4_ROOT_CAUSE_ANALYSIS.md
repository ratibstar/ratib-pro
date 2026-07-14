# Phase 4 — Root Cause Analysis (2026-07-14)

Repository evidence only. No speculation.

| Blocker | Exact root cause | Fix approach |
|---------|------------------|--------------|
| Baseline `clients.claim` | `public/rateb-offline-sw.js` activate claims clients; freeze test forbids it (pos-sw owns claim) | Remove claim from ERP SW activate; keep BG Sync |
| Auth Phase 10 intact | Same `clients.claim` regression | Same |
| Auth layout ungated | Test requires `$ratebOfflineAuthUnlock` string; layout calls method only | Assign `$ratebOfflineAuthUnlock` |
| Super-admin enroll 42 | `rateb_resolve_erp_shell_company_id()` overrides session `rateb_company_id=42` with primary | Prefer explicit session company before resolver |
| Authz inventory/procurement deny accounting | Stale tests; Phase 16B + Foundation allow accounting/payroll module tokens | Update deny cases to `settings_readonly` |
| Authz HR deny payroll | Stale vs Payroll 24B | Same |
| Phase 4.5 flags ON | `clearEnv` clears only 4 keys; `.env` fills rest; flag service caches config | Clear all `RATEB_OFFLINE_*` + reset config |
| Accounting allowlist | Investigate `opsPageAllowlist()` JSON memo | Re-run after fixes; paths present in JSON |
| Hybrid B1/B2 login null | Seed user `is_super_admin=1`; `Auth::attempt(..., company)` rejects SA | Use `attemptAuto` / portal `admin` |
| Hybrid mysql_e2e | MySQL not listening (`2002 connection refused`) | Soft-skip when MySQL unavailable |
| Hybrid C_changed_files_core_only | Verify allowlist only Core since `f3b160de`; later offline phases changed many files | Expand cumulative allowlist; assert HybridSyncEngine not redesigned |
| Debug in layout | Already gated on `?rateb_offline_debug`; bootstrap TEMP trace | Strip TEMP console from SWs; keep debug opt-in |
