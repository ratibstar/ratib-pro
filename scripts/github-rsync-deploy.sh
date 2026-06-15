#!/bin/bash
# DirectAdmin / SSH rsync deploy (same fast file list as cPanel Fileman).
set -uo pipefail
cd "$(dirname "$0")/.."

DEPLOY_SSH_HOST="${DEPLOY_SSH_HOST:-${SSH_HOST:-}}"
DEPLOY_SSH_USER="${DEPLOY_SSH_USER:-${SSH_USER:-}}"
DEPLOY_SSH_PRIVATE_KEY="${DEPLOY_SSH_PRIVATE_KEY:-${SSH_PRIVATE_KEY:-}}"
DEPLOY_REMOTE_BASE="${DEPLOY_REMOTE_BASE:-${CPANEL_REMOTE_BASE:-/home/admin/domains/rateb.sa/public_html}}"
DEPLOY_MODE="${DEPLOY_MODE:-${CPANEL_DEPLOY_MODE:-fast}}"

export DEPLOY_SSH_HOST DEPLOY_SSH_USER DEPLOY_SSH_PRIVATE_KEY
export DEPLOY_REMOTE_BASE DEPLOY_MODE
export DEPLOY_SSH_PORT="${DEPLOY_SSH_PORT:-${SSH_PORT:-22}}"
export RATEB_ERP_MIGRATE_TOKEN="${RATEB_ERP_MIGRATE_TOKEN:-${CPANEL_API_TOKEN:-}}"

if [ -z "$DEPLOY_SSH_HOST" ] || [ -z "$DEPLOY_SSH_USER" ] || [ -z "$DEPLOY_SSH_PRIVATE_KEY" ]; then
  echo "::error::DEPLOY_SSH_HOST, DEPLOY_SSH_USER, DEPLOY_SSH_PRIVATE_KEY required for rsync deploy"
  exit 1
fi

python3 "$(dirname "$0")/github-rsync-deploy.py"
exit $?
