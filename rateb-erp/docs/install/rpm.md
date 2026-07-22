# RATEB Branch — RHEL family (.rpm)

Artifact: **rateb-branch-installer.rpm**

Supports: RHEL, AlmaLinux, Rocky, Oracle Linux, Fedora.

## Build

```bash
bash deploy/enterprise-installers/rpm/build-rpm.sh
```

Requires `rpmbuild`. Without it, the script stages the SPEC + payload tar for a build host.

## Install

```bash
sudo dnf install -y ./rateb-branch-installer.rpm
# or
sudo rpm -Uvh rateb-branch-installer.rpm
```

Install root: `/opt/rateb-branch/`

## Spec hooks

- `%pre` — backup SQLite; stop services
- `%post` — user `rateb`, cold-start, enable/start systemd units
- `%preun` / `%postun` — stop services; keep `storage/` unless `RATEB_PURGE_STORAGE=1`
