#!/usr/bin/env bash
# Step 1 — Linux requirements. Exit 0 if OK / after auto-install when supported.
set -euo pipefail

need_php() {
  command -v php >/dev/null 2>&1 || return 1
  php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'
}

ext_ok() {
  php -m 2>/dev/null | grep -qi "^${1}$"
}

install_deps() {
  if command -v apt-get >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -y
    apt-get install -y php-cli php-sqlite3 php-gd php-curl php-zip php-mbstring php-xml php-json sqlite3 openssl unzip curl
  elif command -v dnf >/dev/null 2>&1; then
    dnf install -y php-cli php-pdo php-sqlite3 php-gd php-curl php-zip php-mbstring php-xml php-json sqlite openssl unzip curl
  elif command -v yum >/dev/null 2>&1; then
    yum install -y php-cli php-pdo php-sqlite3 php-gd php-curl php-zip php-mbstring php-xml php-json sqlite openssl unzip curl
  else
    echo "ERROR: no supported package manager (apt/dnf/yum)" >&2
    return 1
  fi
}

if ! need_php; then
  echo "PHP 8.2+ missing — attempting install..."
  install_deps
fi
if ! need_php; then
  echo "ERROR: PHP 8.2+ required" >&2
  exit 1
fi

MISSING=()
for e in sqlite3 openssl gd pdo_sqlite curl zip json; do
  if ! ext_ok "${e}"; then
    MISSING+=("${e}")
  fi
done
if [[ ${#MISSING[@]} -gt 0 ]]; then
  echo "Missing PHP extensions: ${MISSING[*]} — attempting install..."
  install_deps
fi
for e in sqlite3 openssl gd pdo_sqlite curl zip json; do
  if ! ext_ok "${e}"; then
    echo "ERROR: PHP extension missing: ${e}" >&2
    exit 1
  fi
done
echo "OK requirements PHP $(php -r 'echo PHP_VERSION;')"
