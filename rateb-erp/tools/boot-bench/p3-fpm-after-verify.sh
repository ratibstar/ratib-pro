#!/bin/bash
# P3 post-apply verification (run as admin after root applies dynamic).
set -euo pipefail
POOL=/usr/local/directadmin/data/users/admin/php/php-fpm83.conf
echo "=== pool pm* ==="
grep -E '^pm' "$POOL"
echo "=== systemd ==="
systemctl status php-fpm83 --no-pager 2>&1 | head -20
echo "=== memory/cpu ==="
free -m | head -2
uptime
echo "=== first request after restart (caller should restart first) ==="
curl -sk -o /dev/null -w "after_restart ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" \
  https://rateb.sa/rateb-erp/public/erp-health.php
echo "=== warm x5 ==="
for i in 1 2 3 4 5; do
  curl -sk -o /dev/null -w "warm$i ttfb=%{time_starttransfer}\n" \
    https://rateb.sa/rateb-erp/public/erp-health.php
done
echo "=== wait 65s (spare workers should remain under dynamic) ==="
sleep 65
systemctl status php-fpm83 --no-pager 2>&1 | head -15
curl -sk -o /dev/null -w "after_65s_idle ttfb=%{time_starttransfer} code=%{http_code}\n" \
  https://rateb.sa/rateb-erp/public/erp-health.php
echo "=== wait 300s for 5-minute idle check ==="
sleep 300
systemctl status php-fpm83 --no-pager 2>&1 | head -15
curl -sk -o /dev/null -w "after_5min_idle ttfb=%{time_starttransfer} code=%{http_code}\n" \
  https://rateb.sa/rateb-erp/public/erp-health.php
