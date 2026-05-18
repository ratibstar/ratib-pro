#!/bin/bash
# Upload critical deploy files via cPanel UAPI Fileman::save_file_content
set -uo pipefail

CPANEL_HOST="${CPANEL_HOST:?CPANEL_HOST required}"
CPANEL_USER="${CPANEL_USER:?CPANEL_USER required}"
CPANEL_API_TOKEN="${CPANEL_API_TOKEN:?CPANEL_API_TOKEN required}"
CPANEL_PORT="${CPANEL_PORT:-2083}"
REMOTE_BASE="${CPANEL_REMOTE_BASE:-/home/outratib/public_html}"

CPANEL_BASE="https://${CPANEL_HOST}:${CPANEL_PORT}/execute/Fileman/save_file_content"
AUTH="Authorization: cpanel ${CPANEL_USER}:${CPANEL_API_TOKEN}"

FILES=(
  ".htaccess"
  "public/ratib-build.txt"
  "public/index.php"
  "pages/company-profile.php"
  "pages/about.php"
  "pages/deploy-root.php"
  "pages/ratib-deploy-status.txt"
  "includes/ratib_html_global_ai_patch.php"
  "includes/ratib-public-base-url.php"
  "includes/ratib-home-public-chrome-top.php"
  "includes/ratib-home-public-nav-sync.php"
  "includes/ratib-home-public-nav-bootstrap.php"
  "includes/ratib-home-public-footer.php"
  "ratib-profile-fix.php"
)

ok=0
fail=0

for rel in "${FILES[@]}"; do
  if [ ! -f "$rel" ]; then
    echo "SKIP missing ${rel}"
    continue
  fi
  dir="${REMOTE_BASE}/$(dirname "$rel")"
  if [ "$(dirname "$rel")" = "." ]; then
    dir="${REMOTE_BASE}"
  fi
  file="$(basename "$rel")"
  echo "=== Upload ${rel} -> ${dir}/${file} ==="
  RESP="$(python3 -c "
import json, os, sys, urllib.parse, urllib.request
path, dir_path, name = sys.argv[1], sys.argv[2], sys.argv[3]
with open(path, 'rb') as f:
    raw = f.read()
try:
    content = raw.decode('utf-8')
except UnicodeDecodeError:
    content = raw.decode('latin-1')
host = os.environ['CPANEL_HOST']
port = os.environ.get('CPANEL_PORT', '2083')
user = os.environ['CPANEL_USER']
token = os.environ['CPANEL_API_TOKEN']
url = f\"https://{host}:{port}/execute/Fileman/save_file_content\"
data = urllib.parse.urlencode({'dir': dir_path, 'file': name, 'content': content}).encode('utf-8')
req = urllib.request.Request(url, data=data, method='POST', headers={
    'Authorization': f'cpanel {user}:{token}',
    'Content-Type': 'application/x-www-form-urlencoded',
})
try:
    with urllib.request.urlopen(req, timeout=120) as r:
        print(r.read().decode('utf-8', errors='replace'))
except Exception as e:
    print(json.dumps({'status': 0, 'errors': [str(e)]}))
" "$rel" "$dir" "$file" 2>&1)" || RESP='{"status":0,"errors":["python failed"]}'
  echo "$RESP" | head -c 400
  echo ""
  if echo "$RESP" | python3 -c "
import json,sys
try:
    d=json.load(sys.stdin)
except Exception:
    sys.exit(1)
r=d.get('result',d) or {}
st=r.get('status', d.get('status', 0))
sys.exit(0 if int(st or 0)==1 else 1)
" 2>/dev/null; then
    ok=$((ok + 1))
    echo "OK ${rel}"
  else
    fail=$((fail + 1))
    echo "FAIL ${rel}"
  fi
done

echo "Summary fileman ok=${ok} fail=${fail}"
[ "$ok" -ge 1 ]
