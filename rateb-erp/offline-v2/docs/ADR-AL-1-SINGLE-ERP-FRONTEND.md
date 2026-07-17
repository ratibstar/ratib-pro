# ADR-AL-1 — Single ERP Frontend Architecture Lock

**Status:** ACCEPTED · PERMANENTLY BINDING  
**Date:** 2026-07-17  
**Supersedes (product UI):** Any interpretation of Offline V2 (`public/v2`) as a production ERP frontend  
**Does not supersede:** AF-2.1 Identity / Authentication Authority boundary (Online ERP remains sole Authentication Authority)  
**Related:** Phase 0 / 0.5 / 1.5 consolidation; AF-2.1 platform freeze (infrastructure extraction only)

---

## Decision

The **only** official ERP product frontend is:

```text
https://rateb.sa/rateb-erp/public/admin/*
```

## Binding rules

1. No second production ERP frontend may be introduced without a formal ADR approved before implementation.
2. The following paths are **infrastructure only** and **MUST NEVER** become production ERP frontends again:
   - `public/v2`
   - `public/v2/js/ui`
   - `public/v2/js/router`
   - `public/v2/index.html`
   - `public/v2/sw.js`
3. Those paths may exist only as:
   - temporary migration layer
   - shared infrastructure extraction
   - archived documentation
4. Default policy:
   - ONE ERP
   - ONE URL space
   - ONE Service Worker
   - ONE Runtime
   - ONE Identity
   - ONE Offline Engine
   - ONE Sync Engine
   - ONE Navigation
   - ONE Business Logic

## Consequences

- New ERP features (including HR, Inventory, CRM, Manufacturing, Accounting, POS integration) are implemented only under Admin.
- No new UI components, routes, screens, navigation, or layouts under `public/v2`.
- Shared offline libraries (Runtime, Identity, SQLite, Sync, Queue, Service Locator, EventBus) are consumed only by Admin after migration.
- After Phase 5, `public/v2` must not contain runtime, router, shell, service worker, manifest, navigation, or business screens — docs/archive only until removal.

## Supersession

This lock is permanent unless explicitly superseded by a later ADR that is approved before any conflicting implementation.
