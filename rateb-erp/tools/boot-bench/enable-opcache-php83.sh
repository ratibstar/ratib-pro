#!/bin/bash
# Enable Zend OPcache for PHP 8.3 on DirectAdmin (MUST run as root).
# Settings: production profile (validate_timestamps=0 — restart php-fpm83 after PHP deploys).
# Uses a single drop-in so settings are not duplicated with commented php.ini defaults.
set -euo pipefail

INI=/usr/local/php83/lib/php.ini
EXT=/usr/local/php83/lib/php/extensions/no-debug-non-zts-20230831/opcache.so
DROPIN=/usr/local/php83/lib/php.conf.d/99-rateb-opcache.ini
PHP_BIN=/usr/local/php83/bin/php

if [[ $(id -u) -ne 0 ]]; then
  echo "ERROR: run as root: sudo bash $0" >&2
  exit 1
fi

if [[ ! -f "$EXT" ]]; then
  echo "ERROR: opcache.so not installed at $EXT" >&2
  echo "Do not edit php.ini. Install via CustomBuild as root, then re-run." >&2
  exit 2
fi

if [[ ! -f "$INI" ]]; then
  echo "ERROR: missing $INI" >&2
  exit 1
fi

# Backup once
if [[ ! -f "${INI}.bak-before-opcache" ]]; then
  cp -a "$INI" "${INI}.bak-before-opcache"
fi

# Ensure classic php.ini zend_extension line stays commented to avoid duplicate load
# (drop-in owns the active zend_extension= absolute path).
if grep -qE '^zend_extension=opcache$' "$INI" 2>/dev/null; then
  sed -i 's/^zend_extension=opcache$/;zend_extension=opcache/' "$INI"
fi

# Remove any prior partial opcache drop-ins we own; write exactly once
rm -f /usr/local/php83/lib/php.conf.d/*rateb-opcache*.ini 2>/dev/null || true

cat > "$DROPIN" <<'EOF'
; RATEB production OPcache — single source of truth (do not duplicate in php.ini)
zend_extension=/usr/local/php83/lib/php/extensions/no-debug-non-zts-20230831/opcache.so
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=32
opcache.max_accelerated_files=100000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
opcache.enable_file_override=1
EOF
chmod 644 "$DROPIN"

# Restart only requested services
if systemctl restart php-fpm83; then
  echo "Restarted php-fpm83"
else
  echo "ERROR: php-fpm83 restart failed" >&2
  exit 1
fi

if systemctl restart httpd; then
  echo "Restarted httpd"
elif systemctl restart apache2 2>/dev/null; then
  echo "Restarted apache2"
else
  echo "WARN: httpd/apache2 restart failed (FPM may still be OK)" >&2
fi

sleep 1

echo "=== php -v ==="
"$PHP_BIN" -v
echo "=== php --ini ==="
"$PHP_BIN" --ini
echo "=== php -m | opcache ==="
"$PHP_BIN" -m | grep -i opcache || true

echo "=== verification ==="
"$PHP_BIN" -r '
$loaded = extension_loaded("Zend OPcache") || extension_loaded("opcache");
echo "OPcache loaded: " . ($loaded ? "YES" : "NO") . PHP_EOL;
foreach ([
  "opcache.enable",
  "opcache.enable_cli",
  "opcache.memory_consumption",
  "opcache.interned_strings_buffer",
  "opcache.max_accelerated_files",
  "opcache.validate_timestamps",
  "opcache.revalidate_freq",
  "opcache.save_comments",
  "opcache.enable_file_override",
] as $k) {
  echo $k . "=" . ini_get($k) . PHP_EOL;
}
if (function_exists("opcache_get_status")) {
  $s = @opcache_get_status(false);
  if (is_array($s) && isset($s["memory_usage"]["used_memory"])) {
    echo "shared_memory_used_bytes=" . $s["memory_usage"]["used_memory"] . PHP_EOL;
    echo "shared_memory_free_bytes=" . ($s["memory_usage"]["free_memory"] ?? "") . PHP_EOL;
  }
}
'
echo "OPcache enable complete."
echo "NOTE: validate_timestamps=0 — restart php-fpm83 after every PHP code deploy."
