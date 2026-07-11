# Phase P1 — Enterprise Offline Warm Identity

## Goal

Warm path only (not cold login):

Online login → device + signed identity enroll → PIN seal → browser close → offline reopen → PIN unlock → cached ERP shell + RBAC → queue → reconnect → authenticated replay → logout wipe.

## Non-goals

- No fake PHP sessions
- No Auth/RBAC/Queue/Replay/SDK redesign
- No IndexedDB `DB_VERSION` bump (remains 2)
- No SDK version bump (remains 14.2.0)
- No cold offline password login

## Security model

1. Server issues HMAC-signed identity claims (`ErpOfflineIdentityService`) under live session.
2. Client seals package with AES-GCM key derived from PIN (PBKDF2).
3. PIN unlock decrypts + verifies signature/expiry/tenant/branch/device locally.
4. Replay/push still require `ApiAuthMiddleware` online session.
5. Logout calls `destroyWarmSession()` wiping vault, RBAC, shell chrome, device meta, persisted scope.

## Key files

- `offline/server/Services/ErpOfflineIdentityService.php`
- `offline/server/Services/ErpOfflineIdentityEnrollService.php`
- `POST /api/v1/offline/auth/identity/enroll`
- `offline/client/adapters/auth-lock-adapter.js`
- `public/assets/offline/erp-offline-shell-auth.js`
- `public/offline-shell.html`

## Flags

Requires `offline.enabled` + `offline.read_cache` + `offline.auth.unlock` (+ `offline.rbac.cache` for nav restore).

Optional: `RATEB_OFFLINE_IDENTITY_SECRET`, `RATEB_OFFLINE_IDENTITY_TTL_SECONDS`.
