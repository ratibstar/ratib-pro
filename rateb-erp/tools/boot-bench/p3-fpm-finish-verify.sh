#!/bin/bash
set -euo pipefail
POOL=/usr/local/directadmin/data/users/admin/php/php-fpm83.conf
echo "=== pool ==="
grep -E '^pm' "$POOL"
echo "=== systemd status ==="
systemctl status php-fpm83 --no-pager 2>&1 | head -22
echo "=== workers (ps) ==="
ps auxww | grep -E 'php-fpm: (pool admin|master)' | grep -v grep || true
echo "=== memory/load ==="
free -m | head -2
uptime
echo "=== warm health x5 ==="
for i in 1 2 3 4 5; do
  curl -sk -o /dev/null -w "warm$i ttfb=%{time_starttransfer} code=%{http_code}\n" \
    https://rateb.sa/rateb-erp/public/erp-health.php
done
echo "=== wait 70s (spare should remain) ==="
sleep 70
systemctl status php-fpm83 --no-pager 2>&1 | head -16
curl -sk -o /dev/null -w "after_70s ttfb=%{time_starttransfer} code=%{http_code}\n" \
  https://rateb.sa/rateb-erp/public/erp-health.php
echo "=== wait 300s (5min idle) ==="
sleep 300
systemctl status php-fpm83 --no-pager 2>&1 | head -16
curl -sk -o /dev/null -w "after_5min ttfb=%{time_starttransfer} code=%{http_code}\n" \
  https://rateb.sa/rateb-erp/public/erp-health.php
for i in 1 2 3; do
  curl -sk -o /dev/null -w "post5min_warm$i ttfb=%{time_starttransfer}\n" \
    https://rateb.sa/rateb-erp/public/erp-health.php
done
echo "=== custom template exists? ==="
ls -la /usr/local/directadmin/data/templates/custom/php-fpm.conf 2>&1 | head -2
echo "DONE"
