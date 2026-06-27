#!/usr/bin/env bash
set -eu
ERP="/home/admin/domains/rateb.sa/public_html/rateb-erp"
cd "$ERP"
eval "$(php -r '
define("RATEB_ROOT", getcwd());
require "app/Core/Bootstrap.php";
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
$h = getenv("RATEB_ERP_DB_HOST") ?: getenv("DB_HOST") ?: "localhost";
$u = getenv("RATEB_ERP_DB_USER") ?: getenv("DB_USER") ?: "";
$p = getenv("RATEB_ERP_DB_PASS") ?: getenv("DB_PASS") ?: "";
echo "DB_HOST=".escapeshellarg($h)."\n";
echo "DB_USER=".escapeshellarg($u)."\n";
echo "DB_PASS=".escapeshellarg($p)."\n";
')"
echo "DB_USER=$DB_USER"
mysql --host="$DB_HOST" --user="$DB_USER" ${DB_PASS:+--password="$DB_PASS"} -N -e "SHOW DATABASES" 2>&1
for db in admin_rateb_erp_restore_test admin_rateb_restore_test admin_rateb_erp_restore admin_rateb_restore; do
  echo "TRY_CREATE $db"
  mysql --host="$DB_HOST" --user="$DB_USER" ${DB_PASS:+--password="$DB_PASS"} -e "CREATE DATABASE IF NOT EXISTS \`$db\`" 2>&1 && echo "OK $db" && break || true
done
