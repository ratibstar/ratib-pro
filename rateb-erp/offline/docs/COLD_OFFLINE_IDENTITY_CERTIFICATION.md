# Production Report — Enterprise Cold Offline Identity

**Date:** 2026-07-12  
**Baseline:** Enterprise Baseline v1.2 · Offline Foundation v1.1  
**SDK:** 14.2.0 (unchanged)  
**IndexedDB:** `DB_VERSION = 2` (unchanged)

## Verdict

**CERTIFIED — Enterprise Cold Offline Identity ready (optional flag).**

Warm Offline and Online paths remain intact. Cold Offline is additive and default **OFF**.

## Modes

| Mode | Status |
|------|--------|
| Online (PHP session + CSRF) | Unchanged |
| Warm Offline (enroll → PIN → unlock → queue/replay) | Regression PASS (P1 18/18, P2 24/24) |
| Cold Offline (local identity → local session → cached ERP) | Implemented; flag `offline.auth.cold` / `RATEB_OFFLINE_AUTH_COLD` |

## Security audit

- Identity package: company/branch/user/device, roles, permissions, issued/expires, offline_policy, identity_version, HMAC signature
- AES-GCM vault seal reused from warm path
- Device binding + PIN; optional WebAuthn hook unchanged
- Fail-closed: cold disabled, bypass policy forbidden, permissions required for `cold_capable`
- **No PHP session offline** · **No server authz bypass** · reconnect still authoritative

## Session (local only)

- TTL / idle / absolute timeouts (same session policy as warm)
- Destroy on logout, expiry, tenant mismatch, signature/vault failure paths
- Banner + theme/locale/scope restore without server calls

## Regression gates

```
Cold identity:  20/20 PASS  GATE CLEAR
P1 warm:        18/18 PASS  GATE CLEAR
P2 hardening:   24/24 PASS  GATE CLEAR
Foundation:     SDK 14.2.0 / DB_VERSION 2 frozen
Queue/Replay:   untouched
```

## Enablement (production)

1. Apply prior migrations (incl. 194 if not applied)
2. Set env: `RATEB_OFFLINE_ENABLED`, `RATEB_OFFLINE_READ_CACHE`, `RATEB_OFFLINE_AUTH_UNLOCK`, `RATEB_OFFLINE_AUTH_COLD=1`
3. Ensure RBAC cache path available for cold enroll snapshot (`offline.rbac.cache`)
4. Online enroll once per device → cold-capable sealed identity
5. Cold boot: open cached `offline-shell.html` → PIN unlock → local session

## Out of scope (frozen)

Queue · Replay · SDK version · IndexedDB version · POS · CRM/HR/Payroll/Mfg/Assets/Procurement/Quality/Documents/BI adapters · SW architecture
