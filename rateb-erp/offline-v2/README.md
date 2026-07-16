# RATEB Offline V2

**Architecture Freeze v2.1 — ACTIVE** (includes Identity).  
**Architecture Freeze v2.1.1 — ACTIVE** (Inventory sole ownership of inventory state).

## Freeze docs

- `docs/AF-2.1-ARCHITECTURE-FREEZE.md`
- `docs/AF-2.1.1-INVENTORY-OWNERSHIP.md`
- `docs/AF-2.1-DEPENDENCY-DIAGRAM.md`
- `docs/AF-2.1-OWNERSHIP-MATRIX.md`
- `docs/AF-2.1-SECURITY-BOUNDARY.md`

## Modules

| Module | Path | Role |
|--------|------|------|
| Identity | `public/v2/js/business/identity-module.js` | Foundation · FROZEN |
| Inventory | `public/v2/js/business/inventory-module.js` | Sole inventory SoT · FROZEN |

Open: `/rateb-erp/public/v2/`

Procurement and other ERP modules may begin only after Architecture Board GO, and must call Inventory via published APIs only.
