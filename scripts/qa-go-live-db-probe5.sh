#!/usr/bin/env bash
set -eu
source /tmp/rateb-db.env
echo "=== admin_rateb tables ==="
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='admin_rateb' ORDER BY table_rows DESC LIMIT 15"
echo "=== admin_rateb table count ==="
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='admin_rateb'"
