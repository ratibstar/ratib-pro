#!/usr/bin/env bash
set -eu
ERP="/home/admin/domains/rateb.sa/public_html/rateb-erp"
cd "$ERP"
eval "$(php -r '
define("RATEB_ROOT", getcwd());
require "app/Core/Bootstrap.php";
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
$h = getenv("RATEB_ERP_DB_HOST") ?: "localhost";
$u = getenv("RATEB_ERP_DB_USER") ?: "";
$p = getenv("RATEB_ERP_DB_PASS") ?: "";
echo "DB_HOST=".escapeshellarg($h)."\nDB_USER=".escapeshellarg($u)."\nDB_PASS=".escapeshellarg($p)."\n";
')"
for db in admin_rateb admin_rateb-erp; do
  c=$(mysql --host="$DB_HOST" --user="$DB_USER" ${DB_PASS:+--password="$DB_PASS"} -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db'" 2>/dev/null || echo ERR)
  echo "tables_in_$db=$c"
done
echo "=== da mysql create via admin user ==="
if [ -f /usr/local/directadmin/scripts/create_database.sh ]; then echo has_da_create_script; fi
timeout 5 mysql -N -e "SELECT 1" 2>&1 || echo root_mysql_timeout_or_denied
