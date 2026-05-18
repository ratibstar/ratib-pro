#!/bin/bash
# Fast path: profile page + deploy verify (~2 min).
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

FILES=(
  ".htaccess"
  "profile/index.php"
  "pages/about.php"
  "pages/deploy-root.php"
  "includes/ratib-home-public-chrome-top.php"
  "includes/ratib-home-public-nav-sync.php"
  "includes/ratib-home-public-nav-bootstrap.php"
  "includes/ratib-public-base-url.php"
  "includes/ratib-home-public-footer.php"
  "includes/ratib-about-profile-data.php"
  "includes/ratib-about-sections.php"
  "js/pages/ratib-profile-nav-guard.js"
  "js/pages/ratib-mega-nav.js"
  "js/pages/ratib-home-nav-chrome.js"
  "js/pages/about-enterprise.js"
  "css/pages/about-enterprise.css"
  "css/pages/home-public.css"
  "css/pages/ratib-mega-nav.css"
  "public/ratib-build.txt"
)

ok=0
fail=0
for rel in "${FILES[@]}"; do
  [ -f "$rel" ] || { echo "SKIP $rel"; continue; }
  echo -n "upload $rel ... "
  if upload_one "$rel"; then
    echo OK
    ok=$((ok + 1))
  else
    echo FAIL
    fail=$((fail + 1))
  fi
done

echo "fast fileman ok=${ok} fail=${fail} total=${#FILES[@]}"
[ "$ok" -ge 12 ]
