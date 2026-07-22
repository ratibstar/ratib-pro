# Phase PX4 — Architecture Remediation

**Status:** IMPLEMENTED  
**Effective:** 2026-07-17  
**Governs:** RATEB ERP Offline V2 (`rateb-erp/public/v2`)  
**Binding architecture:** AF 2.1 / AF 2.1.1 (Architecture v1.3.1 remains Platform Catalog only)

## Scope

Remediate Critical and High findings from the enterprise architecture audit, then Medium cleanup, without performance work and without new Runtime global services.

## PHASE 1 — Critical

### 1. Sync lifecycle

- `boot.js` `initializeStorageAndPlatform()` creates `RatebOfflineV2Sync.create()` and `start()` after DB open and **before** `initializeActiveModule()`.
- Sync registers on `runtime.services` as `sync` inside `start()`.
- Writers (`accounting` / `crm` / `hr` / `mfg` `_enqueueBusinessEvent`) **reject** with `sync_not_ready` if sync is missing (no silent skip).

### 2. Identity freshness (no stale singleton cache)

- `BusinessModule.exposeService()` wraps functions as `{ kind: 'module.service', invoke }` so Runtime never treats API methods as singleton factories that cache Promises.
- Consumers use `callPublished()` / `RatebOfflineV2Business.invokePublished()`.
- Identity `runSelfTest` asserts session before unlock → after unlock → after lock via published APIs.

## PHASE 2 — High

### 3. Manufacturing id

- Boot selector / activate id is **`mfg`** (matches module metadata and route `/mfg`).
- Script file remains `manufacturing-module.js` via explicit script map (not an id alias hack).

### 4. Sales / Procurement namespace ownership

- Local denylists removed.
- Both use `Business.createDocStore({ ownedPrefix: 'sales.' | 'proc.' })` — positive allowlist only.

## PHASE 3 — Medium

### 5. Published APIs only

- Removed `biz.getModule(...).module[name]` live-instance calls.
- Cross-module inventory/sales/identity access goes through `callPublished`.

### 6. `company_id` in SQL

- Migration **v3** adds `entity_row.company_id` + index; backfills from JSON.
- Shared `createDocStore` filters `get` / `list` / `remove` / `put` by `company_id`.

### 7. Dedup / dead code

- Single route manifest: `js/routes/route-manifest.json` (removed duplicate `routes/route-manifest.json`).
- Removed Identity `sync:enqueued` dead watcher.
- Shared DocStore factory on Business framework.

## Regression

- Module `runSelfTest` notes (Identity freshness, Sales/Procurement ownedPrefix).
- Tool: `rateb-erp/tools/boot-bench/phase-px4-regression.js`

## Identity boundary

Unchanged: Online ERP is Authentication Authority; Identity stores only sealed/claims/rbac/device/unlock_meta; no credentials.

## Offline V1

Zero-touch.
