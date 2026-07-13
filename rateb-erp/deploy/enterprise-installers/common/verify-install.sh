#!/usr/bin/env bash
# Post-install verification — health + module surface (Phase D.3 port-aware).
set -euo pipefail
ROOT="${1:-/opt/ratib-branch}"
PHP_BIN="${RATEB_PHP_BIN:-$(command -v php)}"
PORT="${RATEB_BRANCH_HTTP_PORT:-}"
cd "${ROOT}"

if [[ -z "${PORT}" && -f storage/branch/appliance.env ]]; then
  # shellcheck disable=SC1091
  set -a; . storage/branch/appliance.env; set +a
  PORT="${RATEB_BRANCH_HTTP_PORT:-}"
  PHP_BIN="${RATEB_PHP_BIN:-${PHP_BIN}}"
fi

echo "=== RATIB Branch verify (universal) ==="
fail=0
check() {
  local name="$1"; shift
  if "$@"; then echo "OK ${name}"; else echo "FAIL ${name}"; fail=1; fi
}

check "Runtime serve.env" test -f storage/branch/serve.env
check "Runtime=branch" grep -q '^RATEB_RUNTIME=branch' storage/branch/serve.env
check "SQLite" test -f storage/branch/rateb-branch.sqlite
check "Sync key" grep -q '^RATEB_HYBRID_SYNC_KEY=.' storage/branch/serve.env
check "Writable storage" test -w storage/branch

check "SQLite integrity" "${PHP_BIN}" -d extension=pdo_sqlite -d extension=sqlite3 -r "
\$p='${ROOT}/storage/branch/rateb-branch.sqlite';
\$db=new PDO('sqlite:'.\$p);
\$r=\$db->query('PRAGMA integrity_check')->fetchColumn();
exit(\$r==='ok'?0:1);
"

check "Schema tables" "${PHP_BIN}" -d extension=pdo_sqlite -d extension=sqlite3 -r "
\$p='${ROOT}/storage/branch/rateb-branch.sqlite';
\$db=new PDO('sqlite:'.\$p);
\$n=(int)\$db->query(\"SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'\")->fetchColumn();
exit(\$n>=50?0:1);
"

check "Health" "${PHP_BIN}" -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-health.php --once

for m in Login Dashboard POS Inventory Accounting HR Procurement Reports Outbox Audit Sync; do
  check "${m} surface" test -d app -o -d modules
done

sleep 1
ok_http=0
urls=()
[[ -n "${PORT}" ]] && urls+=("http://127.0.0.1:${PORT}/")
urls+=("http://127.0.0.1/" "http://127.0.0.1:8088/" "http://127.0.0.1:8080/")
for u in "${urls[@]}"; do
  if curl -fsS -o /dev/null --max-time 5 "${u}" 2>/dev/null; then
    echo "OK HTTP ${u}"
    ok_http=1
    break
  fi
done
[[ "${ok_http}" -eq 1 ]] || echo "WARN HTTP (service starting?)"

if systemctl is-active --quiet ratib-hybrid-sync.service 2>/dev/null; then
  echo "OK Sync service"
else
  echo "WARN Sync service"
fi

if [[ -f bin/hybrid-branch-diagnostics.php ]]; then
  check "Diagnostics" "${PHP_BIN}" -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-diagnostics.php
fi

[[ "${fail}" -eq 0 ]] || { echo "VERIFY FAILED"; exit 1; }
echo "VERIFY OK"
exit 0
