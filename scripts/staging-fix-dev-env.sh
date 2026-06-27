#!/usr/bin/env bash
set -euo pipefail
DEV=/home/admin/domains/dev.rateb.sa/public_html
grep -v '^RATEB_ERP_DB_USER=' "$DEV/.env" | grep -v '^RATEB_ERP_DB_PASS=' > /tmp/dev.env.tmp
mv /tmp/dev.env.tmp "$DEV/.env"
echo 'RATEB_ERP_DB_USER=admin_rateb_dev' >> "$DEV/.env"
PASS=$(grep '^DB_PASS=' "$DEV/.env" | cut -d= -f2-)
echo "RATEB_ERP_DB_PASS=$PASS" >> "$DEV/.env"
grep '^RATEB_ERP' "$DEV/.env" | sed 's/PASS=.*/PASS=***/'
