#!/bin/bash
set -eu
php /tmp/remote-auth.php mint > /tmp/mint.json 2>/dev/null || php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint > /tmp/mint.json
php -r '$j=json_decode(file_get_contents("/tmp/mint.json"),true); printf("rateb.sa\tFALSE\t/\tTRUE\t0\t%s\t%s\n",$j["session_name"],$j["session_id"]);' > /tmp/c.cookie
echo PUBLIC
for i in 1 2 3; do
  curl -sk -o /tmp/a.html -w "admin$i ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download} code=%{http_code}\n" \
    -b /tmp/c.cookie -H 'Accept: text/html' 'https://rateb.sa/rateb-erp/public/admin/'
done
echo LOCAL_RESOLVE
for i in 1 2 3; do
  curl -sk -o /tmp/a2.html -w "local$i ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download} code=%{http_code}\n" \
    -b /tmp/c.cookie -H 'Accept: text/html' --resolve rateb.sa:443:127.0.0.1 'https://rateb.sa/rateb-erp/public/admin/'
done
head -c 80 /tmp/a.html; echo
