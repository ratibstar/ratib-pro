#!/bin/bash
set -eu
echo "=== DNS cold vs warm ==="
for i in 1 2 3; do
  echo -n "hetzner try$i: "
  dig +time=3 +tries=1 @2a01:4ff:ff00::add:2 rateb.sa A +stats 2>&1 | awk '/Query time:/{print $4" ms"}'
  sleep 0.2
done
echo -n "file random subdomain cold: "
dig +time=3 +tries=1 @2a01:4ff:ff00::add:2 "x$RANDOM.rateb.sa" A +stats 2>&1 | awk '/Query time:/{print $4" ms"}'

echo "=== FPM config ==="
grep -RIn "pm \|pm.max\|ondemand\|dynamic\|static" /usr/local/directadmin/data/users/*/php/php-fpm83.conf 2>/dev/null | head -40
cat /usr/local/directadmin/data/users/admin/php/php-fpm83.conf 2>/dev/null | head -60

echo "=== spawn cost: idle then request ==="
# count workers before
before=$(ps -eo args= | grep -c '[p]hp-fpm: pool' || true)
echo "workers_before=$before"
# wait for idle timeout would take 60s - instead measure cold by comparing with resolved timing
R='--resolve rateb.sa:443:167.233.71.107'
PHP=/usr/local/php83/bin/php
$PHP /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint > /tmp/mint.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')
echo -n "admin_a: "
curl -sk $R -b "$C" -o /dev/null -w 'ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n' https://rateb.sa/rateb-erp/public/admin/
echo -n "admin_b: "
curl -sk $R -b "$C" -o /dev/null -w 'ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n' https://rateb.sa/rateb-erp/public/admin/
after=$(ps -eo args= | grep -c '[p]hp-fpm: pool' || true)
echo "workers_after=$after"

echo "=== opcache ini ==="
grep -n 'zend_extension.*opcache\|opcache.enable' /usr/local/php83/lib/php.ini | head -10

echo "=== public no-resolve (pays DNS) ==="
echo -n "public1: "
curl -sk -o /dev/null -w 'dns=%{time_namelookup} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n' https://rateb.sa/rateb-erp/public/login
echo -n "public2: "
curl -sk -o /dev/null -w 'dns=%{time_namelookup} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n' https://rateb.sa/rateb-erp/public/login
