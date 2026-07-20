# ADR-PUSH-1 — Unified Mobile Push Foundation (ERP-owned)

**Status:** ACCEPTED  
**Date:** 2026-07-20  
**Phases:** I.0 (this ADR) · I.1 (registry/token APIs) · **I.2 (outbox + delivery engine — stubs)** · I.3+ (Flutter token wiring / real FCM SDK)  
**Related:** Phase J Device Registry · AF-2.1 Identity / Authentication Authority · ADR-AL-1 Single ERP Frontend

---

## Context

ESS and future Manager (and other) mobile apps need push delivery. In-app notifications already exist via `NotificationService` + `rateb_notifications`. Phase J shipped `rateb_mobile_devices` but no FCM/APNs send path.

Roadmap naming conflict: `ROADMAP.md` Phase **I** previously meant Approvals; older notes called push “Phase I”.

## Decision

1. **Push Notification foundation** is tracked as **Phase I Push** (I.0 / I.1 / I.2…).
2. **Approvals** remain a **separate** optional roadmap item (not renamed to steal “I” for product features — document both clearly).
3. **ERP owns** device registry, push tokens, fan-out policy, and future send workers.
4. **One shared API surface** for all phone clients (`client_app`: `ess` | `manager` | …).
5. Flutter is **presentation only**: may acquire OS/FCM tokens and POST them to ERP; **no** notification business rules, targeting, or local token authority.
6. **Do not** use `rateb_offline_devices` / POS device tables for phone push.
7. **Do not** store passwords, JWTs, session cookies, or auth secrets in the device registry. `push_token` is a **delivery handle** only.

## Ownership & boundaries

| Concern | Owner |
|---------|--------|
| Authentication | Online ERP (`ApiAuthMiddleware` / tokens) |
| Device identity + push handles | ERP `rateb_mobile_devices` |
| In-app notification content | ERP `NotificationService` / `rateb_notifications` |
| Push send (FCM/APNs) | ERP worker (I.2+) — not Flutter |
| Token acquisition UI/OS | Flutter (thin) |
| Who receives which event | ERP policy (I.4) |

## Non-goals (I.0–I.1)

- Firebase / APNs integration
- Flutter changes
- Push worker / Admin revoke UI
- HR business rule changes

## Consequences

- I.1 extends registry (provider/locale, preserve token on register, `POST …/push-token`).
- ESS and Manager must call the same `/api/v1/mobile/devices/*` APIs.
- Violations of Flutter owning push authority or reusing offline device tables are Architecture Conflict.
