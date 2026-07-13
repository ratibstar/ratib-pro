#!/usr/bin/env bash
# Phase D.3 — Universal Linux install with rollback on health failure.
set -euo pipefail

INSTALL_ROOT="${RATEB_BRANCH_ROOT:-/opt/ratib-branch}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
COMMON="$(cd "${SCRIPT_DIR}/../common" && pwd)"
STAGED="${1:?staged ERP tree}"
ROLLBACK_DIR=""
FRESH_INSTALL=0

log() { echo "[universal] $*"; }

rollback() {
  log "ROLLBACK — health/verify failed"
  systemctl stop ratib-branch-web.service ratib-hybrid-sync.service 2>/dev/null || true
  systemctl disable ratib-branch-web.service ratib-hybrid-sync.service 2>/dev/null || true
  if [[ "${FRESH_INSTALL}" -eq 1 && -n "${ROLLBACK_DIR}" && -d "${ROLLBACK_DIR}" ]]; then
    rm -rf "${INSTALL_ROOT}"
    mv "${ROLLBACK_DIR}" "${INSTALL_ROOT}" 2>/dev/null || true
    log "Restored previous tree from ${ROLLBACK_DIR}"
  elif [[ "${FRESH_INSTALL}" -eq 1 ]]; then
    # Keep storage if any pre-existing; remove only if we created everything
    if [[ ! -f "${INSTALL_ROOT}/storage/branch/.pre-existing" ]]; then
      rm -rf "${INSTALL_ROOT}"
      log "Removed incomplete install at ${INSTALL_ROOT}"
    fi
  fi
  exit 1
}

trap 'rc=$?; [[ $rc -ne 0 ]] && rollback || true' ERR

[[ "$(id -u)" -eq 0 ]] || { echo "Run as root"; exit 1; }

bash "${SCRIPT_DIR}/detect-platform.sh" > /tmp/ratib-platform.env
log "platform: $(tr '\n' ' ' </tmp/ratib-platform.env)"

# Snapshot existing install for upgrade rollback of app files only (storage kept)
if [[ -d "${INSTALL_ROOT}" && -f "${INSTALL_ROOT}/VERSION" ]]; then
  ROLLBACK_DIR="$(mktemp -d /tmp/ratib-rollback-XXXXXX)"
  rsync -a --exclude 'storage/' "${INSTALL_ROOT}/" "${ROLLBACK_DIR}/" 2>/dev/null \
    || (mkdir -p "${ROLLBACK_DIR}" && cp -a "${INSTALL_ROOT}/." "${ROLLBACK_DIR}/" && rm -rf "${ROLLBACK_DIR}/storage")
  FRESH_INSTALL=0
  log "Upgrade mode — storage preserved"
else
  FRESH_INSTALL=1
  [[ -f "${INSTALL_ROOT}/storage/branch/rateb-branch.sqlite" ]] && touch "${INSTALL_ROOT}/storage/branch/.pre-existing"
fi

# Resolve PHP (may install packages or use bundled)
PHP_BIN="$(bash "${SCRIPT_DIR}/resolve-php.sh" "${STAGED}")"
export RATEB_PHP_BIN="${PHP_BIN}"
log "php=${PHP_BIN}"

PORT="$(bash "${SCRIPT_DIR}/detect-port.sh")"
log "port=${PORT}"

# Install via common (copy + cold-start only; universal finishes services)
export RATEB_BRANCH_ROOT="${INSTALL_ROOT}"
export RATEB_UNIVERSAL=1
bash "${COMMON}/install-common.sh" install "${STAGED}"

# Re-resolve PHP against final install root (bundled may live there)
PHP_BIN="$(bash "${SCRIPT_DIR}/resolve-php.sh" "${INSTALL_ROOT}")"
export RATEB_PHP_BIN="${PHP_BIN}"

# Rewrite web unit to use start wrapper + detected PHP
install -m 0755 "${SCRIPT_DIR}/../systemd/ratib-branch-web-start.sh" "${INSTALL_ROOT}/deploy/enterprise-installers/systemd/ratib-branch-web-start.sh"

bash "${SCRIPT_DIR}/write-appliance-config.sh" "${INSTALL_ROOT}" "${PORT}" "${PHP_BIN}"

# Patch systemd web service ExecStart to wrapper
cat > /etc/systemd/system/ratib-branch-web.service <<EOF
[Unit]
Description=RATIB Branch Web
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=rateb
Group=rateb
WorkingDirectory=${INSTALL_ROOT}
Environment=RATEB_BRANCH_ROOT=${INSTALL_ROOT}
Environment=RATEB_PHP_BIN=${PHP_BIN}
EnvironmentFile=-${INSTALL_ROOT}/storage/branch/serve.env
EnvironmentFile=-${INSTALL_ROOT}/storage/branch/appliance.env
ExecStart=${INSTALL_ROOT}/deploy/enterprise-installers/systemd/ratib-branch-web-start.sh
Restart=always
RestartSec=5
AmbientCapabilities=CAP_NET_BIND_SERVICE
CapabilityBoundingSet=CAP_NET_BIND_SERVICE
ReadWritePaths=${INSTALL_ROOT}/storage
SyslogIdentifier=ratib-branch-web

[Install]
WantedBy=multi-user.target
EOF

# Sync unit with resolved PHP
sed "s|/usr/bin/php|${PHP_BIN}|g; s|/opt/ratib-branch|${INSTALL_ROOT}|g" \
  "${INSTALL_ROOT}/deploy/enterprise-installers/systemd/ratib-hybrid-sync.service" \
  > /etc/systemd/system/ratib-hybrid-sync.service

bash "${SCRIPT_DIR}/configure-firewall.sh" "${PORT}"
bash "${SCRIPT_DIR}/schedule-backups.sh" "${INSTALL_ROOT}" "${PHP_BIN}"

# Phase D.4 zero-touch desktop + tray + status monitor
if [[ -x "${INSTALL_ROOT}/deploy/enterprise-installers/zero-touch/linux/install-zero-touch.sh" ]]; then
  bash "${INSTALL_ROOT}/deploy/enterprise-installers/zero-touch/linux/install-zero-touch.sh" "${INSTALL_ROOT}" "${PHP_BIN}" || true
elif [[ -f "${SCRIPT_DIR}/../zero-touch/linux/install-zero-touch.sh" ]]; then
  bash "${SCRIPT_DIR}/../zero-touch/linux/install-zero-touch.sh" "${INSTALL_ROOT}" "${PHP_BIN}" || true
fi

systemctl daemon-reload
systemctl enable ratib-hybrid-sync.service ratib-branch-web.service
systemctl restart ratib-hybrid-sync.service ratib-branch-web.service

# Desktop launcher via zero-touch (RATIB ERP); keep legacy alias
URL="http://127.0.0.1:${PORT}/"
[[ "${PORT}" == "80" ]] && URL="http://127.0.0.1/"
LAUNCHER="${INSTALL_ROOT}/deploy/enterprise-installers/zero-touch/linux/ratib-launcher.sh"
if [[ -x "${LAUNCHER}" ]]; then
  cat > /usr/share/applications/ratib-erp.desktop <<EOF
[Desktop Entry]
Type=Application
Name=RATIB ERP
Exec=${LAUNCHER}
Icon=applications-office
Terminal=false
Categories=Office;Finance;
EOF
fi

# Health — fail triggers rollback via ERR trap
export RATEB_BRANCH_HTTP_PORT="${PORT}"
bash "${COMMON}/verify-install.sh" "${INSTALL_ROOT}" || rollback

# Update summary health
sed -i 's/>pending</>ok</' "${INSTALL_ROOT}/storage/branch/post-install.html" 2>/dev/null || true

trap - ERR
rm -rf "${ROLLBACK_DIR}" 2>/dev/null || true

# Open via launcher (starts tray + browser)
if [[ -x "${LAUNCHER}" ]]; then
  RATEB_NO_TRAY=0 "${LAUNCHER}" || true
else
  command -v xdg-open >/dev/null && xdg-open "${URL}" >/dev/null 2>&1 || true
fi
log "OK Universal+ZeroTouch install complete → ${URL}"
cat "${INSTALL_ROOT}/storage/branch/appliance.env"
