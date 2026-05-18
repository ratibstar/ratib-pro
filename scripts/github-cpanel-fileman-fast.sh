#!/bin/bash
# Use proven Fileman deploy (do not use sync-all.py — it caused API 404 errors).
set -euo pipefail
export CPANEL_DEPLOY_MODE="${CPANEL_DEPLOY_MODE:-fast}"
exec bash "$(dirname "$0")/github-cpanel-fileman-deploy.sh"
