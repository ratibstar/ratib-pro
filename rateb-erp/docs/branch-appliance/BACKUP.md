# Backup Guide

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-backup.php --label=nightly
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-backup.php list
```

Features: WAL checkpoint, SHA-256 metadata, integrity verification, rotation (keep 10), point-in-time meta under `storage/branch/backups/meta/`.

Restore:

```bash
php bin/hybrid-branch-backup.php restore storage/branch/backups/rateb-YYYYMMDD-HHMMSS-label.sqlite
```

Restore creates a `pre-restore` safety backup first.
