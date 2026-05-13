# Client Hub / Control Panel Route Ownership

This document is the current source of truth for where customer-facing and control-facing flows should land after the Client Hub ownership unification work.

Use it when checking:

- where a route should go
- whether a route is still legacy-only
- whether `?control=1` should stay in the old shell or redirect
- which UI owns the journey: Client Hub, Control Panel, or compatibility wrapper

---

## Ownership model

There are now three route ownership classes:

- `Client Hub owned`
  - customer-facing pages under `pages/client/`
  - these are the canonical customer surfaces

- `Control Panel owned`
  - operator/admin pages under `control-panel/pages/control/`
  - these are the canonical control-mode surfaces

- `Compatibility / legacy`
  - old main-app pages under `pages/`
  - old module views under `modules/infrastructure-marketplace/Views/...`
  - these stay alive for bookmarks, deep links, embeds, and backward compatibility

---

## Canonical Client Hub routes

These are the primary customer-facing destinations:

- `pages/client/dashboard.php`
  - canonical customer dashboard / Client Hub landing

- `pages/client/services.php`
  - canonical service lifecycle and provisioning visibility

- `pages/client/domains.php`
  - canonical domains and catalog entry point

- `pages/client/orders.php`
  - canonical orders center

- `pages/client/billing.php`
  - canonical billing center

- `pages/client/support.php`
  - canonical support surface inside Client Hub

- `pages/client/notifications-center.php`
  - canonical notification overview inside Client Hub

- `pages/client/settings.php`
  - canonical account/team settings landing inside Client Hub

---

## Canonical Control Panel routes

These are the primary control-owned destinations:

- `control-panel/pages/control/dashboard.php`
  - canonical operator dashboard

- `control-panel/pages/control/control-hub.php`
  - canonical catch-all control navigation / redirect landing

- `control-panel/pages/control/panel-settings.php`
  - canonical control settings hub

- `control-panel/pages/control/system-settings.php`
  - canonical role-gated system settings owner in control mode

- `control-panel/pages/control/accounting.php`
  - canonical control-mode accounting owner

- `control-panel/pages/control/hr.php`
  - canonical control-mode HR owner

- `control-panel/pages/control/help-center.php`
  - canonical control-mode help owner

- `control-panel/pages/control/infrastructure.php`
  - canonical infrastructure operations/control/provider owner

---

## Compatibility routes kept alive

These still exist on purpose:

- `pages/client/marketplace.php`
  - compatibility redirect to `pages/client/domains.php`

- `pages/client/infrastructure.php`
  - compatibility redirect to `pages/client/services.php`

- `modules/infrastructure-marketplace/Views/marketplace/index.php`
  - compatibility wrapper
  - embed target
  - safe redirect entry point

- `modules/infrastructure-marketplace/Views/client/services.php`
  - compatibility wrapper
  - embed target
  - safe redirect entry point

- legacy main-app pages under `pages/`
  - preserved for bookmarks and direct opens
  - control-mode behavior is now redirected where appropriate

---

## Control-mode redirect map

When a legacy route is opened with `?control=1`, it should no longer leave the user inside the old main-app shell.

### Redirects to a specific control-panel owner

- `pages/dashboard.php` -> `control-panel/pages/control/dashboard.php`
- `pages/accounting.php` -> `control-panel/pages/control/accounting.php`
- `pages/hr.php` -> `control-panel/pages/control/hr.php`
- `pages/help-center.php` -> `control-panel/pages/control/help-center.php`
- `pages/settings.php` -> `control-panel/pages/control/panel-settings.php`
- `pages/system-settings.php` -> `control-panel/pages/control/system-settings.php`

### Redirects back into Client Hub ownership

- `pages/profile.php` -> `pages/client/settings.php`

### Redirects to Control Hub because there is no dedicated control-panel implementation yet

- `pages/agent.php` -> `control-panel/pages/control/control-hub.php?legacy_module=agent`
- `pages/subagent.php` -> `control-panel/pages/control/control-hub.php?legacy_module=subagent`
- `pages/Worker.php` -> `control-panel/pages/control/control-hub.php?legacy_module=workers`
- `pages/partner-agencies.php` -> `control-panel/pages/control/control-hub.php?legacy_module=partner_agencies`
- `pages/partner-documents-staff.php` -> `control-panel/pages/control/control-hub.php?legacy_module=partner_agencies`
- `pages/cases/cases-table.php` -> `control-panel/pages/control/control-hub.php?legacy_module=cases`
- `pages/reports.php` -> `control-panel/pages/control/control-hub.php?legacy_module=reports`
- `pages/contact.php` -> `control-panel/pages/control/control-hub.php?legacy_module=contact`
- `pages/notifications.php` -> `control-panel/pages/control/control-hub.php?legacy_module=notifications`

---

## Client Hub internal mismatch rules

Inside `pages/client/`, these rules should now hold:

- `pages/client/settings.php`
  - should not send users to legacy `pages/profile.php`, `pages/settings.php`, or `pages/system-settings.php`
  - should keep profile/account ownership inside Client Hub or Control Panel

- `pages/client/billing.php`
  - should not send control users to legacy accounting shell by mistake

- `pages/client/support.php`
  - in control mode, knowledge-base link should resolve to control-panel help center

- `pages/client/notifications-center.php`
  - in control mode, notification fallback should resolve to control ownership instead of old shell

- `pages/client/index.php`
  - should resolve to Client Hub when available
  - in control-mode fallback, should resolve to control-panel dashboard, not `pages/profile.php`

---

## Navigation ownership

### Main app header

File:

- `includes/header.php`

Current rule:

- old Ratib-style staff nav should not advertise customer-owned Client Hub shortcuts
- `Client Hub` and `Plans & Services` entries were removed from this old shell

### Client Hub sidebar

Files:

- `modules/client-dashboard/bootstrap.php`
- `modules/client-dashboard/Layout/shell-start.inc.php`

Current rule:

- customer journeys are owned here
- sidebar should stay centered on dashboard, services, domains, orders, billing, support, notifications, settings

### Control Panel sidebar

File:

- `control-panel/includes/control/sidebar.php`

Current rule:

- control panel is allowed to expose Client Hub entry points
- it now contains a `Client Platform` section with direct links to:
  - Client Hub
  - Services
  - Domains
  - Orders
  - Billing

---

## Branding normalization

Customer-facing labels should prefer:

- `Client Hub`
- `Services`
- `Domains`
- `Orders`
- `Billing`
- `Support`
- `Account & Team`

Avoid presenting customer-facing experiences as a detached ecosystem under:

- `Ratib Pro`
- `Infrastructure Marketplace`

Internal/admin/developer wording may still mention infrastructure, marketplace, or legacy system names where needed for implementation or operations.

---

## Remaining intentional legacy items

These are not current ownership bugs by themselves:

- internal comments, diagnostics, migration names, and env constants that still mention `Ratib Pro`
- compatibility wrappers that still exist for deep links or embeds
- old main-app pages that remain functional outside control mode

These should only be treated as bugs if they:

- expose the wrong shell in control mode
- show the wrong customer-facing owner in visible UI
- create a loop or broken redirect

---

## Mismatch checklist

When validating a route, check this order:

1. Is the user in customer mode or control mode?
2. Is the route canonical, or only compatibility?
3. If `?control=1` is present, does it land in Control Panel or Control Hub?
4. If it is a customer surface, does it remain inside Client Hub?
5. Does the visible label match the intended owner?

---

## Recommended future cleanup

- Create dedicated control-panel implementations for:
  - agents
  - subagents
  - workers
  - partner agencies
  - cases
  - reports
  - contact
  - notifications

- Once those exist, replace Control Hub catch-all redirects with direct control-panel destinations.

- Remove or retire legacy main-app pages only after:
  - deep links are migrated
  - deploy caches are cleared
  - production smoke tests confirm no regressions

- Optionally add this document to control help-center navigation as an operator reference.
