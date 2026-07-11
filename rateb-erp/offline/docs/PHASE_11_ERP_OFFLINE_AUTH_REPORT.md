# Phase 11 — ERP Offline Authentication (T3)

**Date:** 2026-07-11  
**Scope:** Local unlock of Phase 10 cached ERP shell  
**Flags:** `offline.enabled` + `offline.read_cache` + `offline.auth.unlock` (all default OFF)

## Model

- Unlock = local shell overlay only  
- Never creates PHP session / password / 2FA / remember-me offline  
- Super-admin denied  
- Device must be ACTIVE (fail-closed)  
- Vault in `rateb_erp_offline` → store `auth_vault` (DB_VERSION 2)

## Explicit non-changes

- LoginController, Auth, ErpAuthMiddleware  
- POS auth (`rateb_pos_auth_lock`)  
- Queue/replay Tier-1 domain logic (flush gate only for reauth)
