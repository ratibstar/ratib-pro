# Phase I Push — Foundation (I.0 + I.1)

**Status:** I.0 + I.1 COMPLETE (ERP only)  
**Date:** 20 Jul 2026  
**ADR:** [ADR-PUSH-1-MOBILE-PUSH-FOUNDATION.md](../../rateb-erp/offline-v2/docs/ADR-PUSH-1-MOBILE-PUSH-FOUNDATION.md)

---

## Naming resolution (I.0)

| Track | Meaning |
|-------|---------|
| **Phase I Push** | Mobile push foundation (this doc): registry token APIs → later delivery |
| **Approvals** | Separate optional ESS roadmap item — **not** the same as Push |

Historical note: `PHASE_C.md` once listed “Push … (Phase I)”. Prefer this doc + ADR-PUSH-1. Phase **J** remains Device Registry (shipped).

---

## Architecture

```
Online ERP (SoT + Auth Authority)
        │
        ├─ NotificationService → rateb_notifications (in-app pull — existing)
        │
        └─ Mobile Device Registry (Phase J + I.1)
               rateb_mobile_devices
               register / heartbeat / push-token / revoke
                        │
           ┌────────────┴────────────┐
           │                         │
    client_app=ess            client_app=manager
    (Flutter later)           (shared APIs)
```

**I.1 does not send push.** `findActivePushDevices` is for the future worker (I.2).

---

## Ownership & boundaries

- Device Registry: ERP-owned, shared APIs.
- Flutter: no notification business logic (no Flutter changes in I.1).
- No `rateb_offline_devices` / POS tables.
- No passwords / JWT / session secrets in registry.
- `push_token` = delivery handle; never returned in API DTO.

---

## I.1 changes

### Migration `207_mobile_devices_push_foundation.sql`

Additive columns on `rateb_mobile_devices`:

- `push_provider` VARCHAR(16) NOT NULL DEFAULT `none` (`none`|`fcm`|`apns`)
- `locale` VARCHAR(16) NULL

Unique key `(company_id, client_app, device_id)` unchanged.

### Register fix

If `push_token` is absent or empty on register/heartbeat → **preserve** existing token (never overwrite with NULL).

### API

`POST /api/v1/mobile/devices/push-token`

Body (example):

```json
{
  "client_app": "ess",
  "device_id": "…",
  "push_token": "…",
  "push_provider": "fcm",
  "locale": "ar"
}
```

Auth: Bearer only → `TenantContext` user/company. Body `user_id` / `company_id` ignored.

Response device DTO includes `push_provider` / `locale`; **never** `push_token`.

Revoked device → `403 device_revoked`.

### Services

- `MobileDeviceRegistryService` — register / heartbeat / revoke / updatePushToken
- `MobileDeviceService` — `findActivePushDevices`, token update, revoke helpers
- `MobileDeviceDbStore` — SQL (tenant + user scoped)

---

## Explicitly not in I.1

Firebase · APNs · Flutter · Push worker · Admin UI

---

## Tests

```
php rateb-erp/tests/hr/run-ess-phase-i1-push-foundation-tests.php
php rateb-erp/tests/hr/run-ess-phase-j-device-registry-tests.php
```

Coverage: token preserve, tenant/user isolation, revoked block, client_app validation, token absent from responses, migration additive.

**Result (2026-07-20):** I.1 GATE CLEAR 9/9 · Phase J GATE CLEAR 8/8.
