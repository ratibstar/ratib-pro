#!/usr/bin/env bash
# RATEB ERP v1.0.1 — staging bootstrap + deploy to dev.rateb.sa ONLY
set -euo pipefail

PROD="/home/admin/domains/rateb.sa/public_html"
DEV="/home/admin/domains/dev.rateb.sa/public_html"
ERP="$DEV/rateb-erp"

echo "=== STAGING BOOTSTRAP dev.rateb.sa ==="
mkdir -p "$DEV"

RSYNC=(rsync -rlptgoD --exclude='storage/backups/*.sql.gz' --exclude='storage/backups/*.tar.gz')

for path in rateb-erp config includes css js pages api control-panel; do
  if [ -d "$PROD/$path" ]; then
    echo "Sync $path ..."
    "${RSYNC[@]}" "$PROD/$path/" "$DEV/$path/"
  fi
done

for f in index.php .htaccess composer.json; do
  if [ -f "$PROD/$f" ]; then
    cp -a "$PROD/$f" "$DEV/$f"
  fi
done

echo "=== DEV .env (admin_rateb_dev only) ==="
if [ -f "$PROD/.env" ]; then
  grep -v '^RATEB_ERP_DB_NAME=' "$PROD/.env" | grep -v '^RATEB_ENV=' > "$DEV/.env" || true
else
  : > "$DEV/.env"
fi
echo 'RATEB_ERP_DB_NAME=admin_rateb_dev' >> "$DEV/.env"
echo 'RATEB_ENV=staging' >> "$DEV/.env"

echo "=== DEV host env profile ==="
cat > "$DEV/config/env/dev_rateb_sa.php" << 'PHPEOF'
<?php
if (defined('DB_NAME')) {
    return;
}
require_once __DIR__ . '/directadmin_db.php';
$dbHost = getenv('DB_HOST');
$dbPort = getenv('DB_PORT');
$dbUser = getenv('DB_USER');
$dbPass = getenv('DB_PASS');
$dbName = getenv('DB_NAME');
define('DB_HOST', ($dbHost !== false && $dbHost !== '') ? (string) $dbHost : 'localhost');
define('DB_PORT', ($dbPort !== false && $dbPort !== '') ? (int) $dbPort : 3306);
define('DB_USER', ($dbUser !== false && $dbUser !== '') ? (string) $dbUser : rateb_default_mysql_user());
define('DB_PASS', ($dbPass !== false && $dbPass !== '') ? (string) $dbPass : '');
define('DB_NAME', ($dbName !== false && $dbName !== '') ? (string) $dbName : rateb_main_pro_database());
define('CONTROL_PANEL_DB_NAME', getenv('CONTROL_PANEL_DB_NAME') ?: rateb_control_panel_database());
if (!defined('RATEB_ERP_DB_NAME')) {
    $_erpDb = getenv('RATEB_ERP_DB_NAME');
    define('RATEB_ERP_DB_NAME', ($_erpDb !== false && $_erpDb !== '') ? (string) $_erpDb : 'admin_rateb_dev');
}
define('SITE_URL', 'https://dev.rateb.sa');
define('SINGLE_URL_MODE', true);
define('APP_NAME', 'RATEB');
define('APP_VERSION', '1.0.1');
define('BASE_URL', '');
define('NO_BANGLA', true);
define('OBSERVABILITY_DASHBOARD_ENABLED', true);
define('ADMIN_CONTROL_CENTER_ENABLED', true);
PHPEOF

echo "=== CACHE CLEAR ==="
rm -rf "$ERP/storage/cache/"* 2>/dev/null || true
find "$ERP/storage" -name '*.php' -path '*/cache/*' -delete 2>/dev/null || true

echo "=== PERMISSIONS ==="
chmod -R u+rwX "$ERP/storage" 2>/dev/null || true

echo "=== DB CONNECT TEST (admin_rateb_dev) ==="
cd "$ERP"
php << 'PHPEOF'
<?php
define('RATEB_ROOT', getcwd());
require 'app/Core/Bootstrap.php';
Rateb\App\Core\Bootstrap::init(RATEB_ROOT);
putenv('RATEB_ERP_DB_NAME=admin_rateb_dev');
$_ENV['RATEB_ERP_DB_NAME'] = 'admin_rateb_dev';
try {
    $db = Rateb\App\Core\Database::connection();
    $db->query('SELECT 1');
    $name = Rateb\App\Core\Database::resolvedDatabaseName();
    echo "DB_OK name={$name}\n";
} catch (Throwable $e) {
    echo 'DB_FAIL ' . $e->getMessage() . "\n";
    exit(1);
}
PHPEOF

echo "=== BUILD MARKER ==="
cat "$ERP/public/ratib-erp-build.txt" 2>/dev/null || echo MISSING

echo "=== STAGING BOOTSTRAP DONE ==="
