#!/bin/bash
set -uo pipefail
cd "$(dirname "$0")/.."
python3 scripts/run-rcc-realtime-hub.py
exit $?
