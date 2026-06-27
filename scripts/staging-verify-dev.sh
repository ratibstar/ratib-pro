#!/usr/bin/env bash
set -euo pipefail
BASE=https://dev.rateb.sa
ERP=/home/admin/domains/dev.rateb.sa/public_html/rateb-erp

echo "=== HTTP STATUS ==="
for path in \
  /rateb-erp/public/ratib-erp-build.txt \
  /rateb-erp/public/erp-health.php \
  /rateb-erp/public/login \
  /rateb-erp/public/admin \
  /rateb-erp/public/site/portal/logout \
  /rateb-erp/public/site/portal \
  /rateb-erp/public/admin/companies \
  /rateb-erp/public/admin/branches \
  /rateb-erp/public/admin/users \
  /rateb-erp/public/admin/inventory \
  /rateb-erp/public/admin/accounting \
  /rateb-erp/public/admin/hr \
  /rateb-erp/public/admin/procurement
do
  code=$(curl -sS -o /dev/null -w '%{http_code}' -L --max-redirs 3 "$BASE$path" || echo ERR)
  echo "$code $path"
done

echo "=== BUILD ==="
curl -sS "$BASE/rateb-erp/public/ratib-erp-build.txt"

echo "=== HEALTH ==="
curl -sS "$BASE/rateb-erp/public/erp-health.php"
echo

echo "=== DB RESOLVED ==="
cd "$ERP"
php << 'PHPEOF'
<?php
$_SERVER['HTTP_HOST'] = 'dev.rateb.sa';
define('RATEB_ROOT', getcwd());
require 'app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
echo Rateb\App\Core\Database::resolvedDatabaseName() . PHP_EOL;
try {
    Rateb\App\Core\Database::connection()->query('SELECT 1');
    echo "DB_CONNECT=OK\n";
} catch (Throwable $e) {
    echo 'DB_CONNECT=FAIL ' . $e->getMessage() . "\n";
}
PHPEOF

echo "=== BACKUP ==="
php bin/erp-backup.php 2>&1 | tail -1
B=$(ls -t storage/backups/erp-admin_rateb_dev-*.sql.gz | head -1)
echo "FILE=$B"
php bin/erp-restore.php --verify "$B" 2>&1

echo "=== LOGOUT REDIRECT ==="
curl -sS -o /dev/null -w '%{http_code} -> %{redirect_url}\n' "$BASE/rateb-erp/public/site/portal/logout" || true
