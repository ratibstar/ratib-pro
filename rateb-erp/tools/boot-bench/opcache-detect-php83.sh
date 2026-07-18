#!/bin/bash
# Detect PHP 8.3 OPcache status on DirectAdmin (read-only).
set -euo pipefail

echo "=== identity ==="
whoami
id
sudo -n true 2>/dev/null && echo SUDO_NOPASS=yes || echo SUDO_NOPASS=no

echo "=== php -v ==="
php -v
echo "=== php --ini ==="
php --ini

CONF=$(php --ini 2>/dev/null | awk -F': ' '/Loaded Configuration File/{print $2}' | tr -d ' ')
SCAN=$(php --ini 2>/dev/null | awk -F': ' '/Scan for additional/{print $2}' | tr -d ' ')
echo "CONF=$CONF"
echo "SCAN=$SCAN"

echo "=== opcache.so search ==="
find /usr/local/php83 /usr/lib /usr/lib64 -name 'opcache.so' 2>/dev/null || true

echo "=== php.ini opcache lines ==="
if [[ -n "$CONF" && -f "$CONF" ]]; then
  grep -n -iE 'opcache|zend_extension' "$CONF" || echo "(none)"
fi

echo "=== conf.d opcache ==="
if [[ -n "$SCAN" && -d "$SCAN" ]]; then
  ls -la "$SCAN"
  grep -RniE 'opcache|zend_extension' "$SCAN" || echo "(none in scan dir)"
fi

echo "=== php -m ==="
php -m 2>&1 | grep -i opcache || echo "OPcache NOT in php -m"

echo "=== extension_dir ==="
php -r 'echo ini_get("extension_dir"), "\n";'

echo "=== FPM / httpd units ==="
systemctl list-units --type=service --all 2>/dev/null | grep -iE 'php-fpm|httpd|apache|litespeed' || true

echo "=== custombuild php ==="
if [[ -x /usr/local/directadmin/custombuild/build ]]; then
  /usr/local/directadmin/custombuild/build versions 2>/dev/null | grep -iE 'php|opcache' | head -20 || true
  grep -iE 'php|opcache' /usr/local/directadmin/custombuild/options.conf 2>/dev/null | head -40 || true
fi
