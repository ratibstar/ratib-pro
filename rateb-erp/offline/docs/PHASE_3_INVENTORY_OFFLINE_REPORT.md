# Phase 3 — Inventory Offline (Tier 1)

**Date:** 2026-07-11  
**Scope:** Inventory module offline only — stock movements, stock counts, warehouse transfers, catalog delta, conflicts, replay, tests  
**Out of scope:** HR, Procurement, Accounting, ERP shell, Designed/, existing ERP business logic / APIs / schema

---

## Repository audit

### Additive / offline-local changes

| Area | Change |
|------|--------|
| Replay adapter | `InventoryOfflineReplayService` — thin dispatch to `StockMovementService` + `InventoryWorkflowService` |
| Tenant/branch guard | `InventoryOfflineTenantGuard` — company + branch isolation before replay |
| Catalog delta | `InventoryOfflineCatalogService` — read-only `rateb_inventory` pull + cursor persist |
| Queue | `OfflineQueueService` — accepts `module=inventory` when `offline.inventory.movements` is on; processes via replay |
| Replay engine | `OfflineReplayEngine` — flag-gated inventory delegation |
| Conflict resolver | `resolveInventory()` — LWW + `quantity_changed` |
| Background sync | Processes inventory pending rows when master + inventory flags allow |
| Authz | API tokens with `inventory` ability may manage sync process/resolve |
| Client adapter | `inventory-adapter.js` — enqueue movement/count/transfer + `pullCatalog` |
| SDK bundle | `public/assets/offline/rateb-offline.js` v3.0.0 |
| Config | `modules.php`, `entity-manifest.php` inventory operations |
| Tests | `InventoryOfflinePhase3Test` — 33 cases |

### Explicit non-changes

- No edits to `StockMovementService`, `InventoryWorkflowService`, inventory controllers, or company inventory views
- No new / altered ERP REST business APIs
- No database schema migrations (reuses `rateb_offline_*` + existing inventory tables)
- No HR / Procurement / Accounting offline work
- Feature flag `offline.inventory.movements` defaults **false**

### Architecture compliance

| Rule | Status |
|------|--------|
| Inventory only (Tier 1) | Pass |
| Replay via existing services | Pass |
| No business logic duplication | Pass |
| Idempotent replay (`[offline:key]` markers) | Pass |
| Conflict handling (version + quantity) | Pass |
| Branch + tenant isolation | Pass |
| Zero data loss (selective clear / ack contract) | Pass (inherits 2A.1) |
| Flag default OFF | Pass |

---

## Security audit

| Control | Status | Notes |
|---------|--------|-------|
| Master + inventory flags required | Pass | Enqueue/replay/catalog gated |
| Push `ok` only on accepted/duplicate | Pass | Existing `OfflinePushAckContract` |
| Client never wipes rejected/conflict | Pass | `clearable_keys` only |
| Payload strips url/method/headers | Pass | `OfflinePayloadSanitizer` |
| Tenant isolation on inventory/warehouse | Pass | `InventoryOfflineTenantGuard` |
| Branch isolation | Pass | Guard + `OfflineBranchGuard` on push/delta |
| CSRF on sync push/process | Pass | Existing controller |
| Authz for process/resolve | Pass | pos or inventory ability / unrestricted token |
| No client-trusted replay URL | Pass | Server action whitelist |
| HR/Procurement still rejected | Pass | Queue rejects those modules |

**Residual risks (Medium):**

1. Live DB integration of full movement/count/transfer replay still needs staging soak with migrated `rateb_offline_*` tables.  
2. Idempotency markers live in `notes` (`[offline:key]`) — acceptable without schema change; prefer dedicated column in a future additive migration if ops require reporting.  
3. `warehouse_transfer.approve` offline can race with online approvals — conflict path records `warehouse_transfer_already_processed`.

---

## Test report

**Commands:**

```bash
php offline/tests/run-offline-foundation-tests.php
php offline/tests/run-inventory-offline-tests.php
```

**Results (2026-07-11, PHP 8.4.19, no local MySQL):**

| Suite | Cases | Result |
|-------|-------|--------|
| Offline Foundation (2A/2A.1) | 26 | **26/26 PASS** |
| Inventory Offline Phase 3 | 33 | **33/33 PASS** |

Coverage mapped to requirements:

1. Inventory adapter — client source + SDK bundle audits  
2. Stock movement queue — deferred actions + enqueue path + empty payload guards  
3. Offline stock count — `stock_count.create` validation  
4. Warehouse transfer queue — create + approve guards  
5. Delta pull catalog — flag-off stub + client branch/cursor  
6. Conflict resolution — `quantity_changed` / `server_newer` / accept  
7. Replay via existing services — source audit (no direct SQL writes)  
8. Multi-branch — tenant/branch guard audits + authz  
9. Background sync — disabled when master OFF  
10. Unit / integration / stress — 5k ack, 2k conflict, 1k sanitizer  

---

## Performance report

| Benchmark | Iterations | Result |
|-----------|------------|--------|
| Push ack contract | 5,000 | PASS (local CPU) |
| Inventory conflict resolver | 2,000 | PASS |
| Payload sanitizer (inventory) | 1,000 | PASS |
| Catalog delta SQL | Not measured (no MySQL) | Staging required |
| End-to-end replay latency | Not measured (no MySQL) | Staging required |

**Notes:** Unit/stress paths are O(1) per item and do not allocate business-domain objects beyond resolver/sanitizer. Catalog pull is capped at 500 rows/request with cursor pagination.

---

## Production readiness score

| Dimension | Score (0–10) | Weight |
|-----------|--------------|--------|
| Functional completeness (movement/count/transfer/delta) | 8.0 | 25% |
| Architecture / no logic duplication | 9.5 | 20% |
| Sync integrity (ack + idempotency + selective clear) | 9.0 | 20% |
| Conflict / multi-branch / tenant | 8.5 | 15% |
| Test depth (unit + stress + source integration) | 8.0 | 10% |
| Security posture | 8.5 | 10% |

**Weighted score: 8.6 / 10**

### Gate recommendation

**CONDITIONAL GO** for staging enablement of Inventory Offline:

```bash
RATEB_OFFLINE_ENABLED=1
RATEB_OFFLINE_INVENTORY_MOVEMENTS=1
```

Before production:

1. Confirm migrations **175–177** (and device 179 if used) applied  
2. Staging soak: offline movement → push → process; stock count; warehouse transfer; multi-branch deny  
3. Monitor `rateb_offline_sync_conflicts` for `quantity_changed`  
4. Keep HR / Procurement / read_cache flags **OFF**  
5. Do not inject SDK into ERP layouts until ops sign-off on catalog delta volume  

---

## Enablement checklist

- [ ] Staging DB has `rateb_offline_sync_queue` / conflicts / cursors  
- [ ] `RATEB_OFFLINE_INVENTORY_MOVEMENTS=1` only after master enable  
- [ ] Device/branch access validated for warehouse staff  
- [ ] Conflict resolve UI/API exercised (`accept_server` / `accept_client`)  
- [ ] Rollback plan: unset inventory env flag (queue rejects new inventory items)
