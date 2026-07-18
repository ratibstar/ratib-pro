#!/bin/bash
set -eu
URL=https://rateb.sa/rateb-erp/public/erp-health.php
measure() {
  local label="$1"
  local start end ms
  start=$(date +%s%N)
  curl -sk --max-time 15 -o /dev/null "$URL"
  end=$(date +%s%N)
  ms=$(( (end - start) / 1000000 ))
  echo "$label ${ms}ms"
}
measure w1
measure w2
measure w3
echo sleep22
sleep 22
measure after_idle
measure after_idle2
