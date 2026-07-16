# Architecture Freeze v2.1 — Dependency Diagram

**Binding companion to:** `AF-2.1-ARCHITECTURE-FREEZE.md`

## 1. Platform foundation stack

```mermaid
flowchart TB
  subgraph FOUNDATION["Offline V2 Platform Foundation — FROZEN v2.1"]
    L0[L0 HCI]
    L7[L7 Package Manager]
    L3[L3 SQLite Runtime]
    L1[L1 Runtime Kernel]
    L2[L2 SPA Router]
    L6[L6 UI Shell]
    L4[L4 Sync Engine]
    L5[L5 Module SDK]
    BMF[Business Module Framework]
    ID[Identity Module — Foundation]
  end

  L0 --> L7
  L0 --> L3
  L7 --> L1
  L3 --> L1
  L1 --> L2
  L1 --> L6
  L1 --> L4
  L1 --> L5
  L5 --> BMF
  BMF --> ID
  L3 -.->|entity_row identity.* owned by Identity only| ID
  L0 -.->|OPFS layout; no direct vault access by ERP modules| ID

  subgraph FUTURE["Future ERP BusinessModules"]
    M1[Sales / Inventory / …]
  end

  M1 -->|"published Identity Service APIs ONLY"| ID
  M1 -->|"dependencies: identity"| L5
  M1 -.->|"FORBIDDEN: SQLite identity.* / vault.bin / sealed / unlock meta"| X[Blocked]
```

## 2. Identity consumption path (allowed)

```mermaid
sequenceDiagram
  participant BM as ERP BusinessModule
  participant RT as Runtime services
  participant ID as Identity Services
  participant Store as Identity-owned storage

  BM->>RT: get module.identity.claims / session / rbac / device
  RT->>ID: invoke published service
  ID->>Store: read identity.* (owner only)
  Store-->>ID: payload
  ID-->>BM: claims / session / rbac / device

  Note over BM,Store: BM never opens entity_row identity.* or vault.bin
```

## 3. Authentication authority

```mermaid
flowchart LR
  ONLINE[Online ERP<br/>Authentication Authority<br/>Credential SoT]
  ENROLL[Enrollment package<br/>claims · sealed · RBAC · device]
  ID[Identity Module<br/>Local runtime]
  BM[ERP BusinessModules]

  ONLINE -->|enroll when online session exists| ENROLL
  ENROLL -->|applyEnrollmentPackage| ID
  ID -->|service APIs| BM
  BM -.->|NEVER authenticates against server| ONLINE
```

## 4. Dependency declaration (required)

Future modules **must** declare:

```json
{
  "dependencies": [
    { "id": "identity", "version": ">=1.0.0" }
  ]
}
```

Validated by Business Module Framework `validateDependencies` before activation.
