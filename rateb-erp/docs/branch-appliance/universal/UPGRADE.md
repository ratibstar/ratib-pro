# Upgrade Guide (Universal)

- Re-run the same installer package.
- SQLite, users, settings, outbox, audit, logs, backups are **never deleted**.
- Pre-upgrade copy: `storage/branch/backups/pre-upgrade-<UTC>.sqlite`
- Application files only are replaced.
- Services restart automatically; Hybrid Sync resumes when online.

Rollback of app tree (Linux): failed health check restores previous app snapshot; storage kept.
