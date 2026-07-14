#!/bin/bash
set -eu
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
R='--resolve rateb.sa:443:167.233.71.107'
OUT=$ROOT/tools/boot-bench/reports/phase-ac-server-waterfall.json

$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/mint.json
echo "MINT: $(cat /tmp/mint.json)"
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')

FMT='%{http_code}|%{time_namelookup}|%{time_connect}|%{time_appconnect}|%{time_starttransfer}|%{time_total}|%{size_download}|%{http_version}'

measure() {
  local label=$1 url=$2 extra=${3:-}
  local raw
  raw=$(curl -sk $R $extra -o /tmp/acbody_$label -w "$FMT" "$url")
  echo "$label $raw"
  echo "$label|$raw" >> /tmp/ac_measures.txt
}

: > /tmp/ac_measures.txt
measure login https://rateb.sa/rateb-erp/public/login
measure admin1 "https://rateb.sa/rateb-erp/public/admin/" "-b $C"
measure admin2 "https://rateb.sa/rateb-erp/public/admin/" "-b $C"
measure charts "https://rateb.sa/rateb-erp/public/admin/api/dashboard-charts" "-b $C"
measure probe https://rateb.sa/rateb-erp/public/connectivity-probe.json
measure css https://rateb.sa/rateb-erp/public/assets/css/main.css
measure fa https://rateb.sa/rateb-erp/public/assets/vendor/fontawesome/6.5.2/css/all.min.css
measure bootcss https://rateb.sa/rateb-erp/public/assets/vendor/bootstrap/5.3.3/bootstrap.rtl.min.css
measure offlinejs https://rateb.sa/rateb-erp/public/assets/offline/rateb-offline.min.js
measure sw https://rateb.sa/rateb-erp/public/rateb-offline-sw.js

# DNS comparison
DNS_HETZNER=$(dig +time=3 +tries=1 @2a01:4ff:ff00::add:2 rateb.sa A 2>&1 | awk '/Query time:/{print $4}')
DNS_GOOGLE=$(dig +time=3 +tries=1 @8.8.8.8 rateb.sa A 2>&1 | awk '/Query time:/{print $4; found=1} END{if(!found) print "fail"}')
DNS_CLOUD=$(dig +time=3 +tries=1 @1.1.1.1 rateb.sa A 2>&1 | awk '/Query time:/{print $4; found=1} END{if(!found) print "fail"}')

# OPcache FPM
echo '<?php header("Content-Type: application/json");
$s=function_exists("opcache_get_status")?@opcache_get_status(false):null;
echo json_encode([
  "opcache_loaded"=>extension_loaded("Zend OPcache")||extension_loaded("opcache"),
  "opcache_enabled"=>is_array($s)?($s["opcache_enabled"]??null):null,
  "cached_scripts"=>is_array($s)?($s["opcache_statistics"]["num_cached_scripts"]??null):null,
  "sapi"=>PHP_SAPI,
  "ini"=>php_ini_loaded_file(),
]);' > "$ROOT/public/_ac_opcache_probe.php"
OPC=$(curl -sk $R "https://rateb.sa/rateb-erp/public/_ac_opcache_probe.php")
rm -f "$ROOT/public/_ac_opcache_probe.php"
echo "OPC $OPC"

# FPM pool
POOL=$(grep -iE '^(pm|pm\.|listen)' /usr/local/php83/etc/php-fpm.d/*.conf 2>/dev/null | head -40 || true)
FPM_N=$(ps -C php-fpm -o pid= 2>/dev/null | wc -l || echo 0)

# headers
curl -sk $R -b "$C" -D /tmp/ac_admin.hdr -o /dev/null "https://rateb.sa/rateb-erp/public/admin/"
curl -sk $R -H 'Accept-Encoding: gzip,br' -D /tmp/ac_css.hdr -o /dev/null "https://rateb.sa/rateb-erp/public/assets/css/main.css"
curl -sk $R -H 'Accept-Encoding: gzip,br' -D /tmp/ac_js.hdr -o /dev/null "https://rateb.sa/rateb-erp/public/assets/offline/rateb-offline.min.js"

$PHP -r '
$lines=file("/tmp/ac_measures.txt", FILE_IGNORE_NEW_LINES);
$net=[];
foreach($lines as $line){
  [$label,$raw]=explode("|",$line,2)+[null,null];
  if(!$raw){[$label,$raw]=array_pad(explode(" ",$line,2),2,""); }
}
$net=[];
foreach(file("/tmp/ac_measures.txt", FILE_IGNORE_NEW_LINES) as $line){
  $parts=explode("|", $line);
  if(count($parts)<7) continue;
  $label=array_shift($parts);
  $dns=(float)$parts[1]; $conn=(float)$parts[2]; $tls=(float)$parts[3]; $ttfb=(float)$parts[4]; $total=(float)$parts[5];
  $net[$label]=[
    "code"=>(int)$parts[0],
    "dns_ms"=>round($dns*1000,3),
    "tcp_ms"=>round(($conn-$dns)*1000,3),
    "tls_ms"=>round(($tls-$conn)*1000,3),
    "server_think_ms"=>round(($ttfb-$tls)*1000,3),
    "ttfb_ms"=>round($ttfb*1000,3),
    "total_ms"=>round($total*1000,3),
    "size"=>(int)$parts[6],
    "http"=>$parts[7]??"",
  ];
}
$out=[
  "measured_at"=>gmdate("c"),
  "network_bypass_dns"=>$net,
  "dns_query_ms"=>[
    "hetzner_add2"=>(int)getenv("DNS_HETZNER"),
    "google_8_8_8_8"=>getenv("DNS_GOOGLE"),
    "cloudflare_1_1_1_1"=>getenv("DNS_CLOUD"),
  ],
  "fpm_opcache"=>json_decode(getenv("OPC")?: "{}", true),
  "fpm_workers_approx"=>(int)getenv("FPM_N"),
  "fpm_pool"=>getenv("POOL"),
  "headers_admin"=>trim((string)@file_get_contents("/tmp/ac_admin.hdr")),
  "headers_css"=>trim((string)@file_get_contents("/tmp/ac_css.hdr")),
  "headers_offline_js"=>trim((string)@file_get_contents("/tmp/ac_js.hdr")),
];
file_put_contents(getenv("OUT"), json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo json_encode(["wrote"=>getenv("OUT"),"summary"=>["admin1"=>$net["admin1"]??null,"admin2"=>$net["admin2"]??null,"dns"=>$out["dns_query_ms"],"opcache"=>$out["fpm_opcache"]]], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
'

export OUT DNS_HETZNER DNS_GOOGLE DNS_CLOUD OPC POOL FPM_N
OUT=$OUT DNS_HETZNER=$DNS_HETZNER DNS_GOOGLE=$DNS_GOOGLE DNS_CLOUD=$DNS_CLOUD OPC="$OPC" POOL="$POOL" FPM_N=$FPM_N $PHP -r '
$lines=file("/tmp/ac_measures.txt", FILE_IGNORE_NEW_LINES);
$net=[];
foreach($lines as $line){
  $parts=explode("|", $line);
  if(count($parts)<7) continue;
  $label=array_shift($parts);
  $dns=(float)$parts[1]; $conn=(float)$parts[2]; $tls=(float)$parts[3]; $ttfb=(float)$parts[4]; $total=(float)$parts[5];
  $net[$label]=[
    "code"=>(int)$parts[0],
    "dns_ms"=>round($dns*1000,3),
    "tcp_ms"=>round(($conn-$dns)*1000,3),
    "tls_ms"=>round(($tls-$conn)*1000,3),
    "server_think_ms"=>round(($ttfb-$tls)*1000,3),
    "ttfb_ms"=>round($ttfb*1000,3),
    "total_ms"=>round($total*1000,3),
    "size"=>(int)$parts[6],
    "http"=>$parts[7]??"",
  ];
}
$out=[
  "measured_at"=>gmdate("c"),
  "network_bypass_dns"=>$net,
  "dns_query_ms"=>[
    "hetzner_add2"=>(int)getenv("DNS_HETZNER"),
    "google_8_8_8_8"=>getenv("DNS_GOOGLE"),
    "cloudflare_1_1_1_1"=>getenv("DNS_CLOUD"),
  ],
  "fpm_opcache"=>json_decode(getenv("OPC")?: "{}", true),
  "fpm_workers_approx"=>(int)getenv("FPM_N"),
  "fpm_pool"=>getenv("POOL"),
  "headers_admin"=>trim((string)@file_get_contents("/tmp/ac_admin.hdr")),
  "headers_css"=>trim((string)@file_get_contents("/tmp/ac_css.hdr")),
  "headers_offline_js"=>trim((string)@file_get_contents("/tmp/ac_js.hdr")),
];
file_put_contents(getenv("OUT"), json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo json_encode([
  "wrote"=>getenv("OUT"),
  "admin1"=>$net["admin1"]??null,
  "admin2"=>$net["admin2"]??null,
  "login"=>$net["login"]??null,
  "charts"=>$net["charts"]??null,
  "offlinejs"=>$net["offlinejs"]??null,
  "dns"=>$out["dns_query_ms"],
  "opcache"=>$out["fpm_opcache"],
  "fpm_n"=>$out["fpm_workers_approx"],
], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),"\n";
'
