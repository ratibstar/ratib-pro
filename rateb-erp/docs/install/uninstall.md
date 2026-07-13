# RATIB Branch — Uninstall

## Prompt

**Keep Branch Database?**

| Answer | Result |
|--------|--------|
| **YES** (default) | Keep SQLite, backups, logs under `storage/` — remove binaries only |
| **NO** | Remove install root entirely |

## Windows

Uninstall from Apps & Features, or:

```powershell
powershell -ExecutionPolicy Bypass -File "C:\Program Files\RATIB Branch\deploy\enterprise-installers\windows\uninstall-branch.ps1" -KeepDatabase ask
```

Silent keep DB: `-KeepDatabase yes`  
Silent wipe: `-KeepDatabase no`

## Linux .run / manual

```bash
sudo bash /opt/ratib-branch/deploy/enterprise-installers/common/install-common.sh uninstall ask
sudo bash .../install-common.sh uninstall yes   # keep DB
sudo bash .../install-common.sh uninstall no    # wipe
```

## .deb

```bash
sudo apt-get remove ratib-branch-installer          # keep storage
sudo RATIB_PURGE_STORAGE=1 apt-get purge ratib-branch-installer
```

## .rpm

```bash
sudo dnf remove ratib-branch-installer             # keep storage
sudo RATIB_PURGE_STORAGE=1 dnf remove ratib-branch-installer
```
