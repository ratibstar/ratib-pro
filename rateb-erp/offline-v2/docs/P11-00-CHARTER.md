# Phase 11 — Inventory Module Charter (Implementation)

**Status:** Binding  
**Module:** `inventory` BusinessModule (`RatebOfflineV2Inventory`)  
**Depends on:** `identity` (Architecture Freeze v2.1)

## Scope

Local warehouses · items · single stock posting writer · FEFO batches · soft reservations · delta adjustments · separate-balance transfers · qty×unit_cost valuation · Identity API gating

## Forbidden

Platform/Identity edits · PHP/Offline V1 copy · bins/cost-layers as if online had them · identity.* SQL · credential storage
