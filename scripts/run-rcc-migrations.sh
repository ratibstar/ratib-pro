#!/bin/bash
# Run RATIB Contact Center migrations on production after deploy.
set -uo pipefail
cd "$(dirname "$0")/.."
python3 scripts/run-rcc-migrations.py
exit $?
