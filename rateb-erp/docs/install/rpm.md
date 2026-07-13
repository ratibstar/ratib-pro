# RATIB Branch — RHEL family (.rpm)

Artifact: **ratib-branch-installer.rpm**

Supports: RHEL, AlmaLinux, Rocky, Oracle Linux, Fedora.

## Build

```bash
bash deploy/enterprise-installers/rpm/build-rpm.sh
```

Requires `rpmbuild`. Without it, the script stages the SPEC + payload tar for a build host.

## Install

```bash
sudo dnf install -y ./ratib-branch-installer.rpm
# or
sudo rpm -Uvh ratib-branch-installer.rpm
```

Install root: `/opt/ratib-branch/`

## Spec hooks

- `%pre` — backup SQLite; stop services
- `%post` — user `rateb`, cold-start, enable/start systemd units
- `%preun` / `%postun` — stop services; keep `storage/` unless `RATIB_PURGE_STORAGE=1`
