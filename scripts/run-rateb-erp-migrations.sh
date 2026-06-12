#!/bin/bash
# Run RATEB ERP migrations on production after deploy.
set -uo pipefail
cd "$(dirname "$0")/.."
python3 scripts/run-rateb-erp-migrations.py
exit $?
