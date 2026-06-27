#!/usr/bin/env bash
set -eu
source /tmp/rateb-db.env
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='admin_designed'"
