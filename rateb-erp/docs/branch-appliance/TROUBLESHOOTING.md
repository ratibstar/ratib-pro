# Troubleshooting Guide

| Symptom | Action |
|---------|--------|
| Installer fails extensions | Enable `pdo_sqlite` / `sqlite3` |
| Runtime stays cloud | Check `serve.env` `RATEB_RUNTIME=branch` and `RATEB_ALLOW_RUNTIME_MARKER=1` |
| Sync not draining | `php bin/hybrid-branch-diagnostics.php`; check MySQL sink / internet |
| Daemon already running | Only one instance; clear stale lock via `hybrid-branch-recover.php` |
| Disk full | Free space; backups rotate keep=10 |
| Integrity fail | Restore latest verified backup |
| Cloud accidentally branch | Never set branch env on SaaS; use `RATEB_DEPLOYMENT=cloud` lock |
