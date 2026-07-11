# Phase 13 — Enterprise Master Data Delta Sync

**Flags:** `offline.enabled` + `offline.master_data` (default OFF)

## Entities

- `customer_directory`, `branch_directory`, `warehouse_directory`
- Normalized: `employee_directory`, `supplier_directory` (cursor `updated_at|id`)

## Non-changes

- Phase 10–12 adapters
- POS, queue write path, OfflineReplayEngine write routing
