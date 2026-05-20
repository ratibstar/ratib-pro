#!/usr/bin/env bash
# Production-safe infrastructure migrations (control panel DB).
# Uses CREATE TABLE IF NOT EXISTS / idempotent DDL — no destructive drops.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
MIG_DIR="${ROOT}/modules/infrastructure-marketplace/Migrations"

DB_HOST="${RATIB_INFRA_MYSQL_HOST:-127.0.0.1}"
DB_PORT="${RATIB_INFRA_MYSQL_PORT:-3306}"
DB_USER="${RATIB_INFRA_MYSQL_USER:-}"
DB_NAME="${RATIB_INFRA_MYSQL_DB:-${CONTROL_PANEL_DB_NAME:-outratib_control_panel_db}}"

if [[ -z "${DB_USER}" ]]; then
  echo "Set RATIB_INFRA_MYSQL_USER (and password via RATIB_INFRA_MYSQL_PASS or prompt)." >&2
  exit 1
fi

MYSQL=(mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" "${DB_NAME}")
if [[ -n "${RATIB_INFRA_MYSQL_PASS:-}" ]]; then
  export MYSQL_PWD="${RATIB_INFRA_MYSQL_PASS}"
fi

ORDER=(
  "001_foundation.sql"
  "002_operational_layer.sql"
  "003_execution_layer.sql"
  "004_state_machine_normalization.sql"
  "005_provider_activation_marketplace.sql"
  "006_release_safety.sql"
  "008_provider_secrets_and_events.sql"
)

echo "Target DB: ${DB_NAME} on ${DB_HOST}:${DB_PORT}"
echo "Applying migrations in order..."

for file in "${ORDER[@]}"; do
  path="${MIG_DIR}/${file}"
  if [[ ! -f "${path}" ]]; then
    echo "SKIP missing ${file}"
    continue
  fi
  echo "==> ${file}"
  "${MYSQL[@]}" < "${path}"
done

echo "Done. Verify with:"
echo "  php modules/infrastructure-marketplace/Cli/production-verify.php"
