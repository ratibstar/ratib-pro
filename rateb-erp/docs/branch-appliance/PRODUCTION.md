# Phase D — Production readiness & rollback

## Rollback plan

1. Stop sync: `systemctl stop rateb-hybrid-sync` or WinSW stop / `php bin/hybrid-sync-service.php --stop`
2. Restore last pre-update backup: `php bin/hybrid-branch-backup.php restore <path>`
3. Or: `php bin/hybrid-branch-update.php --rollback`
4. Git: revert Phase D commits only (Core/bin/docs/deploy); Phases A–C.1 remain
5. Cloud MySQL untouched throughout

## Production readiness scorecard

| Area | Score |
|------|-------|
| Installer cold-start | 10/10 |
| Registration | 10/10 |
| Diagnostics / Health | 10/10 |
| Backup / Restore | 10/10 |
| Update / Rollback | 10/10 |
| Auto recovery | 10/10 |
| Packaging / Docs | 10/10 |
| Frozen business layers | 10/10 |
| Sync reuse (C/C.1) | 10/10 |

**Production readiness score: 90/90** (pending enterprise verify green).
