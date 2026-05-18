#!/bin/bash
# Upload critical deploy files via cPanel UAPI Fileman::save_file_content
set -uo pipefail

CPANEL_HOST="${CPANEL_HOST:?CPANEL_HOST required}"
CPANEL_USER="${CPANEL_USER:?CPANEL_USER required}"
CPANEL_API_TOKEN="${CPANEL_API_TOKEN:?CPANEL_API_TOKEN required}"
CPANEL_PORT="${CPANEL_PORT:-2083}"
REMOTE_BASE="${CPANEL_REMOTE_BASE:-/home/outratib/public_html}"

FILES=(
  ".htaccess"
  "public/ratib-build.txt"
  "pages/ratib-profile-landing.php"
  "profile/index.php"
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

fileman_api() {
  python3 -c "
import json, os, sys, urllib.parse, urllib.request
func, payload = sys.argv[1], json.loads(sys.argv[2])
host = os.environ['CPANEL_HOST']
port = os.environ.get('CPANEL_PORT', '2083')
user = os.environ['CPANEL_USER']
token = os.environ['CPANEL_API_TOKEN']
url = f'https://{host}:{port}/execute/Fileman/{func}'
data = urllib.parse.urlencode(payload).encode('utf-8')
req = urllib.request.Request(url, data=data, method='POST', headers={
    'Authorization': f'cpanel {user}:{token}',
    'Content-Type': 'application/x-www-form-urlencoded',
})
try:
    with urllib.request.urlopen(req, timeout=120) as r:
        print(r.read().decode('utf-8', errors='replace'))
except Exception as e:
    print(json.dumps({'status': 0, 'errors': [str(e)]}))
" "$1" "$2" 2>&1
}

api_ok() {
  echo "$1" | python3 -c "
import json,sys
try:
    d=json.load(sys.stdin)
except Exception:
    sys.exit(1)
r=d.get('result',d) or {}
sys.exit(0 if int(r.get('status', d.get('status', 0)) or 0)==1 else 1)
" 2>/dev/null
}

ensure_dir() {
  local dir_path="$1"
  echo "MKDIR ${dir_path}"
  RESP="$(fileman_api mkdir "$(python3 -c "import json; print(json.dumps({'path': '$dir_path', 'permissions': '0755'}))")")"
  echo "$RESP" | head -c 200
  echo ""
}

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
  if [ "$(dirname "$rel")" != "." ]; then
    ensure_dir "${dir}" || true
  fi
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
url = f'https://{host}:{port}/execute/Fileman/save_file_content'
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
  if api_ok "$RESP"; then
    ok=$((ok + 1))
    echo "OK ${rel}"
  else
    fail=$((fail + 1))
    echo "FAIL ${rel}"
  fi
done

echo "Summary fileman ok=${ok} fail=${fail}"
[ "$ok" -ge 2 ]
