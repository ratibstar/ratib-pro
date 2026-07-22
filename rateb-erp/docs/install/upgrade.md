# RATEB Branch — Upgrade

## Rules

- **Never delete** existing SQLite
- Preserve `storage/` (users, settings, outbox, logs, backups)
- Replace application files only
- Pre-upgrade backup → `storage/branch/backups/pre-upgrade-<UTC>.sqlite`

## Windows

Re-run `RATEB-Branch-Setup.exe` (or `/SILENT`). Inno Setup overwrites app files; `storage\branch\*.sqlite` is excluded from wipe.

## Linux .run

Re-run `sudo bash rateb-branch-installer.run`. `install-common.sh` backs up SQLite then rsyncs app files excluding `storage/`.

## .deb / .rpm

```bash
sudo dpkg -i rateb-branch-installer.deb
sudo rpm -Uvh rateb-branch-installer.rpm
```

`preinst` / `%pre` create the SQLite backup before package content updates.

## After upgrade

```bash
sudo systemctl restart rateb-hybrid-sync rateb-branch-web
# or Windows: restart RATEB Hybrid Sync / RATEB Branch Web
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-health.php --once
```
