# Subscription Engine — Phase 6 Grace Period Engine

**Status:** Lifecycle calculation only (no enforcement)  
**Depends on:** Phase 1–2 subscription engine row + Phase 5 alerts  
**Non-goals:** redirects, access blocking, login/middleware changes, payment, renewal processing, suspension enforcement, UI redesign

---

## 1. GracePeriodEngine

| Method | Meaning |
|---|---|
| `calculateGraceStart($end, $days)` | Day after `subscription_end` |
| `calculateGraceEnd($end, $days)` | `subscription_end + grace_days` (inclusive last day) |
| `daysRemaining(...)` | Days until `grace_end` (0 on last day) |
| `isInGracePeriod(...)` | `today` in `[grace_start, grace_end]` |
| `hasGraceExpired(...)` | `today > grace_end` (and after subscription end) |
| `resolveLifecycleStatus(...)` | Derived label for today |

### Example (7-day default)

| Date | State |
|---|---|
| 2026-08-01 | `subscription_end` (still pre-grace) |
| 2026-08-02 → 2026-08-08 | **GRACE** |
| 2026-08-09 | **SUSPENSION_PENDING** (eligibility only — no action) |

---

## 2. Grace policy

`GracePeriodPolicy` — default **7** days.  
Row `grace_period_days` used when `> 0`; otherwise policy default.

---

## 3. Context integration

`SubscriptionContext` exposes:

- `isInGrace()`
- `graceDaysRemaining()`
- `graceEndDate()` / `graceStartedAt()`
- `isSuspensionPending()`
- `canAccessERP()` — **true** during GRACE and SUSPENSION_PENDING

Status derivation (when not already `SUSPENDED`):

`ACTIVE/WARNING/CRITICAL` → `GRACE` → `SUSPENSION_PENDING`

ERP remains fully accessible; no redirects.

---

## 4. Migration

`migrations/212_subscription_grace_period.sql`

- Adds `grace_started_at`, `grace_end_at`
- Adds enum value `SUSPENSION_PENDING`
- Default `grace_period_days = 7`; backfills calculated window

Does **not** touch billing / payments / invoices.

---

## 5. Alert compatibility

GRACE alerts (critical) message:

`Subscription expired. {N} days remaining in grace period.`

Uses `SubscriptionContext::graceDaysRemaining()`.

---

## 6. Test scenarios

`php rateb-erp/modules/subscription/tests/GracePeriodPhase6Test.php`

- Start/end dates for Aug 2026 example  
- In-grace / expired boundaries  
- Context status + accessibility  
- Default 7 when row grace days = 0  

---

## Validation

- [x] No access blocking / redirects  
- [x] No login / middleware / permission / route changes  
- [x] No billing / payment / renewal / suspension actions  
- [x] Calculation-only lifecycle  
