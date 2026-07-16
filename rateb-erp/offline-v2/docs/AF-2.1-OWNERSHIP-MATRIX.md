# Architecture Freeze v2.1 — Ownership Matrix

**Binding companion to:** `AF-2.1-ARCHITECTURE-FREEZE.md`

## 1. Ownership legend

| Code | Meaning |
|------|---------|
| **O** | Sole owner — may read/write |
| **C** | Consumer via published API only |
| **X** | Forbidden |
| **A** | Authority / Source of Truth (online) |

## 2. Identity & credentials

| Asset | Online ERP | Identity Module | Other BusinessModules | Sync Engine | HCI / OPFS callers |
|-------|------------|-----------------|----------------------|-------------|-------------------|
| User accounts | **A/O** | X (not SoT) | X | X | X |
| Passwords / password hashes | **A/O** | **X** | **X** | **X** | **X** |
| Tokens (API/JWT/OAuth/refresh/bearer) | **A/O** | **X** | **X** | **X** | **X** |
| PHP sessions / cookies | **A/O** | **X** | **X** | **X** | **X** |
| TOTP / recovery / reset secrets | **A/O** | **X** | **X** | **X** | **X** |
| Sealed identity | produces enroll | **O** | **X** (API only for claims view) | **X** | **X** direct |
| Identity claims | produces | **O** | **C** via `module.identity.claims` | X sync of secrets | X |
| RBAC snapshot (local) | produces manifest | **O** | **C** via `module.identity.rbac` | X credentials | X |
| Device trust state | registry authority | **O** (local) | **C** via `module.identity.device` | — | X |
| Unlock metadata (local PIN verifier) | — | **O** | **X** | **X** | **X** |
| Derived local session | — | **O** | **C** via `module.identity.session` | **X** | **X** |
| Identity diagnostics / health | — | **O** | **C** (observe events / public diag if exposed) | — | — |
| `entity_row` `identity.*` | — | **O** | **X** direct SQL | **X** | **X** |
| `vault.bin` identity use | — | **O** if used | **X** | **X** | **X** by ERP modules |
| **Stock ledger / balances / batches / reservations / warehouses / valuation** | Online ERP (server) | **O** Inventory Module | **C** via `module.inventory.*` only | Events only — **never balances** | **X** |
| `entity_row` `inv.*` | — | **O** Inventory | **X** direct SQL | **X** balance writes | **X** |

## 3. Platform layers (unchanged freeze)

| Component | Owner | ERP modules |
|-----------|-------|-------------|
| HCI | Platform | C (reachability etc. via Runtime/HCI published APIs; no identity vault writes) |
| Package Manager | Platform | C |
| SQLite Runtime | Platform | C via published `db` API — **not** identity.* tables |
| Runtime Kernel | Platform | C |
| Router / Shell / Sync / Module SDK / BMF | Platform | C |
| Identity Module | Platform Foundation (v2.1) | C via Identity services + dependency |

## 4. Responsibility summary

| Concern | Responsible party |
|---------|-------------------|
| Authenticate users against server | Online ERP only |
| Enroll sealed identity for offline | Online ERP produces · Identity applies |
| Local unlock / lock | Identity only |
| Expose claims/RBAC/device/session to apps | Identity services only |
| Business features (Sales, HR, …) | Future BusinessModules |
| Credential sync | **Nobody in Offline V2** — forbidden |
