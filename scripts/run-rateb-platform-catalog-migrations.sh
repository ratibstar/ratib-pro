#!/bin/bash
# Run RATEB Platform Catalog migrations on production after deploy (SSH).
set -uo pipefail
cd "$(dirname "$0")/.."
python3 scripts/run-rateb-platform-catalog-migrations.py
exit $?
