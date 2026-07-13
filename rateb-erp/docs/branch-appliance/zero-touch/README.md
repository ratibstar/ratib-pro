# Phase D.4 — Zero-Touch Enterprise Experience

Customer never sees SQLite, MySQL, Hybrid Runtime, or env files.

## Flow

1. Install  
2. Desktop shortcut **RATIB ERP**  
3. Double-click → services + tray + browser open automatically  
4. Login and work  

## Online / Offline

| Indicator | Meaning | Browser URL |
|-----------|---------|-------------|
| 🟢 ONLINE | Cloud reachable | `https://rateb.sa/rateb-erp/public/admin/` |
| 🔴 OFFLINE | Cloud unreachable; local ERP continues on SQLite | `http://127.0.0.1:8088/admin` |
| 🟡 SYNCING | Cloud up; outbox draining | Cloud admin (same as online) |
| 🔵 STARTING | Bootstrapping | Local admin |
| ⚪ MAINTENANCE | Recovery / temporary issue | Local admin |

Detection every ~3 seconds (DNS + HTTPS + API health + sync status). Offline within 3–5s.

The topbar badge and `status.json` `open_url` stay in sync: green → cloud admin, red → local admin. The browser auto-switches when the connection state flips (branch / localhost only).

**Architecture (locked):** Local SQLite runtime via `HybridRuntime` is unchanged mid-session. Hybrid Sync Engine remains the only sync path. Online/Offline only chooses which admin URL to open — it does not rewrite `RATEB_RUNTIME` / `serve.env`.

## Components

| Piece | Role |
|-------|------|
| `RatibLauncher` / `ratib-launcher.sh` | Start services, open local URL |
| `RatibTray` / `ratib-tray.py` | Live status + Open / Backup / Diagnostics / Restart |
| `hybrid-zero-touch-status.php` | Writes `storage/branch/status.json` |
| Background services | Web, Hybrid Sync, Health, Backup, Recover, Status monitor |

## Constraints

No Controllers / Services / Models / HybridRuntime / HybridSyncEngine changes.
