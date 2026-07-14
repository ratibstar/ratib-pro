#!/bin/bash
set -eu
php /tmp/rateb_remote_auth.php mint > /tmp/mint.json
php -r '$j=json_decode(file_get_contents("/tmp/mint.json"),true); printf("rateb.sa\tFALSE\t/\tTRUE\t0\t%s\t%s\n",$j["session_name"],$j["session_id"]);' > /tmp/rateb-admin.cookie
echo MINT_ok
echo cold
curl -sk -L -o /tmp/admin.html -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" -b /tmp/rateb-admin.cookie -c /tmp/rateb-admin.cookie --resolve rateb.sa:443:127.0.0.1 -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo warm1
curl -sk -L -o /tmp/admin2.html -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" -b /tmp/rateb-admin.cookie --resolve rateb.sa:443:127.0.0.1 -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo warm2
curl -sk -L -o /tmp/admin3.html -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download}\n" -b /tmp/rateb-admin.cookie --resolve rateb.sa:443:127.0.0.1 -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
wc -c /tmp/admin.html
curl -sk -D /tmp/admin.hdr -o /dev/null -b /tmp/rateb-admin.cookie --resolve rateb.sa:443:127.0.0.1 -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo headers
grep -iE "server:|x-powered|cache|content-encoding|content-length|server-timing|x-rateb|keep-alive" /tmp/admin.hdr || true
php -r 'var_export(function_exists("opcache_get_status") ? (opcache_get_status(false)["opcache_enabled"] ?? null) : "n/a"); echo "\n";'
php -m 2>/dev/null | grep -iE "apcu|redis|opcache" || true
if [ -f /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/phase-x-admin-sql-bench.php ]; then echo phase-x-present; else echo phase-x-missing; fi
ls -la /home/admin/domains/rateb.sa/public_html/rateb-erp/app/services/ApprovalOversightService.php 2>&1 | head -1
grep -n "rateb_request_memo\|function rateb_ops_company_exists\|Request-scoped ops-company memo" /home/admin/domains/rateb.sa/public_html/rateb-erp/config/app.php 2>/dev/null | head -30
echo static_probe
curl -sk -o /dev/null -w "ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code}\n" --resolve rateb.sa:443:127.0.0.1 "https://rateb.sa/rateb-erp/public/connectivity-probe.json"
echo login_page
curl -sk -o /dev/null -w "ttfb=%{time_starttransfer} total=%{time_total} code=%{http_code} size=%{size_download}\n" --resolve rateb.sa:443:127.0.0.1 "https://rateb.sa/rateb-erp/public/login"
