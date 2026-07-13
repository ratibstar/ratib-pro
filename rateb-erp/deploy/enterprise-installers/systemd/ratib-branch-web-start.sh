#!/usr/bin/env bash
# Phase D.3 — start Branch Web reading appliance.env port (no Core edits).
set -euo pipefail
ROOT="${RATEB_BRANCH_ROOT:-/opt/ratib-branch}"
PHP_BIN="${RATEB_PHP_BIN:-/usr/bin/php}"
PORT=8088
APP_ENV="${ROOT}/storage/branch/appliance.env"
if [[ -f "${APP_ENV}" ]]; then
  # shellcheck disable=SC1090
  set -a; . "${APP_ENV}"; set +a
  PORT="${RATEB_BRANCH_HTTP_PORT:-${PORT}}"
  PHP_BIN="${RATEB_PHP_BIN:-${PHP_BIN}}"
fi
exec "${PHP_BIN}" -d extension=pdo_sqlite -d extension=sqlite3 -d extension=gd \
  "${ROOT}/bin/hybrid-branch-serve.php" --host=127.0.0.1 --port="${PORT}"
