# Phase J — Unified Mobile Device Registry (Design Only)

**Status:** DESIGN COMPLETE — implementation shipped in [PHASE_J_IMPLEMENTATION.md](PHASE_J_IMPLEMENTATION.md)  
**Date:** 20 Jul 2026  
**Scope:** Architecture charter for a shared ERP Mobile Device Registry  
**Consumers (planned):** `ratib_hr_mobile` · `ratib_manager_mobile` · future Workforce / Supervisor / CEO apps

---

## 1. Binding architecture

```
RATEB ERP
(Source of Truth + Authentication Authority)
        │
Mobile Device Registry Service
(ERP-owned — single implementation)
        │
        ├──────────────────┬──────────────────┐
        │                  │                  │
 ratib_hr_mobile   ratib_manager_mobile   Future apps
 (ESS)             (Manager)              (Workforce /
                                           Supervisor / CEO)
```

| Rule | Binding |
|------|---------|
| Source of Truth | RATEB ERP only |
| Authentication Authority | Online ERP only (Bearer / existing `ApiAuthMiddleware`) |
| Registry ownership | ERP service + table(s) — never owned by a Flutter app |
| Flutter role | Presentation + thin adapter — register / heartbeat / unregister only |
| Identity boundary | Registry MUST NOT store passwords, hashes, session cookies, JWTs, TOTP, WebAuthn secrets, or API tokens as credentials |
| One registry | All mobile client apps share the same service; differentiate by `client_app` / `workspace_role` |
| Isolation | Do not modify `rateb_mobile`, Capacitor, Tracking, or POS for this charter |

---

## 2. Problem statement

Today device concepts are fragmented:

| Existing | Purpose | Not suitable as mobile push registry |
|----------|---------|--------------------------------------|
| `rateb_offline_devices` | Offline / POS appliance trust | Branch/offline lifecycle; not FCM/APNs multi-app |
| QR `user_trusted_devices` | Browser/QR trusted device cookies | Web session trust, not app installs |
| Branch `device_uuid` | Appliance identity | Hardware appliance, not employee phones |

ESS and future Manager need **one** ERP API to:

1. Bind an install to `company_id` + authenticated `user_id` (+ resolved employee when ESS)
2. Store **push delivery handles** (FCM / APNs) for notifications
3. Revoke / rotate devices from Admin without touching HR business modules
4. Serve multiple client apps without duplicating tables per app

---

## 3. Target domain model (logical)

**Proposed physical name (implementation later):** `rateb_mobile_devices`

| Field (logical) | Notes |
|-----------------|-------|
| `id` | Surrogate PK |
| `company_id` | Tenant — mandatory |
| `user_id` | Authenticated ERP user — mandatory |
| `employee_id` | Optional; set only when ESS resolver binds an employee (server-side) |
| `client_app` | Enum/string: `ess` · `manager` · `workforce` · `supervisor` · `ceo` |
| `device_uuid` | Client-generated stable install id (UUID) — unique per `(company_id, client_app, device_uuid)` |
| `platform` | `android` · `ios` · `other` |
| `push_provider` | `fcm` · `apns` · `none` |
| `push_token` | Delivery token only — **not** an auth secret; rotatable |
| `app_version` | Client build string |
| `locale` | Optional UI locale hint for localized push |
| `status` | `active` · `inactive` · `revoked` |
| `last_seen_at` | Heartbeat |
| `created_at` / `updated_at` | Audit |

**Explicitly excluded from this table**

- Passwords / password hashes  
- Session cookies / Bearer tokens / refresh tokens  
- TOTP / WebAuthn credentials  
- Payroll, leave, attendance payloads  

Push token is a **delivery address**, not an authentication credential. Auth remains ERP token issuance (`POST /api/v1/auth/token`).

---

## 4. Relationship to existing registries

```
rateb_offline_devices     → POS / Offline Runtime (unchanged)
user_trusted_devices      → QR / web trust (unchanged)
rateb_mobile_devices      → NEW — phone apps (ESS / Manager / …)
```

**Do not merge** offline POS devices into the mobile push registry.  
**Do not** have ESS invent a second registry.  
Optional later: Admin UI “Devices” hub that **lists** both families with clear type labels — still two underlying stores.

---

## 5. API charter (thin adapters — implement later)

Base path (proposed): `/api/v1/mobile/devices`  
Middleware: existing `ApiAuthMiddleware`  
Tenant: `TenantContext::companyId()` / `apiUserId()` only — **never** trust client `company_id` / `user_id` / `employee_id`.

| Method | Path | Purpose |
|--------|------|---------|
| `POST` | `/api/v1/mobile/devices/register` | Upsert by `(company, client_app, device_uuid)` |
| `POST` | `/api/v1/mobile/devices/heartbeat` | Touch `last_seen_at`; optional token refresh |
| `POST` | `/api/v1/mobile/devices/push-token` | Update FCM/APNs token only |
| `POST` | `/api/v1/mobile/devices/unregister` | Mark `revoked` / inactive for this install |
| `GET` | `/api/v1/mobile/devices/me` | List **current user’s** devices (DTO only) |

### Register request (client may send)

```json
{
  "client_app": "ess",
  "device_uuid": "…",
  "platform": "android",
  "push_provider": "fcm",
  "push_token": "…",
  "app_version": "0.1.5+107",
  "locale": "ar"
}
```

### Register response DTO

```json
{
  "success": true,
  "data": {
    "device": {
      "id": 1,
      "client_app": "ess",
      "device_uuid": "…",
      "platform": "android",
      "status": "active",
      "last_seen_at": "…"
    }
  }
}
```

Errors: `401` unauthorized · `403` forbidden · `422` validation_error · `404` not_found (unregister unknown).

Admin revoke (separate, later): Admin Mobile Apps / Security screen calling internal service — not exposed as a free-form mobile API.

---

## 6. Flutter contracts (design)

Shared idea (copy into each app’s `core/contracts` when implementing — **no shared Flutter package required in J design**):

```
DeviceRegistryPort
  register(Map payload)     // no user_id / company_id / employee_id
  heartbeat()
  updatePushToken(String)
  unregister()
  listMine()
```

| App | When to call |
|-----|----------------|
| `ratib_hr_mobile` | After successful auth + MobileConfig load; on token refresh; on logout → unregister or deactivate |
| `ratib_manager_mobile` | Same pattern; `client_app: manager` |
| Future apps | Same port; different `client_app` |

Feature flag (optional): `features.push` / `features.device_registry` via MobileConfig — default off until push provider configured.

---

## 7. Push delivery (out of band, same registry)

Registry stores tokens. **Notification send** remains ERP Notification service:

1. Business event → ERP creates `rateb_notifications` (existing)  
2. Push worker resolves active `rateb_mobile_devices` for `user_id` (+ `client_app` filter)  
3. Sends via FCM/APNs  
4. On permanent failure → mark token stale / device inactive  

ESS in-app notification list (Phase C) stays independent; push is an additional channel.

---

## 8. Security & compliance checklist

| Check | Requirement |
|-------|-------------|
| Tenant isolation | All queries `company_id = :cid` from auth context |
| User isolation | Register/list scoped to `apiUserId`; no IDOR on another user’s devices |
| Employee isolation | `employee_id` set only via `HrEssEmployeeResolverService` when `client_app = ess` |
| Mass assignment | Whitelist body keys; strip `user_id`, `company_id`, `employee_id`, `status` from client |
| Token hygiene | Rotate push tokens; never log full tokens in production |
| Auth boundary | No credential storage; Identity module rules unchanged |
| RBAC | Mobile self-service endpoints: authenticated user only; Admin revoke: `mobile_apps.manage` or dedicated permission |

---

## 9. Explicit non-goals (this design phase)

- No schema migration committed  
- No routes / controllers / Flutter adapters  
- No FCM/APNs provider keys in repo  
- No changes to POS offline device registry  
- No Manager app scaffolding  
- No merge of ESS and Manager into one Flutter tree  
- No offline sync of the device registry (online register/heartbeat only)

---

## 10. Implementation phase gate (when approved)

Suggested order after design approval:

1. Migration `rateb_mobile_devices` + indexes  
2. `MobileDeviceRegistryService` + thin `Api` controller + routes  
3. Admin revoke surface (minimal)  
4. `ratib_hr_mobile`: `DeviceRegistryPort` + adapter + post-login hook  
5. Push sender worker (can follow)  
6. `ratib_manager_mobile` consumes same API when that app exists  

**Stop condition for “Phase J implementation”:** ESS can register a device and Admin can revoke it; push send may be a follow-on milestone.

---

## 11. Roadmap placement

| Item | Status |
|------|--------|
| Phase J **design** (this document) | **COMPLETE** |
| Phase J **implementation** | Blocked until explicit “implement Phase J” approval |
| ESS roadmap phases G/H/I | Unrelated; may proceed independently |
| Manager Mobile | Consumer of this registry; not blocking ESS G–I |

---

## 12. Decision summary

| Decision | Choice |
|----------|--------|
| One shared ERP registry for phone apps? | **Yes** |
| Reuse `rateb_offline_devices`? | **No** — parallel domain |
| Auth credentials in registry? | **Never** |
| Client apps differentiated by? | `client_app` |
| Who resolves employee? | ERP resolver for ESS only |
| Design vs code in this phase? | **Design only** |
