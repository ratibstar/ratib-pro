#!/bin/bash
set -eu
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
R='--resolve rateb.sa:443:167.233.71.107'
$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/ae_mint.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/ae_mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')
OUT=/tmp/ae_fpm_probe_results.jsonl
: > "$OUT"
for p in /admin /admin/hr /admin/ops/inventory /admin/ops/accounting /admin/ops/purchase-requests; do
  echo "===== $p ====="
  curl -sk $R -b "$C" "https://rateb.sa/rateb-erp/public/_ae_fpm_probe.php?path=$p" | tee -a "$OUT"
  echo >> "$OUT"
done
rm -f "$ROOT/public/_ae_fpm_probe.php"
echo PROBE_REMOVED
# also save summary
cp "$OUT" "$ROOT/tools/boot-bench/reports/phase-ae-fpm-probe.jsonl"
