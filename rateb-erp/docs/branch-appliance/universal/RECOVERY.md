# Recovery Guide (Universal)

## Automatic

Hourly watchdog runs `bin/hybrid-branch-recover.php` (systemd timer / Windows Task).

If SQLite fails integrity check → restore latest backup → verify → restart Web + Sync services.

## Manual

```bash
cd /opt/rateb-branch   # or Windows install root
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-recover.php
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-backup.php list
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-backup.php restore <path>
```

## Power failure

Services are `Restart=always` / WinSW onfailure restart. Outbox remains in SQLite until sync succeeds (exactly-once via existing Hybrid Sync Engine).
