# Subscription Engine — Phase 7A Suspension Gate (Shadow Mode)

**Status:** Decision / eligibility only — **no enforcement**  
**Depends on:** Phase 6 GracePeriodEngine + SubscriptionContext  
**Non-goals:** redirects, access blocking, Guard wiring, login/middleware, UI, billing, payment, renewal, production lock

> The system calculates whether a tenant **SHOULD** be suspended.  
> It must **not** restrict ERP access.

---

## 1. SuspensionEngine

| Method | Role |
|---|---|
| `evaluate(SubscriptionContext, ?today)` | Returns `SuspensionDecision` |
| `shouldSuspend(...)` | `evaluate()->isEligible()` |
| `reason(...)` | Decision reason (or last) |
| `suspensionDate(context)` | First eligible calendar day |

Does **not** call `SubscriptionGuard`, auth, middleware, or write engine status.

---

## 2. Decision object

`SuspensionDecision` (immutable):

- `company_id`
- `eligible`
- `reason`
- `effective_date`
- `current_status`

---

## 3. Policy

`SuspensionPolicy` — eligible only when:

1. `subscription_end` has passed, **and**
2. grace period has expired (`GracePeriodEngine::hasGraceExpired`)

### Example

| Date | Meaning |
|---|---|
| 2026-08-01 | `subscription_end` |
| 2026-08-08 | `grace_end` |
| **2026-08-09** | suspension **eligible** (shadow) |

---

## 4. Audit logging

Table: `rateb_subscription_suspension_audit`  
Migration: `migrations/213_subscription_suspension_audit.sql`

| Column | Notes |
|---|---|
| `company_id` | Tenant |
| `decision` | `eligible` \| `not_eligible` |
| `reason` | Machine reason code |
| `created_at` | Timestamp |

`SuspensionAuditRepository` — optional; by default Engine audits **eligible** decisions only when an audit repo is injected. Failures are swallowed (never break requests).

---

## 5. Test scenarios

`php rateb-erp/modules/subscription/tests/SuspensionShadowPhase7aTest.php`

| # | Scenario | Expected |
|---|---|---|
| 1 | Active subscription | not eligible (`subscription_active`) |
| 2 | Expired, grace active | not eligible (`grace_period_active`) |
| 3 | Grace expired | **eligible** (`grace_period_expired`) |
| 4 | Missing subscription data | not eligible (`missing_subscription_data`) |

---

## 6. Architecture

```
SubscriptionContext
      ↓
SuspensionEngine
      ↓
SuspensionPolicy → GracePeriodEngine
      ↓
SuspensionDecision
      ↓ (optional)
SuspensionAuditRepository → rateb_subscription_suspension_audit
```

---

## Validation

- [x] No redirects / blocking / Guard / auth / permission changes  
- [x] No UI / billing / payment / renewal  
- [x] Shadow mode only — `canAccessERP()` unchanged by this phase  
