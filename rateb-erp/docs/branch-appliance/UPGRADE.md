# Upgrade Guide

```bash
php bin/hybrid-branch-update.php                 # show version
php bin/hybrid-branch-update.php --to=1.0.1
php bin/hybrid-branch-update.php --rollback
```

Flow: pre-update backup → version write → schema verify → sync service stop signal → post diagnostics.

Rollback pointer: `storage/branch/updates/rollback/last.json`.
