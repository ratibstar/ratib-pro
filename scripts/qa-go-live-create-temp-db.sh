#!/usr/bin/env bash
set -eu
DA=/usr/local/directadmin/directadmin
TEMP_DB="admin_rateb_erp_restore_test"
TEMP_USER="admin_rateb"
TEMP_PASS="RestoreTest20260627!"

echo "=== create temp DB via DirectAdmin API ==="
$DA api --user=admin CMD_API_DATABASES action=create name=erp_restore_test user="$TEMP_USER" passwd="$TEMP_PASS" passwd2="$TEMP_PASS" 2>&1 || true

source /tmp/rateb-db.env
echo "=== show databases ==="
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SHOW DATABASES LIKE 'admin_rateb%'" 2>&1
