# Administrator Guide (Universal)

## Paths

| | Windows | Linux |
|--|---------|-------|
| Root | `C:\Program Files\RATIB Branch` | `/opt/ratib-branch` |
| SQLite | `storage\branch\rateb-branch.sqlite` | same under root |
| Config | `storage\branch\serve.env`, `appliance.env` | same |
| Summary | `storage\branch\post-install.html` | same |

## Services

- **RATIB Branch Web** / `ratib-branch-web` — local PHP server (`appliance.env` port)
- **RATIB Hybrid Sync** / `ratib-hybrid-sync` — Always-On sync daemon

## Backups

Daily / weekly / monthly scheduled. Manual:

`php bin/hybrid-branch-backup.php --label=manual`

## Uninstall

Keep database by default — see `docs/install/uninstall.md`.

## Security

Cold-start generates sync key, device/branch identity, serve.env. Do not copy `serve.env` to cloud hosts.
