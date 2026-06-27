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
DB='admin_rateb-erp'
c=$(mysql --host="$DB_HOST" --user="$DB_USER" ${DB_PASS:+--password="$DB_PASS"} -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB}'")
echo "tables_in_erp=$c"
echo "=== directadmin ==="
ls /usr/local/directadmin/scripts/create* 2>/dev/null | head -5 || echo no_da_scripts
test -r /usr/local/directadmin/conf/mysql.conf && echo mysql_conf_readable || echo mysql_conf_denied
echo "=== try create via DA ==="
if [ -x /usr/local/directadmin/scripts/create_database.sh ]; then
  /usr/local/directadmin/scripts/create_database.sh admin admin_rateb_erp_restore_test restoretest123 2>&1 || true
fi
mysql --host="$DB_HOST" --user="$DB_USER" ${DB_PASS:+--password="$DB_PASS"} -N -e "SHOW DATABASES LIKE 'admin_rateb%'" 2>&1
