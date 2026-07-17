# Phase PX2.1 — Updated Identity Dependency Graph

**Boundary status:** Category B violations = 0

```mermaid
flowchart LR
    subgraph AUTH["Online boundary"]
        ERP["Online ERP\nONLY Authentication Authority"]
        PKG["Enrollment package\nsealed identity + claims + RBAC + device + policy"]
        ERP --> PKG
    end

    subgraph LOCAL["Offline V2 local boundary"]
        ID["Identity Module\nlocal cache; never authenticates"]
        DB[("Identity-owned entity classes\nsealed · claims · rbac · device · unlock_meta")]
        MEM["Memory-only derived session"]
        PKG --> ID
        ID --> DB
        ID --> MEM
    end

    subgraph BUSINESS["BusinessModules — published APIs only"]
        INV["Inventory"]
        PROC["Procurement"]
        SALES["Sales"]
        ACCT["Accounting"]
        CRM["CRM"]
        HR["HR"]
        MFG["Manufacturing / mfg"]
    end

    ID -->|"module.identity.*"| INV
    ID -->|"module.identity.*"| PROC
    ID -->|"module.identity.*"| SALES
    ID -->|"module.identity.*"| ACCT
    ID -->|"module.identity.*"| CRM
    ID -->|"module.identity.*"| HR
    ID -->|"module.identity.*"| MFG

    INV -->|"module.inventory.*"| PROC
    INV -->|"module.inventory.*"| SALES
    INV -->|"module.inventory.*"| ACCT
    INV -->|"module.inventory.*"| MFG
```

## Mandatory dependency declarations

| Module | Mandatory dependencies | Identity access |
|---|---|---|
| Identity | Platform DB/runtime only | Owns approved local cache |
| Inventory | Identity | `module.identity.*` |
| Procurement | Identity, Inventory | `module.identity.*`, `module.inventory.*` |
| Sales | Identity, Inventory | `module.identity.*`, `module.inventory.*` |
| Accounting | Identity, Inventory | `module.identity.*`, `module.inventory.*` |
| CRM | Identity | `module.identity.*` |
| HR | Identity | `module.identity.*` |
| Manufacturing (`mfg`) | Identity, Inventory | `module.identity.*`, `module.inventory.*` |

Optional cross-module APIs remain unchanged.

## Explicitly absent edges

- No BusinessModule → Identity SQLite edge.
- No BusinessModule → Identity OPFS edge.
- No BusinessModule → `vault.bin` edge.
- No Identity → credential store edge.
- No Identity → credential Sync edge.
- No Identity → authentication-authority edge.

## Branch scope

Offline V2 has no standalone Branches BusinessModule. `branch_id` is an
approved Identity Claim supplied by Online ERP and consumed through
`module.identity.claims`.
