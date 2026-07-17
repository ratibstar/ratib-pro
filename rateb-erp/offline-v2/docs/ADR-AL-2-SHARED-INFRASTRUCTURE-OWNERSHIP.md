# ADR-AL-2 — Shared Infrastructure Ownership

**Status:** ACCEPTED  
**Date:** 2026-07-17  
**Related:** [ADR-AL-1 — Single ERP Frontend](./ADR-AL-1-SINGLE-ERP-FRONTEND.md)

---

## Decision

All shared infrastructure extracted from `public/v2` becomes owned by Admin.

Ownership transfers to:

- `/rateb-erp/public/admin/*`
- `/rateb-erp/public/assets/offline/*`
- another Admin-owned shared package

## Binding ownership rules

After extraction, `public/v2` **MUST NOT** be the canonical source of:

- Runtime
- Identity
- SQLite
- Sync
- Queue
- Service Locator
- EventBus

Each component must have exactly one maintained implementation, owned by Admin.

During migration, `public/v2` may temporarily reference extracted libraries, but it must never own them.

The Identity implementation remains subject to the AF-2.1 security boundary: Online ERP is the sole Authentication Authority, and local Identity stores no credentials or authentication secrets.

## End state

- Admin owns all shared infrastructure.
- `public/v2` owns nothing.
- After Phase 5, `public/v2` is removable without affecting production.

## Supersession

This decision remains binding unless explicitly superseded by a later approved ADR before any conflicting implementation.
