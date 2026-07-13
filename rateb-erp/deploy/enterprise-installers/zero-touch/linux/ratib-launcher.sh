#!/usr/bin/env bash
# Phase D.4 — RATIB ERP zero-touch launcher (Linux)
# Opens cloud admin when online (green), local admin when offline (red).
set -euo pipefail
ROOT="${RATEB_BRANCH_ROOT:-/opt/ratib-branch}"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APP_ENV="${ROOT}/storage/branch/appliance.env"
LOCAL_URL="http://127.0.0.1:8088/admin"
CLOUD_ADMIN="https://rateb.sa/rateb-erp/public/admin/"
PHP_BIN="${RATEB_PHP_BIN:-$(command -v php || true)}"

if [[ -f "${APP_ENV}" ]]; then
  # shellcheck disable=SC1090
  set -a; . "${APP_ENV}"; set +a
  LOCAL_URL="${RATEB_BRANCH_HTTP_URL:-${LOCAL_URL}}"
  CLOUD_ADMIN="${RATEB_CLOUD_ADMIN_URL:-${CLOUD_ADMIN}}"
  PHP_BIN="${RATEB_PHP_BIN:-${PHP_BIN}}"
fi
# Local branch URL must stay on loopback
case "${LOCAL_URL}" in
  *rateb.sa*|https://*) LOCAL_URL="http://127.0.0.1:8088/admin" ;;
esac
if [[ "${LOCAL_URL}" != */admin && "${LOCAL_URL}" != */admin/ ]]; then
  LOCAL_URL="${LOCAL_URL%/}/admin"
fi
if [[ "${CLOUD_ADMIN}" != */admin && "${CLOUD_ADMIN}" != */admin/ ]]; then
  CLOUD_ADMIN="${CLOUD_ADMIN%/}/admin/"
fi
CLOUD_ADMIN="${CLOUD_ADMIN%/}/"

mkdir -p "${ROOT}/storage/branch"
cat > "${ROOT}/storage/branch/status.json" <<EOF
{"phase":"D.4","state":"starting","label":"STARTING","display":"🔵 STARTING","open_url":"${LOCAL_URL}","local_url":"${LOCAL_URL}","cloud_admin_url":"${CLOUD_ADMIN}","updated_at":"$(date -u +%Y-%m-%dT%H:%M:%SZ)"}
EOF

systemctl start ratib-branch-web.service ratib-hybrid-sync.service 2>/dev/null || true
systemctl start ratib-zero-touch-status.service 2>/dev/null || true

# One-shot status so open_url matches online/offline
if [[ -n "${PHP_BIN}" && -f "${ROOT}/bin/hybrid-zero-touch-status.php" ]]; then
  "${PHP_BIN}" -d extension=pdo_sqlite -d extension=sqlite3 "${ROOT}/bin/hybrid-zero-touch-status.php" >/dev/null 2>&1 || true
fi

# Tray (best-effort)
if [[ "${RATIB_NO_TRAY:-0}" != "1" ]]; then
  if command -v python3 >/dev/null 2>&1; then
    if ! pgrep -f 'ratib-tray.py' >/dev/null 2>&1; then
      nohup python3 "${SCRIPT_DIR}/ratib-tray.py" >/dev/null 2>&1 &
    fi
  fi
fi

# Wait for local HTTP (offline fallback always available)
for _ in $(seq 1 30); do
  if curl -fsS --max-time 1 "${LOCAL_URL}" >/dev/null 2>&1; then
    break
  fi
  sleep 0.5
done

OPEN_URL="${LOCAL_URL}"
if [[ -f "${ROOT}/storage/branch/status.json" ]] && command -v python3 >/dev/null 2>&1; then
  OPEN_URL="$(python3 -c "import json; print(json.load(open('${ROOT}/storage/branch/status.json')).get('open_url','${LOCAL_URL}'))" 2>/dev/null || echo "${LOCAL_URL}")"
fi

if [[ "${RATIB_NO_BROWSER:-0}" != "1" ]]; then
  if command -v xdg-open >/dev/null 2>&1; then
    xdg-open "${OPEN_URL}" >/dev/null 2>&1 || true
  fi
fi
echo "RATIB ERP → ${OPEN_URL}"
