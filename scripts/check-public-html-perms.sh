#!/bin/bash
# Run on server (cPanel Terminal): bash scripts/check-public-html-perms.sh
# Or: bash ~/public_html/scripts/check-public-html-perms.sh

HOME_DIR="${HOME:-/home/admin}"
WEB="${RATEB_PUBLIC_HTML:-${HOME_DIR}/public_html}"

echo "rateb-perms-check (shell)"
echo "home=${HOME_DIR}"
echo "public_html=${WEB}"
echo ""

check_dir() {
  local p="$1" expected="$2"
  [ -d "$p" ] || { echo "[MISSING DIR] $p"; return 1; }
  local mode
  mode="$(stat -c '%a' "$p" 2>/dev/null || stat -f '%OLp' "$p")"
  if [ "$mode" = "$expected" ]; then
    echo "[OK DIR] $mode $p"
  else
    echo "[BAD DIR] $mode (want $expected) $p"
    return 1
  fi
}

check_file() {
  local p="$1"
  [ -f "$p" ] || { echo "[MISSING FILE] $p"; return 1; }
  local mode
  mode="$(stat -c '%a' "$p" 2>/dev/null || stat -f '%OLp' "$p")"
  if [ "$mode" = "644" ]; then
    echo "[OK FILE] $mode $p"
  else
    echo "[BAD FILE] $mode (want 644) $p"
    return 1
  fi
}

issues=0
check_dir "$HOME_DIR" "711" || check_dir "$HOME_DIR" "750" || check_dir "$HOME_DIR" "755" || issues=$((issues + 1))
check_dir "$WEB" "755" || issues=$((issues + 1))
check_file "$WEB/.htaccess" || issues=$((issues + 1))

echo ""
echo "Scanning bad directories (not 755)..."
find "$WEB" -type d ! -path '*/.git/*' ! -path '*/Designed/*' ! -path '*/vendor/*' 2>/dev/null | while read -r d; do
  m="$(stat -c '%a' "$d" 2>/dev/null || echo 0)"
  if [ "$m" = "644" ] || [ "$m" = "640" ]; then
    echo "[BAD DIR] $m $d"
  fi
done

echo ""
echo "Scanning sample bad files (php/css not 644)..."
find "$WEB" -type f \( -name '*.php' -o -name '*.css' -o -name '.htaccess' \) ! -path '*/.git/*' 2>/dev/null | head -5000 | while read -r f; do
  m="$(stat -c '%a' "$f" 2>/dev/null || echo 0)"
  if [ "$m" != "644" ]; then
    echo "[BAD FILE] $m $f"
  fi
done

echo ""
echo "issues_hint=$issues (home/public_html/htaccess only; see lists above for more)"
echo "Fix: chmod 711 ~ ; chmod 755 $WEB ; find $WEB -type d -exec chmod 755 {} + ; find $WEB -type f -exec chmod 644 {} +"
