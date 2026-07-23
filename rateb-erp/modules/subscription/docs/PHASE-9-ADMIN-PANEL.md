# Phase 9 — Subscription Administration Panel

**Status:** Implemented (ops console only)  
**Scope:** PHP Core · Admin only · no payment · no auto-billing · no ERP module changes

## Goal

Internal platform console to observe and manually manage tenant subscription lifecycle.

## URL

| Route | Permission | Action |
|-------|------------|--------|
| `GET /admin/subscription-engine` | `subscriptions.view` | Dashboard + paginated tenant list |
| `GET /admin/subscription-engine/{id}` | `subscriptions.view` | Tenant detail + histories |
| `POST .../{id}/renew` | `subscriptions.manage` | Manual renewal → ACTIVE |
| `POST .../{id}/extend` | `subscriptions.manage` | Extend expiry → ACTIVE |

Distinct from billing CRUD at `/admin/subscriptions` (plans/amount).

## Components

```
modules/subscription/admin/
  SubscriptionAdminController.php
  SubscriptionAdminService.php
  SubscriptionAdminNotifier.php
  SubscriptionAdminViewModel.php
  SubscriptionAdminRepository.php
  SubscriptionAdminDashboard.php
  views/dashboard.php
  views/detail.php
```

## Platform admin notifications

Opening the console (and Phase 4 scheduler after a real run) fans out **in-app bell notifications** to every active `is_super_admin` user for each company in the alert window (≤14 days, grace, suspended).

- Trigger: `subscription_engine_alert`
- Idempotent: one notification per admin × company × calendar day
- Ops panel on the dashboard lists companies needing follow-up with deep links
- **Perf:** page load is **read-only** (dashboard + list + ops panel SELECT only). Sync is on-demand (`?sync=1`). Admin bell fan-out runs after paint via `POST .../fanout` and Phase 4 scheduler — never blocks HTML render.

## Dashboard counters

- Total tenants  
- Active  
- Warning (WARNING + CRITICAL)  
- Grace (GRACE + SUSPENSION_PENDING)  
- Suspended  
- Expiring soon (≤ 14 days, not suspended)

## Tenant list columns

Company · Status · Start · Expiry · Days remaining · Grace · Suspension · Last renewal

Paginated; filter by status / search name or company id. Queries use `rateb_subscription_engine` + `rateb_companies.name` only (no HR/finance).

## Detail page

- Lifecycle timeline (created + lifecycle audit + renewals + suspension audits)
- Notifications history
- Renewal history
- Suspension audit
- Actions (manage only): Manual renewal, Extend expiry

## Security / RBAC

- Reuses existing RBAC (`rateb_can`)
- New slug: `subscriptions.view` (migration `215_subscription_admin_permissions.sql`)
- `subscriptions.manage` **implies** `subscriptions.view` (`config/permissions-system.php`)
- Both excluded from company-role full-access
- Routes use `rateb_platform_oversight_mw` (super-admin + platform host + permission)
- CSRF on POST; actor_id from session for renewal engine

## Audit

- Renewal → `RENEWED` via `RenewalEngine` (history + lifecycle audit)
- Extend → `EXTENDED` lifecycle audit + renewal history row (`period=extend`)

## Out of scope

- Payment gateway / invoices / auto-renew / plan billing UI  
- Changes to HR / POS / Inventory / other ERP modules  

## Tests

```bash
php rateb-erp/modules/subscription/tests/SubscriptionAdminPhase9Test.php
```

## Auto-sync (companies → engine)

Opening `/admin/subscription-engine` **inserts missing** `rateb_subscription_engine` rows for companies in `rateb_companies`:

- Prefers latest `rateb_subscriptions.starts_at` / `ends_at` when present
- Else defaults: start = today, end = today + 365 days
- Billing `cancelled` / `expired` → engine `SUSPENDED`; otherwise `ACTIVE`
- **Insert-only** — never overwrites existing engine dates/status (renewals stay authoritative)

Why the list was empty before: engine table had zero rows until this bootstrap ran.

## Ops

1. Deploy code  
2. Run migration `215_subscription_admin_permissions.sql`  
3. Open **Admin oversight → Subscription Engine**

Deploy marker: Phase 9 shipped on `main` (subscription-engine admin console).
