# Recovery Guide

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-recover.php
```

Recovers:

- Stale / corrupted sync daemon locks  
- Stop-file remnants  
- Interrupted SQLite transactions (rollback)  
- WAL checkpoint  
- Interrupted sync (`resumeInterrupted`)  
- Integrity check  

Report: `storage/branch/recovery/last-recovery.json`.

For data loss, use Backup Guide restore.
