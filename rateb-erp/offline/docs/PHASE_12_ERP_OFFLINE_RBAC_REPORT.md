# Phase 12 — ERP Offline RBAC & Navigation Cache

**Flags:** `offline.enabled` + `offline.read_cache` + `offline.auth.unlock` + `offline.rbac.cache` (all default OFF)

## Model

- UI cache only (permission slugs + plan modules + filtered nav)
- Snapshot kind `erp_rbac` in `rateb_erp_offline` / `snapshots`
- TTL (`expires_at`) + deterministic `rbac_version` (roles, perms, plan modules, branch access)
- Online: version mismatch → delete + refresh
- Fail-closed: expired / mismatch / tenant / inactive device / super-admin / missing

## Non-changes

- Phase 10 shell-adapter strip / SW recursion
- Phase 11 auth vault
- LoginController, Auth, ErpAuthMiddleware
- POS, queue domain, replay engine
