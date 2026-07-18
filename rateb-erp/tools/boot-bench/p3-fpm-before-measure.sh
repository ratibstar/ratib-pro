#!/bin/bash
set -euo pipefail
POOL=/usr/local/directadmin/data/users/admin/php/php-fpm83.conf
echo "=== pool ==="
ls -la "$POOL"
grep -E "^pm" "$POOL"
echo "=== fpm status ==="
systemctl status php-fpm83 --no-pager 2>&1 | head -18
free -m | head -2
echo "=== warm baseline ==="
for i in 1 2 3; do
  curl -sk -o /dev/null -w "warm${i} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" \
    https://rateb.sa/rateb-erp/public/erp-health.php
done
echo "=== wait 25s for ondemand idle kill ==="
sleep 25
systemctl status php-fpm83 --no-pager 2>&1 | head -18
echo "=== cold-after-idle ==="
curl -sk -o /dev/null -w "cold_after_idle ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" \
  https://rateb.sa/rateb-erp/public/erp-health.php
curl -sk -o /dev/null -w "post_cold_warm ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" \
  https://rateb.sa/rateb-erp/public/erp-health.php
curl -sk -o /dev/null -w "login_page ttfb=%{time_starttransfer} code=%{http_code}\n" \
  https://rateb.sa/rateb-erp/public/login
echo "=== full pool ==="
cat "$POOL"
echo "=== apply attempt ==="
bash /tmp/p3-fpm-ondemand-to-dynamic.sh 2>&1 | head -8