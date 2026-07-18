#!/bin/bash
set -eu
echo '=== APACHE MODULES ==='
httpd -M 2>/dev/null | grep -iE 'http2|ssl|deflate|brotli|expires|headers|cache|http2|proxy_fcgi|php' || true
echo '=== APACHE CONF SNIPS ==='
grep -RniE 'Protocols|KeepAlive|MaxKeepAlive|HTTP2|Brotli|Deflate|ExpiresByType|Cache-Control|H2' /etc/httpd/conf /etc/httpd/conf.d /usr/local/directadmin/custombuild 2>/dev/null | head -80 || true
ls /etc/httpd/conf.d/ 2>/dev/null | head -40
echo '=== VHOST RATEB ==='
grep -RniE 'rateb.sa|Protocols|HTTP2|SSLEngine' /usr/local/directadmin/data/users/admin/httpd.conf /usr/local/directadmin/data/users/admin/httpd.conf.d 2>/dev/null | head -40 || true
# find httpd conf for domain
find /usr/local/directadmin/data/users/admin -name '*.conf' 2>/dev/null | head -30
echo '=== CURL PROTO / TLS / COMPRESS ==='
curl -sk -o /dev/null -w 'proto=%{http_version} ssl=%{ssl_verify_result} namelookup=%{time_namelookup} connect=%{time_connect} appconnect=%{time_appconnect} starttransfer=%{time_starttransfer} total=%{time_total}\n' https://rateb.sa/rateb-erp/public/erp-health.php
curl -sk -D - -o /dev/null -H 'Accept-Encoding: br, gzip' https://rateb.sa/rateb-erp/public/assets/js/app.js 2>/dev/null | grep -iE 'HTTP/|content-encoding|cache-control|expires|etag|vary|age|cf-|server'
echo '=== BROTLI FILE CHECK ==='
httpd -M 2>/dev/null | grep -i brotli || echo 'no brotli module'
echo '=== KEEPALIVE CONF ==='
httpd -S 2>/dev/null | head -20
grep -RniE '^KeepAlive|^MaxKeepAliveRequests|^KeepAliveTimeout|^Protocols' /etc/httpd/conf/httpd.conf /etc/httpd/conf/extra 2>/dev/null | head -30
