#!/usr/bin/env bash
# Phase D.4 — RATIB ERP zero-touch launcher (Linux)
set -euo pipefail
ROOT="${RATEB_BRANCH_ROOT:-/opt/ratib-branch}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_ENV="${ROOT}/storage/branch/appliance.env"
URL="http://127.0.0.1:8088/admin"
PHP_BIN="${RATEB_PHP_BIN:-$(command -v php || true)}"

if [[ -f "${APP_ENV}" ]]; then
  # shellcheck disable=SC1090
  set -a; . "${APP_ENV}"; set +a
  URL="${RATEB_BRANCH_HTTP_URL:-${URL}}"
  PHP_BIN="${RATEB_PHP_BIN:-${PHP_BIN}}"
fi
# Force ERP admin shell (match rateb.sa), never marketing /
case "${URL}" in
  *rateb.sa*|https://*) URL="http://127.0.0.1:8088/admin" ;;
esac
if [[ "${URL}" != */admin && "${URL}" != */admin/ ]]; then
  URL="${URL%/}/admin"
fi

mkdir -p "${ROOT}/storage/branch"
cat > "${ROOT}/storage/branch/status.json" <<EOF
{"phase":"D.4","state":"starting","label":"STARTING","display":"🔵 STARTING","open_url":"${URL}","updated_at":"$(date -u +%Y-%m-%dT%H:%M:%SZ)"}
EOF

systemctl start ratib-branch-web.service ratib-hybrid-sync.service 2>/dev/null || true
systemctl start ratib-zero-touch-status.service 2>/dev/null || true

# Tray (best-effort)
if [[ "${RATIB_NO_TRAY:-0}" != "1" ]]; then
  if command -v python3 >/dev/null 2>&1; then
    if ! pgrep -f 'ratib-tray.py' >/dev/null 2>&1; then
      nohup python3 "${SCRIPT_DIR}/ratib-tray.py" >/dev/null 2>&1 &
    fi
  fi
fi

# Wait for HTTP
for _ in $(seq 1 30); do
  if curl -fsS --max-time 1 "${URL}" >/dev/null 2>&1; then
    break
  fi
  sleep 0.5
done

if [[ "${RATIB_NO_BROWSER:-0}" != "1" ]]; then
  if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "${URL}" >/dev/null 2>&1 || true
  fi
fi
echo "RATIB ERP → ${URL}"
