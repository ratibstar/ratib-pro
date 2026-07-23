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
  SubscriptionAdminViewModel.php
  SubscriptionAdminRepository.php
  SubscriptionAdminDashboard.php
  views/dashboard.php
  views/detail.php
```

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

## Ops

1. Deploy code  
2. Run migration `215_subscription_admin_permissions.sql`  
3. Open **Admin oversight → Subscription Engine**
