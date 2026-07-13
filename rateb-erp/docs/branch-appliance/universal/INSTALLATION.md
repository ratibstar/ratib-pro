# Universal Branch Appliance — Installation Guide (Phase D.3)

## Goal

One installer run on a clean Windows or Linux machine → login at localhost. No manual PHP, SQLite, or `.env` editing.

## Supported OS

- Windows 10/11, Server 2019/2022 (x64; ARM64 where PHP available)
- Ubuntu 22.04+/24.04+, Debian 12+, Alma/Rocky/Oracle/RHEL 9+, Fedora

## Install

| Package | Command |
|---------|---------|
| Windows | `RATIB-Branch-Setup.exe` (Admin) or extract `RATIB-Branch-Setup.zip` → `RATIB-Branch-Setup.cmd` |
| Generic Linux | `sudo bash ratib-branch-installer.run` |
| Debian/Ubuntu | `sudo apt install ./ratib-branch-installer.deb` |
| RHEL family | `sudo dnf install ./ratib-branch-installer.rpm` |

## What the Universal installer does

1. Detect OS / CPU / init system  
2. Resolve PHP (system → auto package install → **bundled** `runtime/php`)  
3. Detect free port among 80, 443, 8080, 8088, 8099 (+ fallback)  
4. Copy ERP → `C:\Program Files\RATIB Branch` or `/opt/ratib-branch`  
5. Cold-start SQLite via `bin/hybrid-branch-install.php`  
6. Register Web + Hybrid Sync services (`Restart=always`)  
7. Open firewall for the chosen port  
8. Schedule daily/weekly/monthly backups + recovery watchdog  
9. Health verify — **rollback** if failed  
10. Open browser + `storage/branch/post-install.html` (Branch ID, SQLite path, sync, version)

## Default login (fresh install)

Shown by cold-start output (typically `admin@branch.test` / seed password).
