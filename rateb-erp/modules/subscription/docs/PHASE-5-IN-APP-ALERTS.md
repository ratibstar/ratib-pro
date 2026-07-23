# Subscription Engine — Phase 5 In-App Alerts (Read Only)

**Status:** Read-only ERP header display  
**Depends on:** Phase 3/4 history rows + Phase 2 `SubscriptionRuntime`  
**Non-goals:** access blocking, redirects, login/middleware enforcement, billing, payment, renewal, suspension actions, permission/routing changes

---

## 1. Alert service

`SubscriptionAlertService`

- Reads **only** `NotificationHistoryRepository` (+ optional `SubscriptionRuntime` for live days/expiry/status).
- **Fallback:** if no history row but `SubscriptionRuntime` is inside the ≤14-day / grace / suspended window, builds an ephemeral alert from context (so engine date changes show immediately without waiting for the scheduler).
- **Never** calls `SubscriptionEngine`, `NotificationPolicy`, or `SubscriptionRepository`.
- Caches result in `SubscriptionAlertRuntime` (one resolve per request).
- Soft dismiss via session (`?dismiss_subscription_alert={id}`) when dismissible.

Accessor: `subscription_alert(): ?SubscriptionAlertViewModel`

---

## 2. View model

`SubscriptionAlertViewModel` — immutable fields:

| Field | Source |
|---|---|
| message | Built from type + live days / grace |
| daysRemaining | `SubscriptionRuntime` (already loaded) |
| expirationDate | request context |
| subscriptionStatus | request context |
| createdAt | history `created_at` / `generated_at` |
| severity / cssClass | type mapping |
| dismissible | display rule |

---

## 3. UI integration

- Banner: `modules/subscription/views/alert-banner.php`
- Included in `views/layouts/main.php` **immediately after flash** (existing top content alert area).
- No new nav item, no second notification system.

---

## 4. Severity rules

| Type | Severity | CSS |
|---|---|---|
| `REMINDER` | normal warning | `alert-warning` |
| `FINAL_WARNING` | high warning | `alert-warning rateb-sub-alert--high` |
| `GRACE` | critical warning | `alert-danger` |
| `SUSPENSION` | critical alert | `alert-danger rateb-sub-alert--critical` |

**Dismissible:** `daysRemaining > 3` and type not GRACE/SUSPENSION (session soft-dismiss only).  
**Persistent:** `daysRemaining ≤ 3` or GRACE/SUSPENSION — no dismiss control.

Example messages:

- `Your subscription expires in 14 days`
- `Your subscription expires in 3 days`
- `Subscription expired. Grace period: 6 days remaining`

---

## 5. Performance report

| Technique | Effect |
|---|---|
| `SubscriptionAlertRuntime` | At most one resolve per request |
| Gate on `SubscriptionRuntime` | If `daysRemaining > 14` and not grace/suspended → **zero history queries** |
| Live days/expiry from Phase 2 context | No second engine table read |
| History query | Single indexed `SELECT … ORDER BY id DESC LIMIT 1` when gate passes |

Expected: most healthy tenants → **0 extra queries**; alert window → **1** history query.

---

## 6. Data flow

```
UI (main layout banner)
  → SubscriptionAlertService
  → NotificationHistoryRepository
  → rateb_subscription_notification_history

(+ SubscriptionRuntime for enrichment only — already bound)
```

---

## Validation checklist

- [x] No permission / routing / middleware enforcement changes  
- [x] No access blocking / redirects  
- [x] No billing / payment / renewal / suspension writes  
- [x] No duplicate notification framework  
- [x] No HR/POS/etc. module edits  
- [x] UI never calls Engine / Policy / SubscriptionRepository  

Unit test:  
`php rateb-erp/modules/subscription/tests/SubscriptionAlertPhase5Test.php`
