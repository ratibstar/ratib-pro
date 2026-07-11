# Phase — Enterprise Cold Offline Identity

Additive cold identity on top of warm identity (P1/P2). No Foundation / SDK / IDB redesign.

## Modes

1. **Online** — PHP session + CSRF (unchanged)
2. **Warm Offline** — online login → enroll → PIN → unlock → queue/replay (unchanged when `offline.auth.cold` OFF)
3. **Cold Offline** (optional) — no internet → local PIN/biometric unlock → **local session only** → cached ERP → queue → reconnect → replay

## Adds

### Server

- `offline.auth.cold` flag (default OFF, env `RATEB_OFFLINE_AUTH_COLD`)
- `OfflineIdentitySnapshot` — session RBAC snapshot for sealed claims
- `OfflineColdIdentityService` — issue/renew/validate cold packages (`cold_capable`)
- `OfflineIdentityValidator` — fail-closed cold checks (permissions list; `server_authz_bypass` must be false)
- `OfflineDeviceIdentityService` — device trust helpers
- `OfflineLocalSessionService` — local-session **policy only** (never creates PHP sessions)
- `OfflineBootstrapManager` — cold boot config for `offline-shell.html`
- Audit events: `cold_unlock_success`, `cold_unlock_failed`, `cold_session_destroyed`
- Enroll/renew use cold issue when flag ON; warm-only when OFF
- Auth `/policy` exposes `cold_identity` + `cold_boot` when enabled

### Client

- `offline-local-session-adapter.js` → `RatebOfflineLocalSession` (sessionStorage; TTL/idle/absolute; destroy on logout)
- `offline-cold-bootstrap-adapter.js` → `RatebOfflineBootstrapManager` (restore theme/locale/scope/banner/RBAC nav after unlock)
- Bundled into `rateb-offline.js` (SDK banner remains **14.2.0**)
- Unlock detail includes `cold: true` when claims are cold-capable
- Logout wipe also destroys local session

## Security

- HMAC identity package + AES-GCM vault seal (existing warm path)
- Device binding + PIN unlock; optional WebAuthn hook (existing)
- Expiry / tenant / branch / clock skew / anti-rollback (existing verify)
- Cold claims: permissions, roles, offline_policy, identity_version, signature
- **No PHP session offline. No fake server auth. Server authz remains authoritative online.**

## Requirements for cold enroll

- `offline.enabled` + `offline.read_cache` + `offline.auth.unlock` + `offline.auth.cold`
- Online session with RBAC manifest available (`offline.rbac.cache` path) — fail-closed if snapshot denied

## Frozen

- SDK version string **14.2.0**
- IndexedDB `DB_VERSION = 2`
- ReplayEngine / Queue / Conflict / POS / Tier-1 module adapters
- Service Worker architecture

## Tests

```bash
php offline/tests/run-offline-cold-identity-tests.php
php offline/tests/run-erp-offline-identity-tests.php
php offline/tests/run-erp-offline-identity-p2-tests.php
```
