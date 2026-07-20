# Phase I Push — Foundation (I.0 + I.1 + I.2)

**Status:** I.0 + I.1 + I.2 COMPLETE (ERP only)  
**Date:** 20 Jul 2026  
**ADR:** [ADR-PUSH-1-MOBILE-PUSH-FOUNDATION.md](../../rateb-erp/offline-v2/docs/ADR-PUSH-1-MOBILE-PUSH-FOUNDATION.md)

---

## Naming resolution (I.0)

| Track | Meaning |
|-------|---------|
| **Phase I Push** | Mobile push foundation: registry → outbox → delivery engine |
| **Approvals** | Separate optional ESS roadmap item |

---

## Architecture

```
NotificationService
  ├─ rateb_notifications     (content SoT — unchanged)
  ├─ email / SMS queue       (unchanged)
  └─ MobilePushOutboxService (feature flag)
         └─ rateb_mobile_push_outbox
                └─ PushQueueWorker
                       └─ MobilePushDeliveryService
                              ├─ rateb_mobile_devices (active tokens)
                              ├─ FcmPushProviderInterface (stub I.2)
                              └─ ApnsPushProviderInterface (placeholder)
```

---

## I.2 — Delivery engine

### Outbox `208_mobile_push_outbox.sql`

Fields: id, company_id, user_id (0 = company broadcast), client_app, notification_id, title, body, data_json, status (`pending`|`processing`|`sent`|`failed`), attempts, last_error, created_at, sent_at.

Unique: `(notification_id, client_app, user_id)` — idempotent enqueue.

### Feature flag

`RATEB_MOBILE_PUSH_OUTBOX_ENABLED=0` (default off)  
`RATEB_MOBILE_PUSH_CLIENT_APPS=ess,manager`

Config placeholders: `rateb-erp/config/mobile-push.example.php` (FCM project/credentials path, APNs key/team/bundle — **no secrets in git**).

### Worker

`PushQueueWorker` claimed via cron (`CronService` → `mobile_push`).  
Revoked devices ignored; invalid tokens cleared; retries until `MAX_ATTEMPTS`; never logs full tokens.

### Providers

Interfaces only in I.2 — **no Firebase/APNs SDK**. Stubs return `*_not_configured` / `*_sdk_pending`.

---

## Explicitly not in I.2

Firebase SDK · APNs SDK · Flutter · Push UI · Store · Manager app

---

## Tests

```
php rateb-erp/tests/hr/run-ess-phase-i2-push-delivery-tests.php
php rateb-erp/tests/hr/run-ess-phase-i1-push-foundation-tests.php
php rateb-erp/tests/hr/run-ess-phase-j-device-registry-tests.php
```

**Result (2026-07-20):** I.2 GATE CLEAR 10/10 · I.1 9/9 · J 8/8.
