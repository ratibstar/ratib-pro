#!/bin/bash
set -eu
echo "DNS_HETZNER=$(dig +time=3 +tries=1 @2a01:4ff:ff00::add:2 rateb.sa A 2>&1 | awk '/Query time:/{print $4}')"
echo "DNS_GOOGLE=$(dig +time=3 +tries=1 @8.8.8.8 rateb.sa A 2>&1 | awk '/Query time:/{print $4;f=1} END{if(!f)print fail}')"
echo "DNS_CF=$(dig +time=3 +tries=1 @1.1.1.1 rateb.sa A 2>&1 | awk '/Query time:/{print $4;f=1} END{if(!f)print fail}')"
ls -la /usr/local/php83/etc/php-fpm.d/ 2>/dev/null || true
grep -iE 'pm\.|max_children|listen' /usr/local/php83/etc/php-fpm.d/*.conf 2>/dev/null | head -30 || true
