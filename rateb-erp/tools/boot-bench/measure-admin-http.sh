#!/bin/bash
set -euo pipefail
php /tmp/mint-admin-cookie.php
echo "=== remote URL with --resolve 127.0.0.1 (follow redirects) ==="
curl -sk -L -o /tmp/admin.html \
  -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download} url=%{url_effective}\n" \
  -b /tmp/rateb-admin.cookie -c /tmp/rateb-admin.cookie \
  --resolve rateb.sa:443:127.0.0.1 \
  -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo "=== second (warm FPM) ==="
curl -sk -L -o /tmp/admin2.html \
  -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download} url=%{url_effective}\n" \
  -b /tmp/rateb-admin.cookie \
  --resolve rateb.sa:443:127.0.0.1 \
  -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
echo "=== third ==="
curl -sk -L -o /tmp/admin3.html \
  -w "code=%{http_code} dns=%{time_namelookup} connect=%{time_connect} tls=%{time_appconnect} ttfb=%{time_starttransfer} total=%{time_total} size=%{size_download} url=%{url_effective}\n" \
  -b /tmp/rateb-admin.cookie \
  --resolve rateb.sa:443:127.0.0.1 \
  -H "Accept: text/html" "https://rateb.sa/rateb-erp/public/admin/"
wc -c /tmp/admin.html
head -c 80 /tmp/admin.html; echo
grep -o "rateb-sidebar\|dashboard\|error\|login" /tmp/admin.html | head -20
