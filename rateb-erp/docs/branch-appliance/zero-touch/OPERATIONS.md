# Operations (Zero-Touch)

## Status file

`storage/branch/status.json` — updated every ~3s by `hybrid-zero-touch-status.php`.

## Tray

- Windows: Notification Area (startup via `RATEB Tray` shortcut)  
- Linux: system tray (Qt) or notify-send fallback; autostart desktop entry  

## Support package

Tray → **Export Support Package** → `storage/branch/support/rateb-support-*.zip` (sync keys redacted).

## Services

| Windows | Linux |
|---------|-------|
| RATEB Branch Web | `rateb-branch-web` |
| RATEB Hybrid Sync | `rateb-hybrid-sync` |
| Zero-Touch Status task | `rateb-zero-touch-status` |
| Backup / Recover tasks | backup + recover timers |

## Verify

```bash
php bin/hybrid-phase-d4-enterprise-verify.php
```
