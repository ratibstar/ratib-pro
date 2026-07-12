# Phase C.1 — Production readiness & rollback

## Production readiness

| Item | Status |
|------|--------|
| Sync Engine reused (no duplicate) | Required / verified by C1 enterprise suite |
| Cloud MySQL path unchanged | Required / mysql e2e regression |
| Branch SQLite + WAL / busy_timeout | Unchanged (Phases A–B.2) |
| Always-On Linux systemd | `deploy/systemd/rateb-hybrid-sync.service` |
| Always-On Windows Service | WinSW installer (not Task Scheduler) |
| Structured JSON logs | `storage/branch/logs/hybrid-sync.jsonl` |
| Single-instance flock | `storage/branch/hybrid-sync.daemon.lock` |
| Graceful stop | SIGTERM/SIGINT or `php bin/hybrid-sync-service.php --stop` |
| Crash recovery | `resumeInterrupted()` on start + each cycle |

### Install (Linux)

```bash
sudo cp deploy/systemd/rateb-hybrid-sync.service /etc/systemd/system/
# Edit WorkingDirectory / User / EnvironmentFile paths
sudo systemctl daemon-reload
sudo systemctl enable --now rateb-hybrid-sync.service
sudo systemctl status rateb-hybrid-sync.service
```

### Install (Windows)

1. Download WinSW → `bin/windows/RatebHybridSync.exe`
2. Run PowerShell as Administrator: `.\bin\windows\install-hybrid-sync-service.ps1 -PhpPath C:\path\to\php.exe`

### Branch `.env` only

```
RATEB_RUNTIME=branch
RATEB_HYBRID_SYNC_ENABLED=1
RATEB_HYBRID_SYNC_SINK=mysql
```

Do **not** enable branch runtime on cloud.

## Rollback plan

1. Stop service: `systemctl stop rateb-hybrid-sync` or WinSW `RatebHybridSync.exe stop`
2. Disable: `systemctl disable rateb-hybrid-sync`
3. Optional temporary drain: `php bin/hybrid-phase-c-sync-drain.php` (cron-style Phase C)
4. Git rollback of C.1 commits only (daemon/service wrappers); Phase C engine remains
5. Outbox data in SQLite is preserved — no schema drop required

## Verify before production

```bash
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-phase-c1-enterprise-verify.php
```

Expect: `VERDICT: ENTERPRISE_PASS`
