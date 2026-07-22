# RATEB Branch — Generic Linux (.run)

Artifact: **rateb-branch-installer.run**

## Build

```bash
bash deploy/enterprise-installers/linux-run/build-run.sh
```

Output: `storage/branch/enterprise-installers/rateb-branch-installer.run`

## Install

```bash
sudo bash rateb-branch-installer.run
```

Supports: Ubuntu, Debian, AlmaLinux, Rocky, Oracle Linux, RHEL, Fedora.

## Flow

1. Detect distro / package manager; install PHP 8.2+ + extensions if missing
2. Extract payload → `/opt/rateb-branch/`
3. Writable `storage/branch/{logs,backups,tmp}`
4. `bin/hybrid-branch-install.php` (cold-start only)
5. systemd: `rateb-hybrid-sync` + `rateb-branch-web` (`Restart=always`, `RestartSec=5`)
6. `.desktop` launcher
7. Verify + open browser

## Dev (no .run)

```bash
sudo bash deploy/enterprise-installers/linux-run/rateb-branch-installer.sh --from-dir /path/to/staged-erp
```
