#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
export RATEB_RESTORE_SUPER_ADMINS_AUTO=1
python3 scripts/run-restore-super-admins.py --auto
