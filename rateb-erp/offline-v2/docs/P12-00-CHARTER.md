# P12-00 — Phase 12 Charter (Procurement)

**Module:** Procurement (`procurement`)  
**API:** `RatebOfflineV2Procurement` `1.0.0-phase12`  
**Dependencies:** `identity >= 1.0.0`, `inventory >= 1.0.0`  
**Architecture Freeze:** AF 2.1 + AF 2.1.1 ACTIVE

## Scope

Implement Offline V2 Procurement BusinessModule owning documents only:

- Purchase Request · RFQ · Supplier Quote · Purchase Order · GRN · Landed Cost · Approvals

## Non-negotiable

- Never own inventory state
- Never write `inv.*` / `identity.*` storage
- GRN → `module.inventory.postMovement` (via published Inventory APIs)
- Landed cost → Inventory valuation via `module.inventory.upsertItem` (unit_cost)
- No platform / Identity / Inventory / Offline V1 edits

## Stop condition

Enterprise Complete → **STOP**. Do not start the next ERP module.
