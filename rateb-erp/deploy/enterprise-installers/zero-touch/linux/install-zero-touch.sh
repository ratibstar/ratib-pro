#!/usr/bin/env bash
# Phase D.4 — install zero-touch desktop entry, status daemon, autostart tray
set -euo pipefail
ROOT="${1:-/opt/ratib-branch}"
PHP_BIN="${2:-$(command -v php)}"
ZT="${ROOT}/deploy/enterprise-installers/zero-touch/linux"
UNI_DIR="$(cd "$(dirname "$0")" && pwd)"

install -m 0755 "${UNI_DIR}/ratib-launcher.sh" "${ZT}/ratib-launcher.sh" 2>/dev/null || true
install -m 0755 "${UNI_DIR}/ratib-tray.py" "${ZT}/ratib-tray.py" 2>/dev/null || true

# Prefer files from this script dir when packaging
SRC="$(cd "$(dirname "$0")" && pwd)"
mkdir -p "${ROOT}/deploy/enterprise-installers/zero-touch/linux"
cp -a "${SRC}/ratib-launcher.sh" "${SRC}/ratib-tray.py" "${ROOT}/deploy/enterprise-installers/zero-touch/linux/"
chmod +x "${ROOT}/deploy/enterprise-installers/zero-touch/linux/"*.sh "${ROOT}/deploy/enterprise-installers/zero-touch/linux/"*.py

# Desktop shortcut
cat > /usr/share/applications/ratib-erp.desktop <<EOF
[Desktop Entry]
Type=Application
Name=RATIB ERP
Comment=RATIB ERP Branch Appliance
Exec=${ROOT}/deploy/enterprise-installers/zero-touch/linux/ratib-launcher.sh
Icon=applications-office
Terminal=false
Categories=Office;Finance;
StartupNotify=true
EOF
chmod 0644 /usr/share/applications/ratib-erp.desktop

# User autostart tray (skel)
mkdir -p /etc/xdg/autostart
cat > /etc/xdg/autostart/ratib-tray.desktop <<EOF
[Desktop Entry]
Type=Application
Name=RATIB Tray
Exec=env RATEB_BRANCH_ROOT=${ROOT} python3 ${ROOT}/deploy/enterprise-installers/zero-touch/linux/ratib-tray.py
X-GNOME-Autostart-enabled=true
EOF

# Status monitor systemd
cat > /etc/systemd/system/ratib-zero-touch-status.service <<EOF
[Unit]
Description=RATIB Zero-Touch Status Monitor
After=network-online.target ratib-branch-web.service
[Service]
Type=simple
User=rateb
Group=rateb
WorkingDirectory=${ROOT}
Environment=RATEB_BRANCH_ROOT=${ROOT}
EnvironmentFile=-${ROOT}/storage/branch/serve.env
EnvironmentFile=-${ROOT}/storage/branch/appliance.env
ExecStart=${PHP_BIN} -d extension=pdo_sqlite -d extension=sqlite3 ${ROOT}/bin/hybrid-zero-touch-status.php --loop --interval=3
Restart=always
RestartSec=5
[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now ratib-zero-touch-status.service

# Default cloud portal URL (customer never edits)
if [[ -f "${ROOT}/storage/branch/appliance.env" ]] && ! grep -q '^RATEB_CLOUD_URL=' "${ROOT}/storage/branch/appliance.env"; then
  echo 'RATEB_CLOUD_URL=https://rateb.sa' >> "${ROOT}/storage/branch/appliance.env"
fi

# Symlink on Desktop for logged-in graphical users (best-effort)
for d in /home/*/Desktop /home/*/سطح\ المكتب; do
  [[ -d "$d" ]] || continue
  ln -sf /usr/share/applications/ratib-erp.desktop "$d/RATIB ERP.desktop" 2>/dev/null || true
done

echo "OK zero-touch Linux desktop + status monitor"
