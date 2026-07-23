# Phase 8 — Renewal & Reactivation Engine

**Status:** Implemented (manual lifecycle only)  
**Scope:** PHP Core · no frameworks · no payment gateway · no auto-charge · no UI redesign · no ERP module changes

## Goal

Complete the subscription lifecycle when an authorized billing/admin process confirms a **manual** renewal. After success the tenant is **ACTIVE immediately**.

## Components

| Class | Role |
|-------|------|
| `RenewalEngine` | `validateRenewal()`, `renew()`, `reactivate()`, `calculateNewPeriod()` |
| `RenewalRequest` | Immutable input: `company_id`, `new_expiry_date`, `renewal_period`, `actor_id`, `reference?` |
| `RenewalResult` | Success / reject payload |
| `RenewalRepository` | Engine UPDATE + history + lifecycle audit |
| `RenewalAuthorizer` / `DefaultRenewalAuthorizer` | Server-side auth only (`subscriptions.manage` / `billing.manage` / super-admin; session actor must match) |

## Flow

```
RenewalRequest
    → validate company (engine row exists)
    → authorize actor (never trust client roles)
    → validate / calculate new_expiry_date
    → UPDATE rateb_subscription_engine
         · subscription_end = new expiry
         · current_status = ACTIVE
         · suspended_at = NULL
         · grace_started_at / grace_end_at = NULL
         · renewed_at = NOW()
    → insert rateb_subscription_renewal_history
    → insert rateb_subscription_lifecycle_audit (action=RENEWED)
    → rebind SubscriptionContext (ACTIVE immediately)
```

Notification / suspension alert history rows are **not** deleted (remain history only).

## After success

| Concern | Result |
|---------|--------|
| `SubscriptionContext` | `ACTIVE` |
| Suspension | Disabled (`suspended_at` cleared) |
| Grace | Reset (stored grace columns cleared; new end in future ⇒ not in grace) |
| Alerts | Prior history retained |

## Database

Migration: `migrations/214_subscription_renewal_history.sql`

- `rateb_subscription_renewal_history` — id, company_id, previous_expiry_date, new_expiry_date, period, actor_id, reference, created_at  
- `rateb_subscription_lifecycle_audit` — id, company_id, action, old_status, new_status, actor_id, created_at  

Does **not** modify billing / invoice / payment tables.

## Period tokens (`calculateNewPeriod`)

| Token | Meaning |
|-------|---------|
| `30d` / `90d` | Add N days |
| `1m` / `12m` | Add N months |
| `1y` | Add N years |
| `30` (digits) | Add N days |

Base date = `max(today, current subscription_end)`.

If `new_expiry_date` is provided and valid (`Y-m-d`, ≥ today), it wins over period calculation.

## Security

- Only `DefaultRenewalAuthorizer` (or an injected `RenewalAuthorizer`) may approve renewals.
- Client-supplied roles / “I am admin” flags are ignored.
- Web: `actor_id` must match the authenticated session user when a session exists.
- Production callers: billing/admin process with `subscriptions.manage` or `billing.manage`, or super-admin.

## Out of scope (explicit)

- Payment gateway / N-Genius / Stripe / etc.
- Automatic charging or dunning
- Invoice creation
- UI redesign of renew pages (Phase 7B placeholder pages unchanged)
- Changes to HR / POS / Inventory / other ERP modules

## Tests

```bash
php rateb-erp/modules/subscription/tests/RenewalPhase8Test.php
```

Coverage:

1. Active renewal → extended expiry  
2. Grace renewal → ACTIVE + grace cleared  
3. Suspended renewal → immediate restore  
4. Invalid company / unauthorized → reject  
5. Expiry calculation correctness  

## Example (server-side)

```php
$engine = new \Rateb\App\Subscription\RenewalEngine();
$result = $engine->renew(new \Rateb\App\Subscription\RenewalRequest(
    companyId: $companyId,
    newExpiryDate: '2027-01-01', // or '' to calculate from period
    renewalPeriod: '12m',
    actorId: (int) SessionManager::get('rateb_user_id'),
    reference: 'MANUAL-2026-001'
));
```
