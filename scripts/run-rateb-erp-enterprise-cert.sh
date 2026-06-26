#!/bin/bash
# Run RATEB ERP enterprise certification on production (official dev DB).
set -uo pipefail
cd "$(dirname "$0")/.."
python3 scripts/run-rateb-erp-enterprise-cert.py
exit $?
