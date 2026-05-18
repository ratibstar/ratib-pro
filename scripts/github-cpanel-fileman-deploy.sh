#!/bin/bash
# Proven cPanel Fileman upload — DO NOT replace with sync-all.py (API 404 on run #855).
# Entry point kept stable; core uses same API + [N/TOTAL] % with parallel workers.
set -euo pipefail
cd "$(dirname "$0")/.."
export CPANEL_UPLOAD_PARALLEL="${CPANEL_UPLOAD_PARALLEL:-4}"
exec python3 "$(dirname "$0")/github-cpanel-fileman-deploy-core.py"
