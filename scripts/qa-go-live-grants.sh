#!/usr/bin/env bash
set -eu
source /tmp/rateb-db.env
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -e "SHOW GRANTS FOR CURRENT_USER()\G" 2>&1 | head -40
