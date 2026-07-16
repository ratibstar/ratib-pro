# Architecture Freeze v2.1.1 — Inventory Ownership

**Status:** BINDING · PERMANENT until superseded by an approved ADR  
**Effective:** 2026-07-16  
**Extends:** Architecture Freeze v2.1  
**Does not modify:** Identity Module · Inventory Module implementation · any platform layer

---

## 1. Permanent rule

**Inventory Module is the ONLY Owner of Inventory State** in Offline V2.

Inventory is the **Single Source of Truth** for:

- Stock Ledger  
- Stock Balance  
- Batch State  
- Reservation State  
- Warehouse State  
- Inventory Valuation  

## 2. Forbidden for every other BusinessModule

No other BusinessModule (Sales, POS, Procurement, Manufacturing, Projects, CRM, HR, etc.) may:

- Create / modify / delete inventory balances  
- Adjust inventory balances  
- Reserve / release inventory  
- Transfer / consume / produce inventory  
- Directly query or update Inventory-owned SQLite storage (`inv.*` entity types or future inventory tables)  
- Directly access Inventory data via OPFS  

## 3. Required integration path

All inventory operations **MUST** go through published Inventory Service APIs only, e.g.:

| Service | Purpose |
|---------|---------|
| `module.inventory.postMovement` | Stock in / out / transfer / adjustment |
| `module.inventory.availableQty` | On-hand / reserved / available |
| `module.inventory.reserve` / release | Soft reservations |
| `module.inventory.upsertWarehouse` / `listWarehouses` | Warehouse state |
| `module.inventory.upsertItem` / `listItems` | Catalog/balance rows (owner API) |
| `module.inventory.valuation` | Valuation report |
| `module.inventory.listMovements` | Ledger read |

Events (observe only): `inventory:ready`, `inventory:movement`.

## 4. Sync Engine rule

Sync Engine **MUST** synchronize **inventory events** (e.g. movement intents / ledger events) only.

Sync Engine **MUST NEVER** synchronize **calculated balances** as authoritative writes that bypass Inventory.

Balance reconstruction is Inventory’s responsibility after applying events.

## 5. Dependency declaration

Modules that need stock effects **MUST** declare:

```json
{ "dependencies": [{ "id": "inventory", "version": ">=1.0.0" }, { "id": "identity", "version": ">=1.0.0" }] }
```

## 6. Violation class

Breaches are **Category B Architecture Violations**.

## 7. Related

- `AF-2.1-ARCHITECTURE-FREEZE.md`  
- `AF-2.1-OWNERSHIP-MATRIX.md`  
- `AF-2.1-SECURITY-BOUNDARY.md`  
- Inventory module: `public/v2/js/business/inventory-module.js` (frozen implementation)
