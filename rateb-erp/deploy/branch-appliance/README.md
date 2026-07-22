# RATEB Branch Appliance — Deployment Package

Customer-ready packaging for Phase D Branch Appliance.

## Contents

| Platform | Artifacts |
|----------|-----------|
| Linux | `deploy/systemd/rateb-hybrid-sync.service`, `bin/hybrid-branch-appliance-install.php`, docs |
| Windows | `bin/windows/*` (WinSW Hybrid Sync), installers, docs |

## One-click install (cold-start, offline)

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-appliance-install.php
```

Production defaults: `RATEB_RUNTIME=branch`, sync enabled, sink=`mysql`.

Offline certification:

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-appliance-install.php --sink=mirror --force
```

## Package builders

- Linux: `bash deploy/branch-appliance/package-linux.sh`
- Windows: `powershell -File deploy/branch-appliance/package-windows.ps1`

Output: `storage/branch/package/`

## Phase D.2 / D.3 — Enterprise installers

Official OS packages (no business-layer changes):

| Artifact | Builder |
|----------|---------|
| `RATEB-Branch-Setup.exe` | `deploy/enterprise-installers/windows/build.ps1` |
| `ratib-branch-installer.run` | `deploy/enterprise-installers/linux-run/build-run.sh` |
| `ratib-branch-installer.deb` | `deploy/enterprise-installers/deb/build-deb.sh` |
| `ratib-branch-installer.rpm` | `deploy/enterprise-installers/rpm/build-rpm.sh` |

**D.3 Universal** auto-detects runtime/port/firewall and rolls back on health failure.  
Docs: `docs/branch-appliance/universal/`. Overview: `deploy/enterprise-installers/README.md`.  
Verify: `php bin/hybrid-phase-d3-enterprise-verify.php`

## Next steps after install

1. `php bin/hybrid-branch-register.php` — registration payload for cloud approval
2. `php bin/hybrid-branch-serve.php` — local ERP UI
3. Enable Hybrid Sync service (systemd / WinSW)
4. `php bin/hybrid-branch-diagnostics.php`
5. `php bin/hybrid-branch-certify.php`

See `docs/branch-appliance/` for full guides.
