# RATEB Branch — Windows Installer

Artifact: **RATEB-Branch-Setup.exe**

## Phase D.3 Universal

Zero-touch install: auto PHP (or bundled), auto port, firewall, services, backup timers, health rollback.

See `docs/branch-appliance/universal/INSTALLATION.md`.

## Build

1. Install [Inno Setup 6](https://jrsoftware.org/isinfo.php)
2. Place WinSW as `deploy/enterprise-installers/windows/RATEBHybridSync.exe` and `RATEBBranchWeb.exe`
3. Run:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\enterprise-installers\windows\build.ps1
```

Output: `storage/branch/enterprise-installers/RATEB-Branch-Setup.exe`

## Install

- GUI: run `RATEB-Branch-Setup.exe` as Administrator
- Silent: `RATEB-Branch-Setup.exe /SILENT`
- Destination: `C:\Program Files\RATEB Branch\`

## Flow

1. OS check (Win10+ / Server 2019+, 64-bit)
2. Copy ERP + optional bundled PHP under `runtime\php`
3. Create `storage\branch\{logs,backups,tmp}`
4. `bin\hybrid-branch-install.php` (skipped if SQLite exists)
5. Register **RATEB Hybrid Sync** + **RATEB Branch Web** (WinSW)
6. Firewall rule (optional task)
7. Verify + open `http://127.0.0.1/`
8. Desktop + Start Menu shortcuts

## Repair / upgrade

Re-run the setup. Application files are replaced; **SQLite is never deleted**.
