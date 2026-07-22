# Phase J — Unified Mobile Device Registry (Implementation)

**Status:** IMPLEMENTATION COMPLETE — Device Registry foundation only  
**Date:** 20 Jul 2026  
**Scope:** ERP-owned `rateb_mobile_devices` + shared APIs + ESS Flutter adapter  
**Non-goals:** Push UI, notification business logic, Store release, Manager app scaffolding

---

## 1. Architecture

```
                    ┌─────────────────────────────────┐
                    │         RATEB ERP               │
                    │  Authentication Authority        │
                    │  ApiAuthMiddleware → TenantContext│
                    └───────────────┬─────────────────┘
                                    │
                    ┌───────────────▼─────────────────┐
                    │  MobileDeviceRegistryService    │
                    │  (single shared implementation) │
                    │  table: rateb_mobile_devices    │
                    └───────────────┬─────────────────┘
                                    │
              POST /api/v1/mobile/devices/{register|heartbeat|{id}/revoke}
                                    │
              ┌─────────────────────┼─────────────────────┐
              │                     │                     │
     ratib_hr_mobile        ratib_manager_mobile     Future apps
     client_app=ess         client_app=manager       workforce/
                                                     supervisor/ceo
```

| Binding | Rule |
|---------|------|
| Ownership | ERP only — Flutter is presentation + thin adapter |
| Auth | Bearer token → `TenantContext` company/user; never trust client IDs |
| Isolation | Not POS / `rateb_offline_devices` |
| Secrets | No passwords, hashes, JWTs, session cookies; `push_token` is delivery handle only |
| Unique identity | `(company_id, client_app, device_id)` |

---

## 2. Database

**Migration:** `rateb-erp/migrations/206_mobile_devices_registry.sql`

| Column | Notes |
|--------|-------|
| `id` | PK |
| `company_id` | Tenant FK |
| `user_id` | Authenticated user |
| `client_app` | `ess` · `manager` · `workforce` · `supervisor` · `ceo` |
| `platform` | `android` · `ios` · `other` |
| `device_id` | Stable install id (client-generated) |
| `push_token` | Optional FCM/APNs handle (omitted from API DTO) |
| `app_version` | Build string |
| `last_seen_at` | Heartbeat |
| `status` | `active` · `inactive` · `revoked` |
| `created_at` / `updated_at` | Audit |

Revocable: `revoke` sets `status=revoked` and clears `push_token`. Same owner may re-register to reactivate.

---

## 3. API contract

All routes require authenticated ERP API token.

### `POST /api/v1/mobile/devices/register`

**Body (client):**

```json
{
  "client_app": "ess",
  "device_id": "a1b2…",
  "platform": "android",
  "push_token": "optional",
  "app_version": "0.1.5"
}
```

**Server:** binds `company_id` / `user_id` from auth context only.

**Success `200`:**

```json
{
  "success": true,
  "data": {
    "device": {
      "id": 42,
      "client_app": "ess",
      "platform": "android",
      "device_id": "a1b2…",
      "app_version": "0.1.5",
      "status": "active",
      "last_seen_at": "2026-07-20 18:00:00"
    }
  }
}
```

DTO **excludes** `company_id`, `user_id`, `push_token`.

### `POST /api/v1/mobile/devices/heartbeat`

Same identity fields. Updates `last_seen_at` / optional token/version.  
If revoked → `403` `{ "code": "device_revoked" }`.

### `POST /api/v1/mobile/devices/{id}/revoke`

Owner-scoped revoke. Clears push token.

### Error codes

| HTTP | code | When |
|------|------|------|
| 422 | `validation_error` | Bad `client_app` / `device_id` |
| 403 | `forbidden` | Device owned by another user |
| 403 | `device_revoked` | Heartbeat on revoked device |
| 404 | `not_found` | Unknown device for this user/tenant |

---

## 4. Security model

1. **Authentication Authority** = Online ERP only.  
2. **Never trust** client-supplied `user_id` / `company_id`.  
3. **Tenant + user isolation** on every read/write.  
4. **No credential storage** in registry table or service.  
5. **`push_token`** is a delivery handle, rotatable, never returned in DTO.  
6. **No local device authority** in Flutter — ERP remains SoT for status.

---

## 5. Flutter ESS

| Piece | Role |
|-------|------|
| `DeviceRegistryPort` | Contract |
| `ErpDeviceRegistryAdapter` | HTTP → shared APIs (`client_app: ess`) |
| `LocalDeviceIdStore` | Stable install id in cache |
| `DeviceRegistryService` | Register + heartbeat after auth |
| `AuthSession` | Calls registry after login/restore; soft-fail network; hard-fail `device_revoked` |

Wired in `bootstrapEssCore` via `AppLocator.registerPhaseJ`.

---

## 6. Test results

### ERP

```
php rateb-erp/tests/hr/run-ess-phase-j-device-registry-tests.php
```

Coverage: migration, routes, client apps, DTO strip, validation, SQL scoping, controller auth IDs, revoke/duplicate paths.

**Result:** GATE CLEAR — 8/8 PASS (2026-07-20).

### Flutter

```
flutter test test/phase_j_device_registry_test.dart
```

Coverage: phase marker, adapter paths/`client_app`, stable device id, no client user/company IDs, register+heartbeat, revoked mapping, login integration, revoked login fail-closed.

**Result:** All 8 tests passed (2026-07-20).

---

## 7. Explicitly not in this phase

- Push notification UI / FCM business rules  
- Admin revoke console UI  
- Store / Play release  
- Manager Flutter app wiring beyond shared API readiness  
- Reuse of POS/offline device tables  
