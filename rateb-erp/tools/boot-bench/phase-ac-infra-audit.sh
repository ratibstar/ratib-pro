#!/bin/bash
# Phase AC — infrastructure audit (evidence only). Run on production host.
set -eu
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
OUT=$ROOT/tools/boot-bench/reports/phase-ac-infra.json
TMP=/tmp/phase-ac-$$
mkdir -p "$(dirname "$OUT")" "$TMP"

php83() { /usr/local/php83/bin/php "$@"; }

echo "=== Phase AC infra audit ==="

# --- auth mint for cookie ---
if [ -f "$ROOT/tools/boot-bench/remote-auth.php" ]; then
  php83 "$ROOT/tools/boot-bench/remote-auth.php" mint > "$TMP/mint.json" 2>/dev/null || true
fi
if [ -f /tmp/rateb_remote_auth.php ]; then
  php83 /tmp/rateb_remote_auth.php mint > "$TMP/mint.json" 2>/dev/null || true
fi
COOKIE=""
if [ -f "$TMP/mint.json" ]; then
  php83 -r '$j=json_decode(file_get_contents("'"$TMP"'/mint.json"),true); if(!$j) exit(1); printf("%s=%s", $j["session_name"]??"PHPSESSID", $j["session_id"]??"");' > "$TMP/cookie.txt" 2>/dev/null || true
  COOKIE=$(cat "$TMP/cookie.txt" 2>/dev/null || true)
fi

# --- network timings via curl (loopback + public DNS) ---
measure_url() {
  local label=$1 url=$2 extra=$3
  local hdr="$TMP/${label}.hdr"
  local body="$TMP/${label}.body"
  # shellcheck disable=SC2086
  curl -sk $extra -D "$hdr" -o "$body" -w "%{http_code}|%{time_namelookup}|%{time_connect}|%{time_appconnect}|%{time_starttransfer}|%{time_total}|%{size_download}|%{num_connects}|%{http_version}\n" "$url" || echo "000|0|0|0|0|0|0|0|0"
}

# loopback (no WAN)
LOOP_EXTRA='--resolve rateb.sa:443:127.0.0.1'
if [ -n "$COOKIE" ]; then
  LOOP_EXTRA="$LOOP_EXTRA -b $COOKIE -c $TMP/cookie.jar"
fi

LB_ADMIN=$(measure_url lb_admin "https://rateb.sa/rateb-erp/public/admin/" "$LOOP_EXTRA")
LB_ADMIN2=$(measure_url lb_admin2 "https://rateb.sa/rateb-erp/public/admin/" "$LOOP_EXTRA")
LB_LOGIN=$(measure_url lb_login "https://rateb.sa/rateb-erp/public/login" "--resolve rateb.sa:443:127.0.0.1")
LB_STATIC=$(measure_url lb_css "https://rateb.sa/rateb-erp/public/assets/css/main.css" "--resolve rateb.sa:443:127.0.0.1")
LB_FA=$(measure_url lb_fa "https://rateb.sa/rateb-erp/public/assets/vendor/fontawesome/6.5.2/css/all.min.css" "--resolve rateb.sa:443:127.0.0.1")
LB_PROBE=$(measure_url lb_probe "https://rateb.sa/rateb-erp/public/connectivity-probe.json" "--resolve rateb.sa:443:127.0.0.1")

# keep-alive second request same connection (curl reuses with connection: keep-alive)
KA1=$(measure_url ka1 "https://rateb.sa/rateb-erp/public/connectivity-probe.json" "--resolve rateb.sa:443:127.0.0.1 -H 'Connection: keep-alive'")
KA2=$(measure_url ka2 "https://rateb.sa/rateb-erp/public/connectivity-probe.json" "--resolve rateb.sa:443:127.0.0.1 -H 'Connection: keep-alive'")

# public path (uses real DNS/TLS from server outbound — may still be local)
PUB_ADMIN=$(measure_url pub_admin "https://rateb.sa/rateb-erp/public/admin/" "${COOKIE:+-b $COOKIE}")

# hello FPM mini if exists
HELLO=""
if [ -f /home/admin/domains/rateb.sa/public_html/rateb-erp/public/hello.php ] || [ -f /tmp/hello.php ]; then
  HELLO=$(measure_url hello "https://rateb.sa/rateb-erp/public/hello.php" "--resolve rateb.sa:443:127.0.0.1" || true)
fi

# --- response headers sample ---
HDR_SAMPLE=""
if [ -f "$TMP/lb_admin.hdr" ]; then
  HDR_SAMPLE=$(grep -iE '^(HTTP/|server:|content-encoding:|content-type:|cache-control:|expires:|etag:|last-modified:|x-powered|keep-alive:|connection:|strict-transport|alt-svc:)' "$TMP/lb_admin.hdr" | tr -d '\r' | head -40)
fi
STATIC_HDR=""
if [ -f "$TMP/lb_css.hdr" ]; then
  STATIC_HDR=$(grep -iE '^(HTTP/|server:|content-encoding:|cache-control:|expires:|etag:|content-length:|accept-ranges:)' "$TMP/lb_css.hdr" | tr -d '\r' | head -30)
fi

# --- compression probe ---
GZ=$(curl -sk --resolve rateb.sa:443:127.0.0.1 -H 'Accept-Encoding: gzip, deflate, br' -D "$TMP/gz.hdr" -o /dev/null -w "%{size_download}" "https://rateb.sa/rateb-erp/public/assets/css/main.css" || echo 0)
GZ_ENC=$(grep -i '^content-encoding:' "$TMP/gz.hdr" 2>/dev/null | tr -d '\r' || true)
BR=$(curl -sk --resolve rateb.sa:443:127.0.0.1 -H 'Accept-Encoding: br' -D "$TMP/br.hdr" -o /dev/null -w "%{size_download}" "https://rateb.sa/rateb-erp/public/assets/css/main.css" || echo 0)
BR_ENC=$(grep -i '^content-encoding:' "$TMP/br.hdr" 2>/dev/null | tr -d '\r' || true)

# --- OPcache ---
OPCACHE_JSON=$(php83 -r '
$status = function_exists("opcache_get_status") ? @opcache_get_status(false) : null;
$cfg = function_exists("opcache_get_configuration") ? @opcache_get_configuration() : null;
$ini = php_ini_loaded_file();
$line = "";
if ($ini && is_readable($ini)) {
  foreach (file($ini) as $i => $l) {
    if (stripos($l, "opcache") !== false && stripos($l, "zend_extension") !== false) {
      $line = trim($l)." @".($i+1);
      break;
    }
  }
}
echo json_encode([
  "php_ini" => $ini,
  "zend_opcache_line" => $line,
  "extension_loaded" => extension_loaded("Zend OPcache") || extension_loaded("opcache"),
  "status" => $status,
  "directives" => $cfg["directives"] ?? null,
], JSON_UNESCAPED_SLASHES);
' 2>/dev/null || echo '{}')

# FPM php.ini opcache (may differ)
FPM_INI=$(ls /usr/local/php83/etc/php-fpm.conf /usr/local/php83/lib/php.ini /usr/local/php83/etc/php.ini 2>/dev/null | head -5)
OPCACHE_FPM=$(php83 -r '
// probe via a tiny web hit is better; check common fpm ini
$paths=["/usr/local/php83/lib/php.ini","/usr/local/php83/etc/php.ini"];
$out=[];
foreach($paths as $p){ if(is_file($p)){ $c=file_get_contents($p); $out[$p]=[
  "opcache_zend_commented"=> (bool)preg_match("/^\s*;\s*zend_extension\s*=\s*opcache/mi",$c),
  "opcache_zend_active"=> (bool)preg_match("/^\s*zend_extension\s*=\s*[^\n]*opcache/mi",$c),
];}}
echo json_encode($out, JSON_UNESCAPED_SLASHES);
' 2>/dev/null || echo '{}')

# --- PHP-FPM ---
FPM_STATUS=""
for u in \
  "https://rateb.sa/fpm-status" \
  "https://rateb.sa/php-fpm-status" \
  "http://127.0.0.1/fpm-status" \
  "http://127.0.0.1/status"
 do
  code=$(curl -sk -o "$TMP/fpm.txt" -w "%{http_code}" --max-time 3 "$u" || echo 000)
  if [ "$code" = "200" ] && grep -qi 'pool\|idle processes\|active processes' "$TMP/fpm.txt" 2>/dev/null; then
    FPM_STATUS=$(cat "$TMP/fpm.txt")
    break
  fi
done
# process list
FPM_PS=$(ps auxww | grep -E 'php-fpm|lsphp|httpd|apache|nginx|litespeed|openlitespeed' | grep -v grep | head -40 || true)
FPM_POOL=$(ls /usr/local/php83/etc/php-fpm.d/*.conf 2>/dev/null | head -5; ls /etc/php-fpm.d/*.conf 2>/dev/null | head -5; true)
POOL_SNIP=""
if [ -n "${FPM_POOL:-}" ]; then
  POOL_FILE=$(echo "$FPM_POOL" | head -1)
  POOL_SNIP=$(grep -iE 'pm\.|max_children|start_servers|status_path|listen' "$POOL_FILE" 2>/dev/null | head -30 || true)
fi

# --- reverse proxy ---
PROXY=$( (httpd -V 2>/dev/null || apachectl -V 2>/dev/null || true); (nginx -v 2>&1 || true); (lshttpd -v 2>&1 || true); (cat /usr/local/lsws/VERSION 2>/dev/null || true); systemctl is-active httpd nginx lsws litespeed 2>/dev/null || true )
PROXY_HDR=$(curl -skI --resolve rateb.sa:443:127.0.0.1 "https://rateb.sa/" | tr -d '\r' | head -20)

# --- system ---
CPU=$(nproc; uptime; top -bn1 | head -20)
MEM=$(free -b; free -h)
SWAP=$(swapon --show 2>/dev/null || echo none)
DISK=$(df -B1 / /home 2>/dev/null; df -h /; df -i /)
IO=$(iostat -x 1 2 2>/dev/null | tail -20 || echo 'iostat_unavailable')
LOAD=$(cat /proc/loadavg)

# --- MySQL ---
MYSQL_JSON=$(php83 -r '
require "'"$ROOT"'/app/Core/Bootstrap.php";
Rateb\App\Core\Bootstrap::init("'"$ROOT"'");
$pdo = Rateb\App\Core\Database::connection();
$t0=hrtime(true);
$pdo->query("SELECT 1")->fetch();
$ping=(hrtime(true)-$t0)/1e6;
$vars=[];
foreach(["version()","@@version_comment"] as $q){}
$st=$pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN (\"Threads_connected\",\"Threads_running\",\"Questions\",\"Slow_queries\",\"Uptime\",\"Innodb_buffer_pool_read_requests\",\"Innodb_buffer_pool_reads\",\"Created_tmp_disk_tables\",\"Connections\")");
while($r=$st->fetch(PDO::FETCH_ASSOC)){ $vars[$r["Variable_name"]]=$r["Value"]; }
$st2=$pdo->query("SHOW GLOBAL VARIABLES WHERE Variable_name IN (\"slow_query_log\",\"long_query_time\",\"slow_query_log_file\",\"max_connections\",\"innodb_buffer_pool_size\")");
$gvars=[];
while($r=$st2->fetch(PDO::FETCH_ASSOC)){ $gvars[$r["Variable_name"]]=$r["Value"]; }
$t0=hrtime(true);
$pdo->query("SELECT COUNT(*) FROM rateb_companies")->fetch();
$c1=(hrtime(true)-$t0)/1e6;
echo json_encode(["ping_ms"=>round($ping,3),"count_companies_ms"=>round($c1,3),"status"=>$vars,"variables"=>$gvars], JSON_UNESCAPED_SLASHES);
' 2>/dev/null || echo '{}')

# redis/memcached
REDIS=$(php83 -r 'echo json_encode(["redis"=>extension_loaded("redis"),"memcached"=>extension_loaded("memcached"),"apcu"=>extension_loaded("apcu")]);' 2>/dev/null)

# service worker files
SW_FILES=$(ls -la "$ROOT/public"/sw*.js "$ROOT/public"/service-worker*.js "$ROOT/public"/assets/**/sw*.js 2>/dev/null | head -20 || true)
SW_HEAD=$(head -80 "$ROOT/public/sw.js" 2>/dev/null || head -80 "$ROOT/public/service-worker.js" 2>/dev/null || echo 'no_sw')

# top asset sizes
ASSETS=$(find "$ROOT/public/assets" -type f \( -name '*.js' -o -name '*.css' -o -name '*.woff2' -o -name '*.woff' \) -printf '%s %p\n' 2>/dev/null | sort -nr | head -20)

# apache/nginx access log sample slowest last hour if readable
ACCESS_LOG=""
for L in \
  /var/log/httpd/domains/rateb.sa.log \
  /var/log/httpd/rateb.sa.log \
  /var/log/apache2/access.log \
  /usr/local/lsws/logs/access.log \
  /home/admin/logs/rateb.sa \
  /var/log/nginx/access.log
 do
  if [ -f "$L" ]; then ACCESS_LOG=$L; break; fi
done
TOP_REQ=""
if [ -n "$ACCESS_LOG" ]; then
  TOP_REQ=$(tail -n 5000 "$ACCESS_LOG" 2>/dev/null | awk '{print}' | tail -20 || true)
fi

# Write JSON via php
php83 -r '
$parse=function($s){
  $p=explode("|", trim((string)$s));
  if(count($p)<6) return ["raw"=>$s];
  return [
    "http_code"=>(int)$p[0],
    "dns_s"=>(float)$p[1],
    "connect_s"=>(float)$p[2],
    "tls_s"=>(float)$p[3],
    "ttfb_s"=>(float)$p[4],
    "total_s"=>(float)$p[5],
    "size"=>(int)$p[6],
    "num_connects"=>(int)($p[7]??0),
    "http_version"=>$p[8]??"",
    "dns_ms"=>round(((float)$p[1])*1000,3),
    "tcp_ms"=>round((((float)$p[2])-((float)$p[1]))*1000,3),
    "tls_ms"=>round((((float)$p[3])-((float)$p[2]))*1000,3),
    "ttfb_ms"=>round(((float)$p[4])*1000,3),
    "total_ms"=>round(((float)$p[5])*1000,3),
    "server_think_ms"=>round((((float)$p[4])-((float)$p[3]))*1000,3),
  ];
};
$out=[
  "phase"=>"AC",
  "measured_at"=>gmdate("c"),
  "host"=>gethostname(),
  "network_loopback"=>[
    "admin_cold"=>$parse(getenv("LB_ADMIN")),
    "admin_warm"=>$parse(getenv("LB_ADMIN2")),
    "login"=>$parse(getenv("LB_LOGIN")),
    "static_main_css"=>$parse(getenv("LB_STATIC")),
    "static_fontawesome"=>$parse(getenv("LB_FA")),
    "probe"=>$parse(getenv("LB_PROBE")),
    "keepalive_1"=>$parse(getenv("KA1")),
    "keepalive_2"=>$parse(getenv("KA2")),
  ],
  "network_public"=>["admin"=>$parse(getenv("PUB_ADMIN"))],
  "headers_admin"=>getenv("HDR_SAMPLE"),
  "headers_static_css"=>getenv("STATIC_HDR"),
  "compression"=>[
    "gzip_accept_size"=>getenv("GZ"),
    "gzip_encoding"=>getenv("GZ_ENC"),
    "br_accept_size"=>getenv("BR"),
    "br_encoding"=>getenv("BR_ENC"),
  ],
  "opcache"=>json_decode(getenv("OPCACHE_JSON")?: "{}", true),
  "opcache_ini_files"=>json_decode(getenv("OPCACHE_FPM")?: "{}", true),
  "fpm_status_raw"=>getenv("FPM_STATUS"),
  "fpm_ps"=>getenv("FPM_PS"),
  "fpm_pool_snip"=>getenv("POOL_SNIP"),
  "proxy"=>getenv("PROXY"),
  "proxy_headers"=>getenv("PROXY_HDR"),
  "cpu"=>getenv("CPU"),
  "mem"=>getenv("MEM"),
  "swap"=>getenv("SWAP"),
  "disk"=>getenv("DISK"),
  "io"=>getenv("IO"),
  "loadavg"=>getenv("LOAD"),
  "mysql"=>json_decode(getenv("MYSQL_JSON")?: "{}", true),
  "cache_ext"=>json_decode(getenv("REDIS")?: "{}", true),
  "sw_files"=>getenv("SW_FILES"),
  "sw_head"=>getenv("SW_HEAD"),
  "top_assets_by_size"=>getenv("ASSETS"),
  "access_log"=>getenv("ACCESS_LOG"),
];
file_put_contents(getenv("OUT"), json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
echo "wrote ".getenv("OUT")."\n";
' 

# export env for php -r (avoid huge via files)
export OUT LB_ADMIN="$LB_ADMIN" LB_ADMIN2="$LB_ADMIN2" LB_LOGIN="$LB_LOGIN" LB_STATIC="$LB_STATIC" LB_FA="$LB_FA" LB_PROBE="$LB_PROBE" KA1="$KA1" KA2="$KA2" PUB_ADMIN="$PUB_ADMIN"
export HDR_SAMPLE STATIC_HDR GZ GZ_ENC BR BR_ENC
export OPCACHE_JSON OPCACHE_FPM FPM_STATUS FPM_PS POOL_SNIP PROXY PROXY_HDR
export CPU MEM SWAP DISK IO LOAD MYSQL_JSON REDIS SW_FILES SW_HEAD ASSETS ACCESS_LOG

# rebuild - the php -r above ran before exports. Re-run properly:
php83 <<'PHP'
<?php
$parse=function($s){
  $p=explode("|", trim((string)$s));
  if(count($p)<6) return ["raw"=>$s];
  return [
    "http_code"=>(int)$p[0],
    "dns_s"=>(float)$p[1],
    "connect_s"=>(float)$p[2],
    "tls_s"=>(float)$p[3],
    "ttfb_s"=>(float)$p[4],
    "total_s"=>(float)$p[5],
    "size"=>(int)$p[6],
    "num_connects"=>(int)($p[7]??0),
    "http_version"=>$p[8]??"",
    "dns_ms"=>round(((float)$p[1])*1000,3),
    "tcp_ms"=>round((((float)$p[2])-((float)$p[1]))*1000,3),
    "tls_ms"=>round((((float)$p[3])-((float)$p[2]))*1000,3),
    "ttfb_ms"=>round(((float)$p[4])*1000,3),
    "total_ms"=>round(((float)$p[5])*1000,3),
    "server_think_ms"=>round((((float)$p[4])-((float)$p[3]))*1000,3),
  ];
};
$g=static fn($k)=>getenv($k)?:"";
$out=[
  "phase"=>"AC",
  "measured_at"=>gmdate("c"),
  "host"=>gethostname(),
  "network_loopback"=>[
    "admin_cold"=>$parse($g("LB_ADMIN")),
    "admin_warm"=>$parse($g("LB_ADMIN2")),
    "login"=>$parse($g("LB_LOGIN")),
    "static_main_css"=>$parse($g("LB_STATIC")),
    "static_fontawesome"=>$parse($g("LB_FA")),
    "probe"=>$parse($g("LB_PROBE")),
    "keepalive_1"=>$parse($g("KA1")),
    "keepalive_2"=>$parse($g("KA2")),
  ],
  "network_public"=>["admin"=>$parse($g("PUB_ADMIN"))],
  "headers_admin"=>$g("HDR_SAMPLE"),
  "headers_static_css"=>$g("STATIC_HDR"),
  "compression"=>[
    "gzip_accept_size"=>$g("GZ"),
    "gzip_encoding"=>$g("GZ_ENC"),
    "br_accept_size"=>$g("BR"),
    "br_encoding"=>$g("BR_ENC"),
  ],
  "opcache"=>json_decode($g("OPCACHE_JSON")?: "{}", true),
  "opcache_ini_files"=>json_decode($g("OPCACHE_FPM")?: "{}", true),
  "fpm_status_raw"=>$g("FPM_STATUS"),
  "fpm_ps"=>$g("FPM_PS"),
  "fpm_pool_snip"=>$g("POOL_SNIP"),
  "proxy"=>$g("PROXY"),
  "proxy_headers"=>$g("PROXY_HDR"),
  "cpu"=>$g("CPU"),
  "mem"=>$g("MEM"),
  "swap"=>$g("SWAP"),
  "disk"=>$g("DISK"),
  "io"=>$g("IO"),
  "loadavg"=>$g("LOAD"),
  "mysql"=>json_decode($g("MYSQL_JSON")?: "{}", true),
  "cache_ext"=>json_decode($g("REDIS")?: "{}", true),
  "sw_files"=>$g("SW_FILES"),
  "sw_head"=>$g("SW_HEAD"),
  "top_assets_by_size"=>$g("ASSETS"),
  "access_log"=>$g("ACCESS_LOG"),
];
file_put_contents($g("OUT"), json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
echo "wrote ".$g("OUT")."\n";
PHP

echo DONE
