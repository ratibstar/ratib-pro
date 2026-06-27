#!/usr/bin/env bash
set -eu
echo "=== DA access ==="
which directadmin 2>/dev/null || echo no_da_bin
ls -la /usr/local/directadmin/scripts/create_database.sh 2>/dev/null || echo no_create_db
ls -la /usr/local/directadmin/conf/mysql.conf 2>/dev/null || echo no_mysql_conf
echo "=== da whoami ==="
/usr/local/directadmin/directadmin 2>&1 | head -3 || true
echo "=== api test ==="
if [ -f /usr/local/directadmin/conf/directadmin.conf ]; then grep -E '^ssl=|^port=' /usr/local/directadmin/conf/directadmin.conf | head -3; fi
