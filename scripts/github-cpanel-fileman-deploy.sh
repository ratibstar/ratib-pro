#!/bin/bash
# Proven cPanel Fileman upload (same API as run #858). DO NOT use sync-all.py.
set -uo pipefail
cd "$(dirname "$0")/.."

CPANEL_HOST="${CPANEL_HOST:?CPANEL_HOST required}"
CPANEL_USER="${CPANEL_USER:?CPANEL_USER required}"
CPANEL_API_TOKEN="${CPANEL_API_TOKEN:?CPANEL_API_TOKEN required}"
CPANEL_PORT="${CPANEL_PORT:-2083}"
REMOTE_BASE="${CPANEL_REMOTE_BASE:-/home/admin/public_html}"
MODE="${CPANEL_DEPLOY_MODE:-critical}"

export CPANEL_HOST CPANEL_USER CPANEL_API_TOKEN CPANEL_PORT
export CPANEL_REMOTE_BASE="${REMOTE_BASE}"
export CPANEL_DEPLOY_MODE="${MODE}"

python3 "$(dirname "$0")/github-cpanel-fileman-deploy-core.py"
exit $?
