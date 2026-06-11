#!/bin/bash
# Run RATEB ERP migrations on production after deploy (uses cPanel API token).
set -uo pipefail

SITE="${CPANEL_SITE_URL:-https://out.ratib.sa}"
TOKEN="${RATEB_ERP_MIGRATE_TOKEN:-${CPANEL_API_TOKEN:-}}"
REMOTE_BASE="${CPANEL_REMOTE_BASE:-/home/outratib/public_html}"

if [ -z "$TOKEN" ]; then
  echo "::warning::RATEB ERP migrations skipped — no migrate token (CPANEL_API_TOKEN / RATEB_ERP_MIGRATE_TOKEN)"
  exit 0
fi

echo "RATEB ERP migrations: uploading auth token + calling run-migrations endpoint…"

# Write token file on server so PHP can validate (storage/ is outside public web root).
python3 - <<'PY'
import os, sys, urllib.parse, urllib.request, json

host = os.environ.get("CPANEL_HOST", "")
user = os.environ.get("CPANEL_USER", "")
token = os.environ.get("CPANEL_API_TOKEN", "")
remote_base = os.environ.get("CPANEL_REMOTE_BASE", "/home/outratib/public_html").rstrip("/")
if not (host and user and token):
    sys.exit(0)

path = f"{remote_base}/rateb-erp/storage/.deploy-migrate-token"
data = urllib.parse.urlencode({
    "dir": os.path.dirname(path),
    "file": os.path.basename(path),
    "content": token,
}).encode("utf-8")
req = urllib.request.Request(
    f"https://{host}:{os.environ.get('CPANEL_PORT', '2083')}/execute/Fileman/save_file_content",
    data=data,
    method="POST",
    headers={
        "Authorization": f"cpanel {user}:{token}",
        "Content-Type": "application/x-www-form-urlencoded",
    },
)
try:
    with urllib.request.urlopen(req, timeout=60) as resp:
        payload = json.loads(resp.read().decode("utf-8", errors="replace"))
    st = int((payload.get("result") or payload).get("status", payload.get("status", 0)) or 0)
    if st != 1:
        print(f"::warning::Could not upload migrate token file: {payload}", flush=True)
    else:
        print("migrate token file uploaded", flush=True)
except Exception as e:
    print(f"::warning::Migrate token upload failed: {e}", flush=True)
PY

URL="${SITE}/rateb-erp/public/run-migrations.php"
FALLBACK="${SITE}/control-panel/api/control/rateb-erp-migrate-run.php"

run_migrate() {
  local url="$1"
  curl -sS -X POST "$url" \
    -H "X-Rateb-Migrate-Token: ${TOKEN}" \
    -H "Cache-Control: no-cache" \
    --connect-timeout 20 --max-time 180 2>&1
}

OUT="$(run_migrate "$URL")"
if echo "$OUT" | grep -qi '^Forbidden' || echo "$OUT" | grep -qi '^ERROR:' || [ -z "$OUT" ]; then
  echo "Primary endpoint failed — trying control-panel migrate API…"
  OUT="$(run_migrate "$FALLBACK")"
fi

echo "$OUT"

if echo "$OUT" | grep -qi '^ERROR:'; then
  echo "::error::RATEB ERP migration failed"
  exit 1
fi

if echo "$OUT" | grep -q 'OK'; then
  echo "RATEB ERP migrations completed"
  exit 0
fi

echo "::warning::Migration response did not include OK — check log above"
exit 0
