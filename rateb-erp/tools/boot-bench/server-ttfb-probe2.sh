#!/bin/bash
set -eu
php /tmp/rateb_remote_auth.php mint | tee /tmp/mint.json
php -r '$j=json_decode(file_get_contents("/tmp/mint.json"),true); if(empty($j["session_id"])){fwrite(STDERR,"mint fail\n"); exit(1);} file_put_contents("/tmp/rateb-admin.cookie", sprintf("rateb.sa\tFALSE\t/\tTRUE\t0\t%s\t%s\n",$j["session_name"],$j["session_id"])); echo "cookie_ok\n";'
echo "=== DNS dig ==="
time dig +time=2 +tries=1 rateb.sa A +short || true
time getent hosts rateb.sa || true
echo "=== admin nofollow cold ==="
curl -sk -o /tmp/admin-nf.html -D /tmp/admin-nf.hdr -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download} redir=%{redirect_url}\n" -b /tmp/rateb-admin.cookie -c /tmp/rateb-admin.cookie -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
head -5 /tmp/admin-nf.hdr
wc -c /tmp/admin-nf.html
echo "=== follow cold ==="
curl -sk -L -o /tmp/admin-f.html -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" -b /tmp/rateb-admin.cookie -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
wc -c /tmp/admin-f.html
grep -o "rateb-sidebar\|لوحة التحكم\|login" /tmp/admin-f.html | head -10
echo "=== warm follow ==="
curl -sk -L -o /tmp/admin-f2.html -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" -b /tmp/rateb-admin.cookie -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo "=== login POST timing (no auth) ==="
curl -sk -o /dev/null -w "code=%{http_code} dns=%{time_namelookup} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total}\n" -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/login"
echo "=== probe json ==="
curl -sk -o /dev/null -w "code=%{http_code} dns=%{time_namelookup} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total}\n" "https://rateb.sa/rateb-erp/public/connectivity-probe.json"
echo "=== db host from config ==="
php -r '$_SERVER["HTTP_HOST"]="rateb.sa"; $_SERVER["HTTPS"]="on"; define("RATEB_ROOT","/home/admin/domains/rateb.sa/public_html/rateb-erp"); require RATEB_ROOT."/config/database.php"; $c=is_array($config??null)?$config:(require RATEB_ROOT."/config/database.php"); if(is_array($c)){echo json_encode(["host"=>$c["host"]??$c["hostname"]??null],JSON_UNESCAPED_SLASHES),"\n";}'
echo "=== CLI profile if present ==="
if [ -f /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/profile-admin-get.php ]; then php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/profile-admin-get.php 2>/tmp/prof.err | tail -c 2000; echo; tail -5 /tmp/prof.err; else echo no-profile; fi
