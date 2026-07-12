# RATEB ERP Offline — Deployment Manifest

| Field | Value |
|-------|-------|
| **Release version** | `erp-offline-v1.0.0` |
| **Feature commit hash** | `2c6b0c3275b5a675210d595cabcef987714419a5` |
| **Feature commit subject** | `update-20260712-125600` |
| **Feature commit time** | `2026-07-12 12:56:01 +0300` |
| **Build timestamp (closure)** | `2026-07-12T13:02:00+03:00` (release closure audit) |
| **Production environment** | `https://rateb.sa` — host `167.233.71.107` — path `/home/admin/domains/rateb.sa/public_html/rateb-erp` |
| **Git branch** | `main` |
| **Rollback reference** | Parent: `96ad925b187244ff986b777aa587644147d91f88` (`deploy-20260712-072115`) |
| **SDK version** | `14.2.0` |
| **Service Worker** | `pos-sw.js` (controlling scope `/rateb-erp/public/`) |
| **SW ERP ops page cache** | `rateb-erp-ops-pages-v14` |
| **SW ERP allowlist cache** | `rateb-erp-ops-allowlist-v14` |
| **SW ERP coexist cache** | `rateb-erp-coexist-v6` |
| **SW POS shell / assets** | `rateb-pos-shell-v8` / `rateb-pos-assets-v8` |
| **IndexedDB name** | `rateb_erp_offline` |
| **IndexedDB DB_VERSION** | `2` |
| **Offline schema version** | IndexedDB stores schema **v2** (`auth_vault` + sync stores); allowlist JSON **version 2** (`paths` + `routes`) |
| **Client bundle** | `public/assets/offline/rateb-offline.js` (+ `.min.js`) |
| **Allowlist artifact** | `public/assets/offline/ops-page-allowlist.json` (142 paths / 142 routes) |

## Database migrations (offline-related)

Applied / required for this release surface (MySQL `admin_rateb-erp`):

| Migration | Purpose |
|-----------|---------|
| `175_offline_sync_meta.sql` | Sync meta |
| `176_offline_entity_cursors.sql` | Entity cursors |
| `177_offline_device_registry.sql` | Device registry |
| `179_offline_device_activation.sql` | Device activation |
| `180_offline_warehouses_updated_at.sql` | Warehouse offline touch |
| `194_offline_identity_hardening.sql` | Identity / trust hardening |

No new migration files were introduced in the closure commit. Runtime tables exercised in validation: `rateb_offline_devices`, `rateb_offline_sync_queue`, `rateb_purchase_requests`, `rateb_purchase_request_items`, `rateb_stock_movements`, `rateb_attendance_records`.

## Deployed / verified identical paths (SHA-256 match repo ↔ production)

- `offline/client/sync/queue-manager.js`
- `offline/client/adapters/auth-lock-adapter.js`
- `offline/client/adapters/shell-adapter.js`
- `offline/server/Services/ErpOfflineAuthPolicy.php`
- `offline/server/Services/ProcurementOfflineReplayService.php`
- `public/assets/offline/rateb-offline.js`
- `public/assets/offline/ops-page-allowlist.json`
- `public/pos-sw.js`
- `public/rateb-offline-sw.js`
- `views/layouts/main.php`

## Feature commit file list (17 paths)

See `git show --name-only 2c6b0c3275b5a675210d595cabcef987714419a5`.
