#!/usr/bin/env bash
# Phase D.3 — resolve PHP: system (ok) → bundled → auto-install packages.
set -euo pipefail
INSTALL_ROOT="${1:-${RATEB_BRANCH_ROOT:-/opt/ratib-branch}}"
REQUIRED_EXTS=(pdo_sqlite sqlite3 openssl gd curl zip json)

php_ok() {
  local bin="$1"
  [[ -x "${bin}" ]] || return 1
  "${bin}" -r 'exit(version_compare(PHP_VERSION,"8.2.0",">=") ? 0 : 1);' 2>/dev/null || return 1
  local m
  m="$("${bin}" -m 2>/dev/null || true)"
  local e
  for e in "${REQUIRED_EXTS[@]}"; do
    echo "${m}" | grep -qi "^${e}$" || return 1
  done
  return 0
}

try_install_pkgs() {
  if command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y php-cli php-sqlite3 php-gd php-curl php-zip php-mbstring php-xml php-json sqlite3 openssl unzip curl
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y php-cli php-pdo php-sqlite3 php-gd php-curl php-zip php-mbstring php-xml php-json sqlite openssl unzip curl
  elif command -v yum >/dev/null 2>&1; then
    yum install -y php-cli php-pdo php-sqlite3 php-gd php-curl php-zip php-mbstring php-xml php-json sqlite openssl unzip curl
  else
    return 1
  fi
}

BUNDLED="${INSTALL_ROOT}/runtime/php/bin/php"
[[ -x "${BUNDLED}" ]] || BUNDLED="${INSTALL_ROOT}/runtime/php/php"

if command -v php >/dev/null 2>&1 && php_ok "$(command -v php)"; then
  echo "$(command -v php)"
  exit 0
fi
if php_ok "${BUNDLED}"; then
  echo "${BUNDLED}"
  exit 0
fi

echo "PHP incomplete — installing packages..." >&2
try_install_pkgs || true
if command -v php >/dev/null 2>&1 && php_ok "$(command -v php)"; then
  echo "$(command -v php)"
  exit 0
fi
if php_ok "${BUNDLED}"; then
  echo "${BUNDLED}"
  exit 0
fi

echo "ERROR: no usable PHP 8.2+ with required extensions. Place bundled runtime at ${INSTALL_ROOT}/runtime/php/" >&2
exit 1
