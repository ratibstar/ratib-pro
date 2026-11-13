#!/bin/bash
set -eu
DOC="/home/admin/domains/ai.rateb.sa"
TS="${1:-2026-11-13T0950Z-hr-admin}"
BACKUP="${DOC}/_deploy-backups/${TS}"
mkdir -p "${BACKUP}/files"
FILES="admin/settings.php admin/auth.php admin/users.php admin/user.php admin/bootstrap.php app/schema.php app/nav.php index.php app/auth/register.php app/auth/forgot-password.php"
for f in $FILES; do
  if [ -f "${DOC}/${f}" ]; then
    mkdir -p "${BACKUP}/files/$(dirname "${f}")"
    cp -a "${DOC}/${f}" "${BACKUP}/files/${f}"
  fi
done
PASS="$(tr -d '\r\n' < /home/admin/.secrets/rateb_ai_db_password)"
mysqldump -u admin_rateb_ai -p"${PASS}" --single-transaction admin_rateb_ai > "/home/admin/backups/admin_rateb_ai-before-${TS}.sql"
echo "FILE_BACKUP=${BACKUP}"
echo "DB_BACKUP=/home/admin/backups/admin_rateb_ai-before-${TS}.sql"
