# Phase D.4 — Zero-Touch Enterprise Experience

Customer never sees SQLite, MySQL, Hybrid Runtime, or env files.

## Flow

1. Install  
2. Desktop shortcut **RATEB ERP**  
3. Double-click → services + tray + browser open automatically  
4. Login and work  

## Online / Offline

| Indicator | Meaning | Browser URL |
|-----------|---------|-------------|
| 🟢 ONLINE | Cloud reachable | `https://rateb.sa/rateb-erp/public/admin/` |
| 🔴 OFFLINE | Cloud unreachable; PWA offline shell on same origin | **same** cloud admin URL |
| 🟡 SYNCING | Outbox draining | same |
| 🔵 STARTING | Bootstrapping | same |
| ⚪ MAINTENANCE | Recovery | same |

Detection every ~3 seconds. The browser address does **not** switch to `127.0.0.1` — agencies/branches stay on [rateb.sa admin](https://rateb.sa/rateb-erp/public/admin/).

Product nav is **unified lean** (procurement = PR / PO / RFQ / quotations).

**Architecture (locked):** Offline on the cloud origin is PWA / service-worker. Local Hybrid Sync services may still run for appliance sync, but customer UX is always the cloud admin URL.

## Components

| Piece | Role |
|-------|------|
| `RatebLauncher` / `rateb-launcher.sh` | Start services, open local URL |
| `RatebTray` / `rateb-tray.py` | Live status + Open / Backup / Diagnostics / Restart |
| `hybrid-zero-touch-status.php` | Writes `storage/branch/status.json` |
| Background services | Web, Hybrid Sync, Health, Backup, Recover, Status monitor |

## Constraints

No Controllers / Services / Models / HybridRuntime / HybridSyncEngine changes.
