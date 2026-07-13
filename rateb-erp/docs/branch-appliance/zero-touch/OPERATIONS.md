# Operations (Zero-Touch)

## Status file

`storage/branch/status.json` — updated every ~3s by `hybrid-zero-touch-status.php`.

## Tray

- Windows: Notification Area (startup via `RATIB Tray` shortcut)  
- Linux: system tray (Qt) or notify-send fallback; autostart desktop entry  

## Support package

Tray → **Export Support Package** → `storage/branch/support/ratib-support-*.zip` (sync keys redacted).

## Services

| Windows | Linux |
|---------|-------|
| RATIB Branch Web | `ratib-branch-web` |
| RATIB Hybrid Sync | `ratib-hybrid-sync` |
| Zero-Touch Status task | `ratib-zero-touch-status` |
| Backup / Recover tasks | backup + recover timers |

## Verify

```bash
php bin/hybrid-phase-d4-enterprise-verify.php
```
