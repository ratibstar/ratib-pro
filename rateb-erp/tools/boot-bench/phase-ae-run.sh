#!/bin/bash
# Phase AE — curl TTFB (DNS bypass) vs CLI PHP breakdown
set -eu
PHP=/usr/local/php83/bin/php
ROOT=/home/admin/domains/rateb.sa/public_html/rateb-erp
R='--resolve rateb.sa:443:167.233.71.107'
$PHP "$ROOT/tools/boot-bench/remote-auth.php" mint > /tmp/ae_mint.json
C=$($PHP -r '$j=json_decode(file_get_contents("/tmp/ae_mint.json"),true); echo $j["session_name"]."=".$j["session_id"];')
FMT='%{http_code}|%{time_namelookup}|%{time_starttransfer}|%{time_total}|%{size_download}|%{http_version}'

paths=(
  "/rateb-erp/public/admin/"
  "/rateb-erp/public/admin/hr?company_id=22"
  "/rateb-erp/public/admin/ops/inventory?company_id=22"
  "/rateb-erp/public/admin/ops/accounting?company_id=22"
  "/rateb-erp/public/admin/ops/purchase-requests?company_id=22"
)

echo "=== FPM HTTP TTFB (DNS bypass) ==="
for p in "${paths[@]}"; do
  # warm then measure
  curl -sk $R -b "$C" -o /dev/null "https://rateb.sa$p" >/dev/null
  raw=$(curl -sk $R -b "$C" -o /dev/null -w "$FMT" "https://rateb.sa$p")
  echo "$p => $raw"
done

echo "=== CLI PHP profiles ==="
$PHP "$ROOT/tools/boot-bench/phase-ae-ttfb-rootcause.php" /admin
$PHP "$ROOT/tools/boot-bench/phase-ae-ttfb-rootcause.php" /admin/hr
$PHP "$ROOT/tools/boot-bench/phase-ae-ttfb-rootcause.php" /admin/ops/inventory
$PHP "$ROOT/tools/boot-bench/phase-ae-ttfb-rootcause.php" /admin/ops/accounting
$PHP "$ROOT/tools/boot-bench/phase-ae-ttfb-rootcause.php" /admin/ops/purchase-requests
