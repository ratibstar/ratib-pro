#!/bin/bash
set -eu
PASS="$(tr -d '\r\n' < /home/admin/.secrets/rateb_ai_db_password)"
mysql -u admin_rateb_ai -p"${PASS}" admin_rateb_ai -e "SHOW TABLES LIKE 'platform_settings'; SHOW TABLES LIKE 'hr_%'; SELECT setting_key, setting_value FROM platform_settings;"
