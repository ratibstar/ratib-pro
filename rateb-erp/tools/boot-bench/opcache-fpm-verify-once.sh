#!/bin/bash
# Temporary PHP-FPM OPcache verification (create → measure → warm → measure → delete).
set -euo pipefail

PUBLIC="/home/admin/domains/rateb.sa/public_html/rateb-erp/public"
ROOT="/home/admin/domains/rateb.sa/public_html/rateb-erp"
BASE="https://rateb.sa/rateb-erp/public"
TOKEN=$(openssl rand -hex 24)
DIAG_NAME=".__opcache_fpm_diag_${TOKEN:0:12}.php"
DIAG_PATH="${PUBLIC}/${DIAG_NAME}"
DIAG_URL="${BASE}/${DIAG_NAME}?t=${TOKEN}"
OUTDIR="/tmp/opcache-fpm-verify-$$"
mkdir -p "$OUTDIR"
COOKIE="$OUTDIR/cookie.txt"
REPORT="/tmp/opcache-fpm-verify-report.json"

cleanup() {
  rm -f "$DIAG_PATH" 2>/dev/null || true
  rm -f "$COOKIE" 2>/dev/null || true
}
trap cleanup EXIT

cat > "$DIAG_PATH" <<PHPEOF
<?php
declare(strict_types=1);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Content-Type: application/json; charset=UTF-8');
header('X-Robots-Tag: noindex, nofollow');
\$expected = '${TOKEN}';
\$got = (string)(\$_GET['t'] ?? '');
if (!hash_equals(\$expected, \$got)) {
    http_response_code(404);
    echo '{"ok":false,"error":"not_found"}';
    exit;
}
\$st = function_exists('opcache_get_status') ? @opcache_get_status(false) : null;
\$cfg = function_exists('opcache_get_configuration') ? @opcache_get_configuration() : null;
\$stats = is_array(\$st) ? (\$st['opcache_statistics'] ?? []) : [];
\$mem = is_array(\$st) ? (\$st['memory_usage'] ?? []) : [];
\$hits = (int)(\$stats['hits'] ?? 0);
\$misses = (int)(\$stats['misses'] ?? 0);
\$total = \$hits + \$misses;
\$hitRate = \$total > 0 ? round((\$hits / \$total) * 100, 2) : 0.0;
\$directives = [];
if (is_array(\$cfg) && isset(\$cfg['directives']) && is_array(\$cfg['directives'])) {
    foreach ([
        'opcache.enable', 'opcache.enable_cli', 'opcache.memory_consumption',
        'opcache.max_accelerated_files', 'opcache.validate_timestamps',
        'opcache.revalidate_freq', 'opcache.interned_strings_buffer',
    ] as \$k) {
        if (array_key_exists(\$k, \$cfg['directives'])) {
            \$directives[\$k] = \$cfg['directives'][\$k];
        }
    }
}
echo json_encode([
    'ok' => true,
    'via' => 'php-fpm-web',
    'sapi' => PHP_SAPI,
    'opcache_loaded' => extension_loaded('Zend OPcache') || extension_loaded('opcache'),
    'opcache_enabled' => is_array(\$st) && !empty(\$st['opcache_enabled']),
    'num_cached_scripts' => (int)(\$stats['num_cached_scripts'] ?? 0),
    'hits' => \$hits,
    'misses' => \$misses,
    'hit_rate' => \$hitRate,
    'used_memory' => (int)(\$mem['used_memory'] ?? 0),
    'free_memory' => (int)(\$mem['free_memory'] ?? 0),
    'wasted_memory' => (int)(\$mem['wasted_memory'] ?? 0),
    'restart_pending' => is_array(\$st) && !empty(\$st['restart_pending']),
    'restart_in_progress' => is_array(\$st) && !empty(\$st['restart_in_progress']),
    'directives' => \$directives,
    'ts' => gmdate('c'),
], JSON_UNESCAPED_SLASHES);
PHPEOF
chmod 644 "$DIAG_PATH"

echo "=== BEFORE ==="
curl -sk -o "$OUTDIR/before.json" -w "http=%{http_code} ttfb=%{time_starttransfer}\n" "$DIAG_URL"
cat "$OUTDIR/before.json"; echo

AUTH="$ROOT/tools/boot-bench/remote-auth.php"
[[ -f "$AUTH" ]] || AUTH="/tmp/remote-auth.php"
php "$AUTH" mint > "$OUTDIR/mint.json"
php -r "
\$j=json_decode(file_get_contents('$OUTDIR/mint.json'), true);
if (empty(\$j['session_id'])) { fwrite(STDERR, 'mint failed\n'); exit(1); }
file_put_contents('$COOKIE', sprintf(\"rateb.sa\\tFALSE\\t/\\tTRUE\\t0\\t%s\\t%s\\n\", \$j['session_name'], \$j['session_id']));
echo 'mint_ok\n';
"

echo "=== WARM ==="
for u in \
  "${BASE}/admin/" \
  "${BASE}/admin/dashboard" \
  "${BASE}/admin/hr" \
  "${BASE}/admin/inventory" \
  "${BASE}/admin/crm" \
  "${BASE}/admin/settings" \
  "${BASE}/erp-health.php" \
  "${BASE}/admin/" \
  "${BASE}/admin/dashboard" \
  "${BASE}/admin/"
do
  curl -sk -L -o /dev/null -b "$COOKIE" -c "$COOKIE" -H "Accept: text/html" \
    -w "warm http=%{http_code} ttfb=%{time_starttransfer} %{url_effective}\n" "$u"
done

echo "=== AFTER ==="
curl -sk -o "$OUTDIR/after.json" -w "http=%{http_code} ttfb=%{time_starttransfer}\n" "$DIAG_URL"
cat "$OUTDIR/after.json"; echo

echo "=== TTFB samples ==="
: > "$OUTDIR/ttfb_samples.txt"
for i in 1 2 3 4 5; do
  curl -sk -L -o /dev/null -b "$COOKIE" -H "Accept: text/html" \
    -w "sample${i} http=%{http_code} ttfb=%{time_starttransfer} total=%{time_total}\n" \
    "${BASE}/admin/" | tee -a "$OUTDIR/ttfb_samples.txt"
done

PM_LINE=$(grep -E '^pm\s*=' /usr/local/directadmin/data/users/admin/php/php-fpm83.conf | head -1 | tr -d '\r')
IDLE=$(grep -E '^pm\.process_idle_timeout' /usr/local/directadmin/data/users/admin/php/php-fpm83.conf | head -1 | tr -d '\r' || true)
MAXC=$(grep -E '^pm\.max_children' /usr/local/directadmin/data/users/admin/php/php-fpm83.conf | head -1 | tr -d '\r' || true)
NPROC=$(nproc)
MEM_MB=$(free -m | awk '/^Mem:/{print $2}')

php -r "
\$before = json_decode(file_get_contents('$OUTDIR/before.json'), true);
\$after  = json_decode(file_get_contents('$OUTDIR/after.json'), true);
\$ttfb = file_get_contents('$OUTDIR/ttfb_samples.txt');
preg_match_all('/ttfb=([0-9.]+)/', \$ttfb, \$m);
\$samples = array_map('floatval', \$m[1] ?? []);
sort(\$samples);
\$n = count(\$samples);
\$median = \$n ? \$samples[(int)floor((\$n-1)/2)] : null;

\$reasons = [];
\$sapi = (string)(\$after['sapi'] ?? '');
\$fpmSapi = (\$sapi === 'fpm-fcgi' || str_contains(\$sapi, 'fpm'));
if (!is_array(\$before) || empty(\$before['ok'])) \$reasons[] = 'before_diag_failed';
if (!is_array(\$after) || empty(\$after['ok'])) \$reasons[] = 'after_diag_failed';
if (!\$fpmSapi) \$reasons[] = 'not_fpm_sapi:' . \$sapi;
if (empty(\$after['opcache_loaded'])) \$reasons[] = 'opcache_not_loaded';
if (empty(\$after['opcache_enabled'])) \$reasons[] = 'opcache_not_enabled';
\$bScripts = (int)(\$before['num_cached_scripts'] ?? 0);
\$aScripts = (int)(\$after['num_cached_scripts'] ?? 0);
\$bHits = (int)(\$before['hits'] ?? 0);
\$aHits = (int)(\$after['hits'] ?? 0);
if (\$aScripts < 10) \$reasons[] = 'too_few_cached_scripts:' . \$aScripts;
if (\$aScripts <= \$bScripts && \$aHits <= \$bHits) \$reasons[] = 'cache_did_not_grow';

\$pass = count(\$reasons) === 0;
\$report = [
  'verdict' => \$pass ? 'PASS' : 'FAIL',
  'reasons' => \$reasons,
  'evidence' => [
    'accessed_via' => 'https web → Apache → php-fpm83',
    'sapi' => \$sapi,
    'diag_token_gated' => true,
    'scripts_listed' => false,
  ],
  'before' => [
    'opcache_enabled' => \$before['opcache_enabled'] ?? null,
    'num_cached_scripts' => \$bScripts,
    'hits' => \$bHits,
    'misses' => (int)(\$before['misses'] ?? 0),
    'hit_rate' => \$before['hit_rate'] ?? null,
    'used_memory' => (int)(\$before['used_memory'] ?? 0),
    'free_memory' => (int)(\$before['free_memory'] ?? 0),
    'wasted_memory' => (int)(\$before['wasted_memory'] ?? 0),
    'restart_pending' => \$before['restart_pending'] ?? null,
    'restart_in_progress' => \$before['restart_in_progress'] ?? null,
  ],
  'after' => [
    'opcache_enabled' => \$after['opcache_enabled'] ?? null,
    'num_cached_scripts' => \$aScripts,
    'hits' => \$aHits,
    'misses' => (int)(\$after['misses'] ?? 0),
    'hit_rate' => \$after['hit_rate'] ?? null,
    'used_memory' => (int)(\$after['used_memory'] ?? 0),
    'free_memory' => (int)(\$after['free_memory'] ?? 0),
    'wasted_memory' => (int)(\$after['wasted_memory'] ?? 0),
    'restart_pending' => \$after['restart_pending'] ?? null,
    'restart_in_progress' => \$after['restart_in_progress'] ?? null,
    'directives' => \$after['directives'] ?? null,
  ],
  'delta' => [
    'num_cached_scripts' => \$aScripts - \$bScripts,
    'hits' => \$aHits - \$bHits,
    'misses' => (int)(\$after['misses'] ?? 0) - (int)(\$before['misses'] ?? 0),
    'used_memory' => (int)(\$after['used_memory'] ?? 0) - (int)(\$before['used_memory'] ?? 0),
  ],
  'ttfb_admin_origin_sec' => [
    'samples' => \$samples,
    'min' => \$n ? min(\$samples) : null,
    'median' => \$median,
    'max' => \$n ? max(\$samples) : null,
  ],
  'fpm' => [
    'pm_line' => '$PM_LINE',
    'idle_timeout_line' => '$IDLE',
    'max_children_line' => '$MAXC',
    'nproc' => (int)$NPROC,
    'mem_mb' => (int)$MEM_MB,
  ],
  'diag_file_removed' => !file_exists('$DIAG_PATH'),
  'ts' => gmdate('c'),
];
file_put_contents('$REPORT', json_encode(\$report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode(\$report, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), PHP_EOL;
"

rm -f "$DIAG_PATH"
if [[ -f "$DIAG_PATH" ]]; then
  echo "FATAL: diag still present" >&2
  exit 2
fi
echo "diag_removed=YES"
echo "REPORT=$REPORT"
