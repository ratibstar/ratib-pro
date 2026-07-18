#!/bin/bash
# PERF-P1 — Keep PHP-FPM ondemand workers warm (idle timeout is 20s).
# No root required. Hits lightweight health endpoint every 15s.
set -eu
URL="https://rateb.sa/rateb-erp/public/erp-health.php"
curl -sk --max-time 8 -o /dev/null "$URL" || true
