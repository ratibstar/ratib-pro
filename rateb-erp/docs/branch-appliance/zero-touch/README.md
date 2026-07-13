# Phase D.4 — Zero-Touch Enterprise Experience

Customer never sees SQLite, MySQL, Hybrid Runtime, or env files.

## Flow

1. Install  
2. Desktop shortcut **RATIB ERP**  
3. Double-click → services + tray + browser open automatically  
4. Login and work  

## Online / Offline

| Indicator | Meaning |
|-----------|---------|
| 🟢 ONLINE | Cloud reachable; Hybrid Sync active |
| 🔴 OFFLINE | Cloud unreachable; local ERP continues on SQLite |
| 🟡 SYNCING | Cloud up; outbox draining |
| 🔵 STARTING | Bootstrapping |
| ⚪ MAINTENANCE | Recovery / temporary issue |

Detection every ~3 seconds (DNS + HTTPS + API health + sync status). Offline within 3–5s.

**Architecture (locked):** Branch desktop always works against local SQLite through existing `HybridRuntime`. Online does **not** rewrite runtime variables mid-session (avoids logout/reload). Hybrid Sync Engine remains the only sync path when the cloud returns.

## Components

| Piece | Role |
|-------|------|
| `RatibLauncher` / `ratib-launcher.sh` | Start services, open local URL |
| `RatibTray` / `ratib-tray.py` | Live status + Open / Backup / Diagnostics / Restart |
| `hybrid-zero-touch-status.php` | Writes `storage/branch/status.json` |
| Background services | Web, Hybrid Sync, Health, Backup, Recover, Status monitor |

## Constraints

No Controllers / Services / Models / HybridRuntime / HybridSyncEngine changes.
