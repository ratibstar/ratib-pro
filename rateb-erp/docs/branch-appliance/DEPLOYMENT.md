# Deployment Guide

## Linux

1. Deploy full `rateb-erp` tree to `/var/www/rateb-erp` (or appliance path).
2. Run installer as service user.
3. Install sync unit:

```bash
sudo cp deploy/systemd/rateb-hybrid-sync.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now rateb-hybrid-sync
```

4. Point `WorkingDirectory` / `User` / `EnvironmentFile` at the appliance.

## Windows

1. Install PHP with `pdo_sqlite`.
2. Run `bin/hybrid-branch-appliance-install.php`.
3. Install Sync Service via WinSW: `bin/windows/install-hybrid-sync-service.ps1` (Administrator).

## Cloud

Do **not** set `RATEB_RUNTIME=branch` on cloud hosts. Cloud remains MySQL.

## Packaging

See `deploy/branch-appliance/README.md`.
