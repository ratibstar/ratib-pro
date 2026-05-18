#!/bin/bash
# Fast path: only files required for profile + deploy verify (~30-60s total).
set -uo pipefail

CPANEL_HOST="${CPANEL_HOST:?}"
CPANEL_USER="${CPANEL_USER:?}"
CPANEL_API_TOKEN="${CPANEL_API_TOKEN:?}"
CPANEL_PORT="${CPANEL_PORT:-2083}"
REMOTE_BASE="${CPANEL_REMOTE_BASE:-/home/outratib/public_html}"

upload_one() {
  local rel="$1"
  local dir="${REMOTE_BASE}/$(dirname "$rel")"
  [ "$(dirname "$rel")" = "." ] && dir="${REMOTE_BASE}"
  local file="$(basename "$rel")"
  python3 -c "
import json, os, sys, urllib.parse, urllib.request
path, dir_path, name = sys.argv[1], sys.argv[2], sys.argv[3]
content = open(path, 'rb').read().decode('utf-8', 'replace')
host, port = os.environ['CPANEL_HOST'], os.environ.get('CPANEL_PORT', '2083')
url = f\"https://{host}:{port}/execute/Fileman/save_file_content\"
data = urllib.parse.urlencode({'dir': dir_path, 'file': name, 'content': content}).encode()
req = urllib.request.Request(url, data=data, method='POST', headers={
    'Authorization': f\"cpanel {os.environ['CPANEL_USER']}:{os.environ['CPANEL_API_TOKEN']}\",
    'Content-Type': 'application/x-www-form-urlencoded',
})
with urllib.request.urlopen(req, timeout=45) as r:
    body = r.read().decode('utf-8', 'replace')
d = json.loads(body)
r = d.get('result', d) or {}
sys.exit(0 if int(r.get('status', d.get('status', 0)) or 0) == 1 else 1)
" "$rel" "$dir" "$file"
}

# build marker LAST so verify sees this commit immediately after upload
FILES=(
  ".htaccess"
  "profile/index.php"
  "pages/deploy-root.php"
  "public/ratib-build.txt"
)

ok=0
for rel in "${FILES[@]}"; do
  [ -f "$rel" ] || { echo "SKIP $rel"; continue; }
  echo -n "upload $rel ... "
  if upload_one "$rel"; then
    echo OK
    ok=$((ok + 1))
  else
    echo FAIL
  fi
done

echo "fast fileman ok=${ok}/${#FILES[@]}"
[ "$ok" -ge 3 ]
