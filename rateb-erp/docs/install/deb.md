# RATEB Branch — Debian / Ubuntu (.deb)

Artifact: **rateb-branch-installer.deb**

## Build

```bash
bash deploy/enterprise-installers/deb/build-deb.sh
```

Requires `dpkg-deb` (or `ar` fallback).

## Install

```bash
sudo apt-get install -y ./rateb-branch-installer.deb
# or
sudo dpkg -i rateb-branch-installer.deb
sudo apt-get install -f -y
```

Install root: `/opt/rateb-branch/`

## Maintainer scripts

| Script | Role |
|--------|------|
| `preinst` | Backup SQLite on upgrade; stop services |
| `postinst` | Cold-start if needed; `systemctl enable/start` |
| `prerm` | Stop/disable services |
| `postrm` | Remove units; keep `storage/` unless `RATEB_PURGE_STORAGE=1` on purge |
