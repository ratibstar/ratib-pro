#!/bin/bash
# OPcache verification report (CLI + optional FPM probe via erp-health if present).
set -euo pipefail
PHP_BIN=${PHP_BIN:-/usr/local/php83/bin/php}

echo "======== OPcache Verification Report ========"
echo "host=$(hostname)"
echo "date=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
echo "user=$(whoami)"
echo
echo "--- php -v ---"
"$PHP_BIN" -v 2>&1
echo
echo "--- php --ini ---"
"$PHP_BIN" --ini 2>&1
echo
echo "--- php -m | grep -i opcache ---"
if "$PHP_BIN" -m 2>&1 | grep -i opcache; then
  :
else
  echo "(none)"
fi
echo
echo "--- settings ---"
"$PHP_BIN" -r '
$err = [];
set_error_handler(function($no,$str) use (&$err) { $err[] = $str; });
$loaded = extension_loaded("Zend OPcache") || extension_loaded("opcache");
echo "OPcache_loaded=" . ($loaded ? "YES" : "NO") . PHP_EOL;
echo "opcache.enable=" . var_export(ini_get("opcache.enable"), true) . PHP_EOL;
echo "opcache.enable_cli=" . var_export(ini_get("opcache.enable_cli"), true) . PHP_EOL;
echo "opcache.memory_consumption=" . ini_get("opcache.memory_consumption") . PHP_EOL;
echo "opcache.interned_strings_buffer=" . ini_get("opcache.interned_strings_buffer") . PHP_EOL;
echo "opcache.max_accelerated_files=" . ini_get("opcache.max_accelerated_files") . PHP_EOL;
echo "opcache.validate_timestamps=" . ini_get("opcache.validate_timestamps") . PHP_EOL;
echo "opcache.revalidate_freq=" . ini_get("opcache.revalidate_freq") . PHP_EOL;
echo "opcache.save_comments=" . ini_get("opcache.save_comments") . PHP_EOL;
echo "opcache.enable_file_override=" . ini_get("opcache.enable_file_override") . PHP_EOL;
if (function_exists("opcache_get_status")) {
  $s = @opcache_get_status(false);
  if ($s === false) {
    echo "opcache_get_status=false (often normal under CLI without shared cache)" . PHP_EOL;
  } elseif (is_array($s)) {
    $mu = $s["memory_usage"] ?? [];
    echo "shared_memory_used_bytes=" . ($mu["used_memory"] ?? "n/a") . PHP_EOL;
    echo "shared_memory_free_bytes=" . ($mu["free_memory"] ?? "n/a") . PHP_EOL;
    echo "shared_memory_wasted_bytes=" . ($mu["wasted_memory"] ?? "n/a") . PHP_EOL;
    echo "opcache_enabled_runtime=" . (!empty($s["opcache_enabled"]) ? "YES" : "NO") . PHP_EOL;
    if (isset($s["opcache_statistics"]["num_cached_scripts"])) {
      echo "num_cached_scripts=" . $s["opcache_statistics"]["num_cached_scripts"] . PHP_EOL;
      echo "hits=" . ($s["opcache_statistics"]["hits"] ?? "") . PHP_EOL;
      echo "misses=" . ($s["opcache_statistics"]["misses"] ?? "") . PHP_EOL;
    }
  }
} else {
  echo "opcache_get_status=unavailable" . PHP_EOL;
}
if ($err) {
  echo "startup_errors=" . implode(" | ", $err) . PHP_EOL;
} else {
  echo "startup_errors=(none)" . PHP_EOL;
}
'
echo "======== end report ========"
