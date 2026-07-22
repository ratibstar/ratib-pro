#!/usr/bin/env bash
# Shared Linux install / upgrade / uninstall for Branch Appliance.
# Install root: /opt/ratib-branch
set -euo pipefail

INSTALL_ROOT="${RATEB_BRANCH_ROOT:-/opt/ratib-branch}"
SERVICE_USER="${RATEB_BRANCH_USER:-rateb}"
SERVICE_GROUP="${RATEB_BRANCH_GROUP:-rateb}"
PHP_BIN="${RATEB_PHP_BIN:-$(command -v php)}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

php_flags=(-d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd -d extension=mbstring)

ensure_user() {
  if ! id "${SERVICE_USER}" >/dev/null 2>&1; then
    useradd --system --home "${INSTALL_ROOT}" --shell /usr/sbin/nologin "${SERVICE_USER}" 2>/dev/null \
      || useradd --system --home "${INSTALL_ROOT}" --shell /sbin/nologin "${SERVICE_USER}"
  fi
}

ensure_dirs() {
  mkdir -p \
    "${INSTALL_ROOT}/storage/branch/logs" \
    "${INSTALL_ROOT}/storage/branch/backups" \
    "${INSTALL_ROOT}/storage/branch/tmp" \
    "${INSTALL_ROOT}/storage/sessions"
  chown -R "${SERVICE_USER}:${SERVICE_GROUP}" "${INSTALL_ROOT}/storage"
  chmod -R u+rwX,g+rwX "${INSTALL_ROOT}/storage"
}

backup_sqlite_if_present() {
  local db="${INSTALL_ROOT}/storage/branch/rateb-branch.sqlite"
  if [[ -f "${db}" ]]; then
    local stamp
    stamp="$(date -u +%Y%m%d%H%M%S)"
    local dest="${INSTALL_ROOT}/storage/branch/backups/pre-upgrade-${stamp}.sqlite"
    mkdir -p "$(dirname "${dest}")"
    cp -a "${db}" "${dest}"
    echo "Backed up SQLite → ${dest}"
  fi
}

run_cold_start() {
  # Never pass --force on upgrade; existing DB must be preserved.
  if [[ -f "${INSTALL_ROOT}/storage/branch/rateb-branch.sqlite" ]]; then
    echo "Existing SQLite preserved (upgrade path)."
    return 0
  fi
  cd "${INSTALL_ROOT}"
  "${PHP_BIN}" "${php_flags[@]}" bin/hybrid-branch-install.php
}

install_systemd() {
  local unit_src="${INSTALL_ROOT}/deploy/enterprise-installers/systemd"
  install -m 0644 "${unit_src}/ratib-hybrid-sync.service" /etc/systemd/system/ratib-hybrid-sync.service
  install -m 0644 "${unit_src}/ratib-branch-web.service" /etc/systemd/system/ratib-branch-web.service
  systemctl daemon-reload
  systemctl enable ratib-hybrid-sync.service ratib-branch-web.service
  systemctl restart ratib-hybrid-sync.service ratib-branch-web.service || systemctl start ratib-hybrid-sync.service ratib-branch-web.service
}

desktop_launcher() {
  local desk="/usr/share/applications/ratib-branch.desktop"
  cat > "${desk}" <<EOF
[Desktop Entry]
Type=Application
Name=RATEB Branch
Comment=RATEB ERP Branch Appliance
Exec=xdg-open http://127.0.0.1/
Icon=utilities-terminal
Terminal=false
Categories=Office;Finance;
EOF
  chmod 0644 "${desk}"
}

verify() {
  bash "${SCRIPT_DIR}/verify-install.sh" "${INSTALL_ROOT}"
}

cmd_install_from_staged() {
  local staged="${1:?staged payload dir}"
  ensure_user
  backup_sqlite_if_present
  mkdir -p "${INSTALL_ROOT}"
  # Upgrade: copy app files, preserve storage/
  if [[ -d "${INSTALL_ROOT}/storage" ]]; then
    rsync -a --exclude 'storage/' "${staged}/" "${INSTALL_ROOT}/" \
      || (cd "${staged}" && tar cf - --exclude=storage . | (cd "${INSTALL_ROOT}" && tar xf -))
  else
    rsync -a "${staged}/" "${INSTALL_ROOT}/" \
      || (cp -a "${staged}/." "${INSTALL_ROOT}/")
  fi
  ensure_dirs
  run_cold_start
  # Phase D.3 universal orchestrator handles systemd / desktop / verify / browser
  if [[ "${RATEB_UNIVERSAL:-0}" == "1" ]]; then
    echo "OK staged+cold-start at ${INSTALL_ROOT} (universal continues)"
    return 0
  fi
  install_systemd
  desktop_launcher
  verify
  if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "http://127.0.0.1/" >/dev/null 2>&1 || true
  fi
  echo "OK installed at ${INSTALL_ROOT}"
}

cmd_uninstall() {
  local keep_db="${1:-ask}"
  systemctl stop ratib-branch-web.service ratib-hybrid-sync.service 2>/dev/null || true
  systemctl disable ratib-branch-web.service ratib-hybrid-sync.service 2>/dev/null || true
  rm -f /etc/systemd/system/ratib-branch-web.service /etc/systemd/system/ratib-hybrid-sync.service
  systemctl daemon-reload || true
  rm -f /usr/share/applications/ratib-branch.desktop

  if [[ "${keep_db}" == "ask" ]]; then
    read -r -p "Keep Branch Database? [Y/n] " ans || true
    case "${ans:-Y}" in
      n|N|no|NO) keep_db="no" ;;
      *) keep_db="yes" ;;
    esac
  fi

  if [[ "${keep_db}" == "yes" ]]; then
    echo "Keeping storage (SQLite, backups, logs)."
    find "${INSTALL_ROOT}" -mindepth 1 -maxdepth 1 ! -name storage -exec rm -rf {} +
  else
    rm -rf "${INSTALL_ROOT}"
  fi
  echo "OK uninstalled (keep_db=${keep_db})"
}

case "${1:-}" in
  install) shift; cmd_install_from_staged "$@" ;;
  uninstall) shift; cmd_uninstall "${1:-ask}" ;;
  ensure-dirs) ensure_user; ensure_dirs ;;
  cold-start) run_cold_start ;;
  systemd) install_systemd ;;
  verify) verify ;;
  *)
    echo "Usage: $0 {install <staged>|uninstall [yes|no|ask]|ensure-dirs|cold-start|systemd|verify}" >&2
    exit 1
    ;;
esac
