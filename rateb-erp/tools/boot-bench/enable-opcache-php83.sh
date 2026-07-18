#!/bin/bash
# PERF-P1 — Enable Zend OPcache for PHP 8.3 (MUST run as root).
# opcache.so is present but commented out in php.ini.
set -euo pipefail
INI=/usr/local/php83/lib/php.ini
EXT=/usr/local/php83/lib/php/extensions/no-debug-non-zts-20230831/opcache.so
DROPIN=/usr/local/php83/lib/php.conf.d/99-rateb-opcache.ini

if [[ $(id -u) -ne 0 ]]; then
  echo "ERROR: run as root: sudo bash $0" >&2
  exit 1
fi
if [[ ! -f "$EXT" ]]; then
  echo "ERROR: missing $EXT" >&2
  exit 1
fi

cat > "$DROPIN" <<EOF
zend_extension=$EXT
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
opcache.save_comments=1
EOF

# Also uncomment classic php.ini lines if still present
sed -i 's/^;zend_extension=opcache$/zend_extension=opcache/' "$INI" || true
sed -i 's/^;opcache.enable=1$/opcache.enable=1/' "$INI" || true
sed -i 's/^;opcache.enable_cli=0$/opcache.enable_cli=1/' "$INI" || true

systemctl restart php-fpm83 2>/dev/null || service php-fpm83 restart 2>/dev/null || /usr/local/php83/sbin/php-fpm --nodaemonize &
sleep 1
/usr/local/php83/bin/php -m | grep -i opcache
/usr/local/php83/bin/php -r 'var_export(function_exists("opcache_get_status")); echo "\n";'
echo "OPcache enable complete."
