# RATIB ERP — Phase D.2 / D.3 Enterprise Branch Installers

**Phase D.3 Universal:** auto OS/arch/PHP/port/firewall/services, bundled runtime, health rollback, scheduled backup/recovery.

**Constraints:** packaging and ops only. No Controllers / Services / Models / Routes / Views / HybridRuntime / HybridSyncEngine changes.

## Install destinations

| Platform | Path |
|----------|------|
| Windows | `C:\Program Files\RATIB Branch\` |
| Linux | `/opt/ratib-branch/` |

## Deliverables

| Artifact | Builder |
|----------|---------|
| `RATIB-Branch-Setup.exe` | `windows/build.ps1` (Inno Setup 6) |
| `RATIB-Branch-Setup.zip` | `windows/build-bootstrap.ps1` (interim if Inno missing) |
| `ratib-branch-installer.run` | `linux-run/build-run.sh` |
| `ratib-branch-installer.deb` | `deb/build-deb.sh` |
| `ratib-branch-installer.rpm` | `rpm/build-rpm.sh` (needs `rpmbuild`) |

Output: `storage/branch/enterprise-installers/`

## Universal entrypoints

- Linux: `universal/install-universal.sh <staged>`
- Windows: `universal/install-universal.ps1`

## Phase D.4 Zero-Touch

Desktop shortcut **RATIB ERP**, tray indicator, auto browser, connectivity status — see `docs/branch-appliance/zero-touch/`.

Verify: `php bin/hybrid-phase-d4-enterprise-verify.php`
