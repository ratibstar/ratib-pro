#!/usr/bin/env bash
set -eu
source /tmp/rateb-db.env
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" admin_designed <<'SQL'
SET FOREIGN_KEY_CHECKS=0;
SET GROUP_CONCAT_MAX_LEN=32768;
SET @tables = NULL;
SELECT GROUP_CONCAT(CONCAT('`', table_name, '`')) INTO @tables
FROM information_schema.tables
WHERE table_schema='admin_designed' AND table_name LIKE 'rateb\_%';
SET @sql = IFNULL(CONCAT('DROP TABLE IF EXISTS ', @tables), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
SET FOREIGN_KEY_CHECKS=1;
SQL
echo "rateb_remaining=$(mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='admin_designed' AND table_name LIKE 'rateb_%'")"
echo "total_tables=$(mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='admin_designed'")"
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='admin_designed' ORDER BY table_name"
