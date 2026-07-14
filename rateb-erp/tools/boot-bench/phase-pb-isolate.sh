#!/bin/bash
set -u
PHP=/usr/local/php83/bin/php
OUT=/tmp/phase-pb3
mkdir -p "$OUT"
IP=167.233.71.107
R="--resolve rateb.sa:443:$IP"
URL="https://rateb.sa/rateb-erp/public/admin/ops/pos/register?company_id=22"

$PHP /tmp/remote-auth-pa.php mintpos > "$OUT/mint.json"
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/phase-pb3/mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')

echo "=== kill wait for ondemand (25s) ==="
# warm then idle
curl -sk $R -b "$C" -o /dev/null "$URL" >/dev/null
sleep 25
echo "workers=$(ps -eo args= | grep -c '[p]hp-fpm: pool' || true)"

echo "=== COLD FPM spawn with DNS BYPASSED (--resolve) ==="
curl -sk $R -b "$C" -o "$OUT/cold.html" -w "cold_no_dns dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code} size=%{size_download}\n" "$URL" | tee "$OUT/cold.txt"
echo "workers_after=$(ps -eo args= | grep -c '[p]hp-fpm: pool' || true)"

echo "=== WARM no dns ==="
curl -sk $R -b "$C" -o /dev/null -w "warm_no_dns dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" "$URL" | tee "$OUT/warm.txt"
curl -sk $R -b "$C" -o /dev/null -w "warm2_no_dns dns=%{time_namelookup} tcp=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" "$URL" | tee "$OUT/warm2.txt"

echo "=== DNS only: getaddrinfo timing for rateb.sa ==="
$PHP -r '
$t=hrtime(true);
$r=dns_get_record("rateb.sa", DNS_A);
$a=(hrtime(true)-$t)/1e6;
$t=hrtime(true);
$r6=@dns_get_record("rateb.sa", DNS_AAAA);
$aaaa=(hrtime(true)-$t)/1e6;
$t=hrtime(true);
$g=gethostbynamel("rateb.sa");
$ghn=(hrtime(true)-$t)/1e6;
echo json_encode(["a_ms"=>round($a,3),"aaaa_ms"=>round($aaaa,3),"gethostbynamel_ms"=>round($ghn,3),"a"=>$r,"aaaa"=>$r6,"gethost"=>$g], JSON_PRETTY_PRINT)."\n";
' | tee "$OUT/dns-php.json"

echo "=== curl DNS x5 no resolve ==="
for i in 1 2 3 4 5; do
  curl -sk -o /dev/null -w "dns$i namelookup=%{time_namelookup} ttfb=%{time_starttransfer}\n" "https://rateb.sa/rateb-erp/public/login"
  sleep 2
done | tee "$OUT/dns-curl.txt"

# opcache via FPM with resolve
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
cat > "$ROOT/public/_pb_opc.php" <<'P'
<?php
header('Content-Type: application/json');
$st = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
echo json_encode([
  'sapi'=>PHP_SAPI,
  'opcache_loaded'=>extension_loaded('Zend OPcache')||extension_loaded('opcache'),
  'enabled'=>$st['opcache_enabled']??null,
  'stats'=>isset($st['opcache_statistics'])?[
    'scripts'=>$st['opcache_statistics']['num_cached_scripts']??null,
    'hits'=>$st['opcache_statistics']['hits']??null,
    'misses'=>$st['opcache_statistics']['misses']??null,
  ]:null,
  'ini'=>[
    'opcache.enable'=>ini_get('opcache.enable'),
    'opcache.enable_cli'=>ini_get('opcache.enable_cli'),
  ],
], JSON_PRETTY_PRINT);
P
curl -sk $R -o "$OUT/opc.json" -w "opc ttfb=%{time_starttransfer} code=%{http_code}\n" "https://rateb.sa/rateb-erp/public/_pb_opc.php"
rm -f "$ROOT/public/_pb_opc.php"
cat "$OUT/opc.json"
echo
echo DONE
