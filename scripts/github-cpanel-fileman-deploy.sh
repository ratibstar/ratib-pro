#!/bin/bash
# Proven cPanel Fileman upload (same API as run #851) + progress % for every file.
set -uo pipefail
cd "$(dirname "$0")/.."

CPANEL_HOST="${CPANEL_HOST:?CPANEL_HOST required}"
CPANEL_USER="${CPANEL_USER:?CPANEL_USER required}"
CPANEL_API_TOKEN="${CPANEL_API_TOKEN:?CPANEL_API_TOKEN required}"
CPANEL_PORT="${CPANEL_PORT:-2083}"
REMOTE_BASE="${CPANEL_REMOTE_BASE:-/home/outratib/public_html}"
MODE="${CPANEL_DEPLOY_MODE:-all}"

upload_one() {
  local rel="$1"
  local dir="${REMOTE_BASE}/$(dirname "$rel")"
  if [ "$(dirname "$rel")" = "." ]; then
    dir="${REMOTE_BASE}"
  fi
  local file
  file="$(basename "$rel")"
  export CPANEL_HOST CPANEL_USER CPANEL_API_TOKEN CPANEL_PORT
  python3 -c "
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
" "$rel" "$dir" "$file" 2>&1
}

is_ok() {
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

CRITICAL=(
  ".htaccess"
  "public/ratib-build.txt"
  "pages/about.php"
  "pages/deploy-root.php"
  "pages/company-profile.php"
  "includes/ratib-public-base-url.php"
  "includes/ratib-home-public-nav-bootstrap.php"
  "includes/ratib-home-public-chrome-top.php"
  "includes/ratib-home-public-nav-sync.php"
  "includes/ratib-home-public-footer.php"
  "includes/ratib-profile-nav-guard.php"
  "includes/ratib_html_global_ai_patch.php"
  "includes/ratib-about-profile-data.php"
  "includes/ratib-about-sections.php"
  "js/pages/ratib-profile-nav-guard.js"
  "js/pages/ratib-mega-nav.js"
  "js/pages/ratib-home-nav-chrome.js"
  "js/pages/about-enterprise.js"
  "js/pages/home-page.js"
  "css/pages/about-enterprise.css"
  "css/pages/home-public.css"
  "css/pages/ratib-mega-nav.css"
  "public/index.php"
  "pages/home.php"
)

mapfile -t ALL_FILES < <(
  find . -type f \
    ! -path './.git/*' ! -path './.github/*' ! -path './.cursor/*' \
    ! -path './node_modules/*' ! -path './archive/*' \
    ! -name '*.md' ! -name '*.map' ! -name '*.log' ! -name '*.zip' ! -name '*.png' ! -name '*.jpg' \
    ! -name '*.jpeg' ! -name '*.gif' ! -name '*.webp' ! -name '*.ico' ! -name '*.pdf' ! -name '*.woff' \
    ! -name '*.woff2' ! -name '*.ttf' ! -name '*.mp4' \
    -size -3M \
    | sed 's|^\./||' | sort -u
)

if [ "$MODE" = "critical" ]; then
  FILES=("${CRITICAL[@]}")
else
  FILES=("${ALL_FILES[@]}")
fi

TOTAL=${#FILES[@]}
ok=0
fail=0
echo "deploy mode=${MODE} files=${TOTAL} dest=${REMOTE_BASE}"

n=0
for rel in "${FILES[@]}"; do
  n=$((n + 1))
  if [ ! -f "$rel" ]; then
    echo "[$n/$TOTAL] $(( n * 100 / TOTAL ))% SKIP missing $rel"
    continue
  fi
  pct=$((n * 100 / TOTAL))
  printf '[%s/%s] %s%% upload %s ... ' "$n" "$TOTAL" "$pct" "$rel"
  RESP="$(upload_one "$rel")" || RESP='{"status":0}'
  if is_ok "$RESP"; then
    echo OK
    ok=$((ok + 1))
  else
    echo FAIL
    fail=$((fail + 1))
    echo "$RESP" | head -c 200
    echo ""
  fi
done

echo ""
echo "========== Summary: ok=${ok} fail=${fail} total=${TOTAL} ($(( ok * 100 / TOTAL ))% success) =========="
# Must upload core profile stack
need=0
for must in ".htaccess" "public/ratib-build.txt" "pages/about.php" "js/pages/ratib-profile-nav-guard.js"; do
  if [ -f "$must" ]; then
    need=$((need + 1))
  fi
done
if [ "$ok" -lt "$need" ]; then
  exit 1
fi
exit 0
