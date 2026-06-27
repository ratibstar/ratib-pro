#!/usr/bin/env bash
set -eu
source /tmp/rateb-db.env
for db in admin_designed admin_genia admin_ethiopia admin_bangladesh; do
  tc=$(mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db'" 2>/dev/null || echo ERR)
  rc=$(mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db' AND table_name LIKE 'rateb_%'" 2>/dev/null || echo ERR)
  echo "$db total_tables=$tc rateb_tables=$rc"
done
