#!/usr/bin/env bash
# Phase D.3 — write appliance.env + post-install summary (no Core changes).
set -euo pipefail
INSTALL_ROOT="${1:?}"
PORT="${2:?}"
PHP_BIN="${3:?}"
VERSION="unknown"
[[ -f "${INSTALL_ROOT}/VERSION" ]] && VERSION="$(tr -d '\r\n' < "${INSTALL_ROOT}/VERSION")"

BRANCH_ID=""
SQLITE="${INSTALL_ROOT}/storage/branch/rateb-branch.sqlite"
SERVE="${INSTALL_ROOT}/storage/branch/serve.env"
IDENT="${INSTALL_ROOT}/storage/branch/identity"
if [[ -f "${IDENT}/branch.uuid" ]]; then
  BRANCH_ID="$(tr -d '\r\n' < "${IDENT}/branch.uuid")"
elif [[ -f "${IDENT}/device.json" ]]; then
  BRANCH_ID="$(grep -oE '"branch_uuid"\s*:\s*"[^"]+"' "${IDENT}/device.json" 2>/dev/null | head -1 | sed 's/.*"\([^"]*\)"$/\1/' || true)"
fi
SYNC_KEY_SET=0
grep -q '^RATEB_HYBRID_SYNC_KEY=.' "${SERVE}" 2>/dev/null && SYNC_KEY_SET=1

URL="http://127.0.0.1:${PORT}/admin"
[[ "${PORT}" == "80" ]] && URL="http://127.0.0.1/admin"

mkdir -p "${INSTALL_ROOT}/storage/branch"
cat > "${INSTALL_ROOT}/storage/branch/appliance.env" <<EOF
# Phase D.3 — Universal Branch Appliance (installer-written)
RATEB_BRANCH_HTTP_PORT=${PORT}
RATEB_BRANCH_HTTP_URL=${URL}
RATEB_PHP_BIN=${PHP_BIN}
RATEB_APPLIANCE_VERSION=${VERSION}
RATEB_BRANCH_ID=${BRANCH_ID}
RATEB_CLOUD_URL=https://rateb.sa
EOF

# Append port hint into serve.env if missing (local serve only)
if [[ -f "${SERVE}" ]] && ! grep -q '^RATEB_BRANCH_HTTP_PORT=' "${SERVE}"; then
  printf '\nRATEB_BRANCH_HTTP_PORT=%s\n' "${PORT}" >> "${SERVE}"
fi

SYNC_STATUS="unknown"
systemctl is-active --quiet ratib-hybrid-sync.service 2>/dev/null && SYNC_STATUS="active" || SYNC_STATUS="inactive"
HEALTH="pending"

SUMMARY="${INSTALL_ROOT}/storage/branch/post-install.html"
cat > "${SUMMARY}" <<EOF
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><title>RATIB Branch Installed</title>
<style>body{font-family:Segoe UI,system-ui,sans-serif;max-width:640px;margin:2rem auto;padding:0 1rem;line-height:1.5}
h1{font-size:1.4rem}code{background:#f4f4f4;padding:.1rem .3rem}a.btn{display:inline-block;margin-top:1rem;padding:.6rem 1rem;background:#0b5;color:#fff;text-decoration:none;border-radius:4px}</style>
</head><body>
<h1>RATIB Branch Appliance ready</h1>
<p><strong>URL:</strong> <a href="${URL}">${URL}</a></p>
<p><strong>Branch ID:</strong> <code>${BRANCH_ID:-n/a}</code></p>
<p><strong>SQLite:</strong> <code>${SQLITE}</code></p>
<p><strong>Sync:</strong> <code>${SYNC_STATUS}</code></p>
<p><strong>Health:</strong> <code>${HEALTH}</code></p>
<p><strong>Version:</strong> <code>${VERSION}</code></p>
<p><strong>PHP:</strong> <code>${PHP_BIN}</code></p>
<a class="btn" href="${URL}">Open ERP</a>
</body></html>
EOF

echo "summary=${SUMMARY}"
echo "url=${URL}"
echo "port=${PORT}"
echo "branch_id=${BRANCH_ID}"
