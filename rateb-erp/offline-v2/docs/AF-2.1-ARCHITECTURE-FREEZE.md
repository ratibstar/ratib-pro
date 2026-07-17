# Architecture Freeze v2.1

**Status:** BINDING · PERMANENT until superseded by an approved ADR  
**Effective:** 2026-07-16  
**Supersedes:** Architecture Freeze v2.0 (platform layers L0–L7 + Business Module Framework)  
**Extends:** Phase 10 Identity Module (APPROVED) into Offline V2 Platform Foundation  
**Product UI superseded by:** [ADR-AL-1 — Single ERP Frontend](./ADR-AL-1-SINGLE-ERP-FRONTEND.md) (2026-07-17)

> **Architecture Lock (ADR-AL-1):** The only production ERP frontend is `/rateb-erp/public/admin/*`.  
> `public/v2` (including router, UI shell, `index.html`, `sw.js`) must never be a production ERP frontend again — migration / shared-infra extraction / archive only.  
> This freeze still binds **platform contracts** (Identity APIs, Runtime, Sync, Module SDK, security boundary) for extraction into Admin; it does **not** authorize a second ERP UI.

---

## 1. Purpose

Architecture Freeze v2.1 locks the Offline V2 platform **including Identity** as foundation. All future ERP BusinessModules consume Identity only through published Identity Service APIs. Product UI lives under Admin per ADR-AL-1.

## 2. Platform Foundation (frozen)

| Layer / Component | Global / Path | Status |
|-------------------|---------------|--------|
| L0 Host / HCI | `RatebOfflineV2HCI` | FROZEN |
| L7 Package Manager | `RatebOfflineV2PM` | FROZEN |
| L3 SQLite Runtime | `RatebOfflineV2DB` | FROZEN |
| L1 Runtime Kernel | `RatebOfflineV2Runtime` | FROZEN |
| L2 SPA Router | `RatebOfflineV2Router` | FROZEN |
| L6 UI Shell | `RatebOfflineV2Shell` | FROZEN |
| L4 Sync Engine | `RatebOfflineV2Sync` | FROZEN |
| L5 Module SDK | `RatebOfflineV2Modules` | FROZEN |
| Business Module Framework | `RatebOfflineV2Business` | FROZEN |
| **Identity Module (Foundation)** | **`RatebOfflineV2Identity`** | **FROZEN (v2.1)** |
| Offline V1 | `public/assets/offline`, `offline/`, SW shells, Capacitor | FROZEN · ZERO TOUCH |

No architectural changes to the above without a formal **Architecture Decision Record (ADR)** approved by the Architecture Board.

## 3. Identity as Platform Foundation

1. Identity Module is part of the Offline V2 Platform Foundation.
2. Identity is the **only** owner of local identity, device trust, unlock state, RBAC snapshot, and identity diagnostics.
3. Online ERP remains the **Authentication Authority** and credential Source of Truth.
4. Identity remains a **local identity runtime** — never credential SoT.

## 4. Mandatory access rules (non-negotiable)

### 4.1 Prohibited for all BusinessModules (except Identity itself)

- Direct access to `vault.bin` / OPFS identity storage
- Direct access to Sealed Identity payloads
- Direct access to Unlock Metadata
- Direct access to RBAC Snapshot storage
- Direct SQLite queries to identity-owned `entity_row` types (`identity.*`)
- Direct OPFS reads/writes of identity storage
- Bypassing Identity Service APIs for claims, unlock, device, or RBAC

### 4.2 Required for all BusinessModules

- Obtain identity information **only** through published Identity Service APIs
- Declare **Identity as a dependency** via the published Module SDK / BusinessModule metadata (`dependencies: [{ id: 'identity', version: '>=1.0.0' }]`)
- Treat unlock/session/claims/RBAC as opaque results from Identity services

### 4.3 Published Identity Service APIs (consume only)

Registered under Runtime services (module namespace), including:

| Service key | Purpose |
|-------------|---------|
| `module.identity.session` | Derived local session (unlocked state) |
| `module.identity.unlock` | Local unlock |
| `module.identity.lock` | Lock |
| `module.identity.claims` | Identity claims |
| `module.identity.rbac` | RBAC snapshot (read) |
| `module.identity.device` | Device trust state |
| `module.identity.enrollBridge` | Online enroll bridge (no authentication) |
| `module.identity.applyEnrollment` | Apply online-produced enrollment package |
| `module.identity.securityScan` | Identity security scan |

Events (observe only): `identity:ready`, `identity:enrolled`, `identity:unlocked`, `identity:locked`.

## 5. Change control

| Change type | Allowed? |
|-------------|----------|
| New ERP BusinessModule using Identity APIs + dependency declaration | Yes (per-module board approval) |
| Modify Identity implementation | **No** without ADR |
| Modify frozen platform layers | **No** without ADR |
| Modify Offline V1 | **Never** |
| Store credentials in Offline V2 | **Never** |

## 6. Related documents

- `AF-2.1-DEPENDENCY-DIAGRAM.md`
- `AF-2.1-OWNERSHIP-MATRIX.md`
- `AF-2.1-SECURITY-BOUNDARY.md`
- `P10-00-CHARTER.md` / `P10-ARCHITECTURE-COMPLIANCE.md`

## 7. Board decision

**Architecture Freeze v2.1: ACTIVE**  
Next ERP modules may begin only after an explicit per-module Architecture Board GO, under these constraints.
