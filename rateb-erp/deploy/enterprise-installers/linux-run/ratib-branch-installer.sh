#!/usr/bin/env bash
# Generic Linux self-extracting installer → ratib-branch-installer.run (Phase D.3 Universal)
set -euo pipefail

INSTALL_ROOT="${RATEB_BRANCH_ROOT:-/opt/ratib-branch}"
MARKER="__RATIB_BRANCH_PAYLOAD_BELOW__"

self_extract() {
  local tmp
  tmp="$(mktemp -d /tmp/ratib-branch-XXXXXX)"
  ARCHIVE_LINE="$(awk "/^${MARKER}\$/ {print NR + 1; exit 0;}" "$0")"
  if [[ -z "${ARCHIVE_LINE}" ]]; then
    echo "ERROR: payload marker not found (build with build-run.sh)" >&2
    exit 1
  fi
  tail -n +"${ARCHIVE_LINE}" "$0" | tar -xzf - -C "${tmp}"
  echo "${tmp}"
}

main() {
  if [[ "$(id -u)" -ne 0 ]]; then
    echo "Run as root (sudo)." >&2
    exit 1
  fi
  echo "=== RATEB Branch Universal Installer (.run) ==="
  if [[ -f /etc/os-release ]]; then
    # shellcheck disable=SC1091
    . /etc/os-release
    echo "Detected: ${NAME:-unknown} ${VERSION_ID:-}"
  fi
  local staged uni
  staged="$(self_extract)"
  uni="${staged}/deploy/enterprise-installers/universal"
  chmod +x "${uni}"/*.sh "${staged}/deploy/enterprise-installers/common"/*.sh 2>/dev/null || true
  bash "${uni}/install-universal.sh" "${staged}"
  rm -rf "${staged}"
}

if [[ "${BASH_SOURCE[0]}" == "$0" ]]; then
  if [[ "${1:-}" == "--from-dir" ]]; then
    bash "$(cd "$(dirname "$0")/../universal" && pwd)/install-universal.sh" "$2"
    exit 0
  fi
  main "$@"
fi
exit 0
