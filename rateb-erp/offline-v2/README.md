# RATEB Offline V2

Greenfield Offline V2 architecture. **Offline V1 is frozen and untouched.**

## Architecture Freeze v2.1 — ACTIVE

Platform foundation **includes Identity**. See:

- `docs/AF-2.1-ARCHITECTURE-FREEZE.md`
- `docs/AF-2.1-DEPENDENCY-DIAGRAM.md`
- `docs/AF-2.1-OWNERSHIP-MATRIX.md`
- `docs/AF-2.1-SECURITY-BOUNDARY.md`

ERP BusinessModules must consume Identity **only** via published `module.identity.*` APIs and must declare Identity as a dependency.

## Current

| Path | Role |
|------|------|
| `public/v2/js/business/identity-module.js` | Identity (Platform Foundation · FROZEN) |

Open: `/rateb-erp/public/v2/`

Online ERP remains Authentication Authority. Do not start the next ERP module without Architecture Board GO.
