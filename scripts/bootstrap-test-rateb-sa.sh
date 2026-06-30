#!/usr/bin/env bash
# Copy rateb.sa app tree → test.rateb.sa (same as pages/rateb-bootstrap-test-domain.php)
set -euo pipefail

PROD="/home/admin/domains/rateb.sa/public_html"
TEST="/home/admin/domains/test.rateb.sa/public_html"

echo "=== BOOTSTRAP test.rateb.sa from rateb.sa ==="
mkdir -p "$TEST"

RSYNC=(rsync -rlptgoD --exclude='storage/backups/*.sql.gz' --exclude='storage/backups/*.tar.gz')

for path in rateb-erp config includes css js pages api control-panel admin public; do
  if [ -d "$PROD/$path" ]; then
    echo "Sync $path ..."
    "${RSYNC[@]}" "$PROD/$path/" "$TEST/$path/"
  fi
done

for f in index.php .htaccess composer.json control.php; do
  if [ -f "$PROD/$f" ]; then
    cp -a "$PROD/$f" "$TEST/$f"
  fi
done

if [ -f "$PROD/.env" ] && [ ! -f "$TEST/.env" ]; then
  cp -a "$PROD/.env" "$TEST/.env"
fi

echo "Done. Test: https://test.rateb.sa/pages/login.php"
