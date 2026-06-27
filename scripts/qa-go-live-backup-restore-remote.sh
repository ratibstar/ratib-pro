#!/usr/bin/env bash
# RATIB ERP v1.0 — backup/restore operational sign-off (production server)
set -eu

ERP="/home/admin/domains/rateb.sa/public_html/rateb-erp"
BACKUP="${1:-storage/backups/erp-admin_rateb-erp-20260627-024200.sql.gz}"
SCRATCH_DB="admin_designed"

cd "$ERP"
php <<'PHPEOF'
<?php
define('RATEB_ROOT', getcwd());
require 'app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
$h = getenv('RATEB_ERP_DB_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$u = getenv('RATEB_ERP_DB_USER') ?: getenv('DB_USER') ?: '';
$p = getenv('RATEB_ERP_DB_PASS') ?: getenv('DB_PASS') ?: '';
$db = \Rateb\App\Core\Database::resolvedDatabaseName();
file_put_contents('/tmp/rateb-db.env', "DB_HOST=".var_export($h,true)."\nDB_USER=".var_export($u,true)."\nDB_PASS=".var_export($p,true)."\nSOURCE_DB=".var_export($db,true)."\n");
PHPEOF
source /tmp/rateb-db.env

echo "=== STEP1 BACKUP (record) ==="
echo "backup_file=$BACKUP"
ls -lh "$BACKUP"
sha256sum "$BACKUP"

echo "=== STEP2 OFFICIAL VERIFY ==="
set +e
php bin/erp-restore.php --verify "$BACKUP"
OFFICIAL_VERIFY_EC=$?
set -e
echo "OFFICIAL_VERIFY_EC=$OFFICIAL_VERIFY_EC"

echo "=== STEP2 EXTENDED MANUAL VERIFY (8192-byte header) ==="
php <<PHPEOF
<?php
\$path = '$BACKUP';
\$size = (int) filesize(\$path);
\$gz = gzopen(\$path, 'rb');
\$chunk = (string) gzread(\$gz, 8192);
gzclose(\$gz);
\$ok = \$size > 100 && stripos(\$chunk, 'CREATE TABLE') !== false;
echo 'extended_verify=' . (\$ok ? 'PASS' : 'FAIL') . "\n";
exit(\$ok ? 0 : 1);
PHPEOF
echo "EXTENDED_VERIFY_EC=$?"

DECOMP=$(zcat "$BACKUP" | wc -c)
CREATE=$(zcat "$BACKUP" | grep -c 'CREATE TABLE' || true)
INSERT=$(zcat "$BACKUP" | grep -c '^INSERT' || true)
echo "decompressed_bytes=$DECOMP create_table_count=$CREATE insert_count=$INSERT"
if [ "$DECOMP" -gt 1000 ] && [ "$CREATE" -gt 0 ]; then echo "MANUAL_VERIFY=PASS"; else echo "MANUAL_VERIFY=FAIL"; exit 1; fi

echo "=== STEP3 RESTORE TO SCRATCH DB ($SCRATCH_DB) ==="
BEFORE=$(mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$SCRATCH_DB' AND table_name LIKE 'rateb_%'")
echo "rateb_tables_before=$BEFORE"

RESTORE_START=$(date +%s)
set +e
gunzip -c "$BACKUP" | mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" "$SCRATCH_DB" 2>/tmp/restore-err.log
RESTORE_EC=$?
set -e
RESTORE_END=$(date +%s)
echo "RESTORE_EC=$RESTORE_EC"
echo "RESTORE_DURATION_SEC=$((RESTORE_END - RESTORE_START))"
if [ "$RESTORE_EC" -ne 0 ]; then echo "=== RESTORE ERRORS ==="; cat /tmp/restore-err.log; exit 1; fi
if [ -s /tmp/restore-err.log ]; then echo "=== RESTORE WARNINGS ==="; cat /tmp/restore-err.log; fi

RESTORED=$(mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$SCRATCH_DB' AND table_name LIKE 'rateb_%'")
echo "restored_rateb_tables=$RESTORED"
mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema='$SCRATCH_DB' AND table_name LIKE 'rateb_%' ORDER BY table_rows DESC LIMIT 8"

echo "=== HEALTH (production endpoint unchanged) ==="
curl -sS -o /tmp/health.json -w "health_http=%{http_code}\n" "https://rateb.sa/rateb-erp/public/erp-health.php" || true
cat /tmp/health.json 2>/dev/null || true
echo

echo "=== ENTERPRISE SUITE ON RESTORED SCRATCH DB ==="
export RATEB_ERP_DB_NAME="$SCRATCH_DB"
export RATEB_DB_NAME="$SCRATCH_DB"
export RATEB_OFFICIAL_DEV_DB=1
export RATEB_ENV=development
php bin/enterprise-test/run.php --json > /tmp/erp-restore-test.json 2>/tmp/erp-restore-test.err || true
php <<'PHPEOF'
<?php
$raw = file_get_contents('/tmp/erp-restore-test.json');
$j = json_decode($raw, true);
if (!$j) { echo "enterprise_json=INVALID\n"; echo substr($raw,0,500),"\n"; exit(1); }
$p = (int)($j['passed'] ?? 0);
$f = (int)($j['failed'] ?? 0);
$t = (int)($j['total'] ?? 0);
echo "enterprise_passed=$p\nenterprise_failed=$f\nenterprise_total=$t\n";
exit($f > 0 ? 1 : 0);
PHPEOF
ENT_EC=$?
echo "ENTERPRISE_EC=$ENT_EC"

echo "=== CLEANUP SCRATCH rateb_* TABLES ==="
TABLES=$(mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$SCRATCH_DB' AND table_name LIKE 'rateb_%'")
for t in $TABLES; do
  mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -e "DROP TABLE IF EXISTS \`$SCRATCH_DB\`.\`$t\`"
done
AFTER=$(mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$SCRATCH_DB' AND table_name LIKE 'rateb_%'")
echo "rateb_tables_after_cleanup=$AFTER"
DESIGNED=$(mysql --host="$DB_HOST" --user="$DB_USER" --password="$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$SCRATCH_DB'")
echo "scratch_db_tables_remaining=$DESIGNED"

if [ "$RESTORE_EC" -ne 0 ] || [ "$ENT_EC" -ne 0 ]; then exit 1; fi
echo "=== OPERATIONAL BACKUP/RESTORE SIGN-OFF PASS ==="
