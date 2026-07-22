# RATEB Branch — Uninstall

## Prompt

**Keep Branch Database?**

| Answer | Result |
|--------|--------|
| **YES** (default) | Keep SQLite, backups, logs under `storage/` — remove binaries only |
| **NO** | Remove install root entirely |

## Windows

Uninstall from Apps & Features, or:

```powershell
powershell -ExecutionPolicy Bypass -File "C:\Program Files\RATEB Branch\deploy\enterprise-installers\windows\uninstall-branch.ps1" -KeepDatabase ask
```

Silent keep DB: `-KeepDatabase yes`  
Silent wipe: `-KeepDatabase no`

## Linux .run / manual

```bash
sudo bash /opt/rateb-branch/deploy/enterprise-installers/common/install-common.sh uninstall ask
sudo bash .../install-common.sh uninstall yes   # keep DB
sudo bash .../install-common.sh uninstall no    # wipe
```

## .deb

```bash
sudo apt-get remove rateb-branch-installer          # keep storage
sudo RATEB_PURGE_STORAGE=1 apt-get purge rateb-branch-installer
```

## .rpm

```bash
sudo dnf remove rateb-branch-installer             # keep storage
sudo RATEB_PURGE_STORAGE=1 dnf remove rateb-branch-installer
```
