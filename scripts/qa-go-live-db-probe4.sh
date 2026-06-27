#!/usr/bin/env bash
set -eu
ERP="/home/admin/domains/rateb.sa/public_html/rateb-erp"
cd "$ERP"
php <<'PHPEOF'
<?php
define('RATEB_ROOT', getcwd());
require 'app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
$h = getenv('RATEB_ERP_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$u = getenv('RATEB_ERP_DB_USER') ?: getenv('DB_USER') ?: '';
$p = getenv('RATEB_ERP_DB_PASS') ?: getenv('DB_PASS') ?: '';
$db = \Rateb\App\Core\Database::resolvedDatabaseName();
file_put_contents('/tmp/rateb-db.env', "DB_HOST=".var_export($h,true)."\nDB_USER=".var_export($u,true)."\nDB_PASS=".var_export($p,true)."\nSOURCE_DB=".var_export($db,true)."\n");
echo "wrote /tmp/rateb-db.env user=$u source=$db\n";
PHPEOF
source /tmp/rateb-db.env
echo "DB_USER=$DB_USER SOURCE_DB=$SOURCE_DB"
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$SOURCE_DB'"
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SHOW DATABASES LIKE 'admin_rateb%'"
