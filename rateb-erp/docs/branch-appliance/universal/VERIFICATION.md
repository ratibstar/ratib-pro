# Verification matrix (Phase D.3)

Static suite (always):

```bash
php bin/hybrid-phase-d3-enterprise-verify.php
```

Evidence: `storage/branch/phase-d3-enterprise-verify.json`

## Runtime matrix (customer / QA hosts)

| Check | Windows 10/11/2019/2022 | Ubuntu/Debian | Alma/Rocky/OL/RHEL/Fedora |
|-------|-------------------------|---------------|---------------------------|
| Fresh install | Setup.exe / zip | .run / .deb | .run / .rpm |
| Upgrade preserves SQLite | Y | Y | Y |
| Repair / re-run | Y | Y | Y |
| Offline ERP after disconnect | Y | Y | Y |
| Online sync resume | Hybrid Sync service | systemd unit | systemd unit |
| Backup daily/weekly/monthly | Task Scheduler | systemd timers | systemd timers |
| Recovery watchdog | hourly task | hourly timer | hourly timer |
| Service restart / power loss | WinSW onfailure | Restart=always | Restart=always |
| Exactly-once sync | existing HybridSyncEngine | same | same |
| No business-layer regressions | D3_frozen_layers_present | same | same |

## Rollback instructions

- **Fresh install health fail:** installer removes incomplete tree (Windows) or restores prior app snapshot (Linux upgrade).
- **Manual:** see `docs/branch-appliance/universal/RECOVERY.md` and `docs/install/uninstall.md`.
