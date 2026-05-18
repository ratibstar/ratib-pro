#!/bin/bash
# cPanel Version Control: after git pull, sync checkout → live document root(s).
# Required live root for out.ratib.sa: /home/outratib/public_html
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

MARKER="$(tr -d '\r\n' < public/ratib-build.txt 2>/dev/null || echo unknown)"
STAMP="deploy-$(date -u +%Y%m%dT%H%M%SZ)-${MARKER}"
LOG="${HOME}/.ratib-deploy-log"
PUBLIC_HTML="${RATIB_PUBLIC_HTML:-/home/outratib/public_html}"

mkdir -p "$(dirname "$LOG")" 2>/dev/null || true

log() { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$LOG"; }

log "start marker=${MARKER} ROOT=${ROOT} PUBLIC_HTML=${PUBLIC_HTML} user=$(whoami 2>/dev/null || echo unknown)"

CRITICAL_FILES=(
  "includes/ratib-php74-compat.php"
  "includes/ratib-home-public-nav-sync.php"
  "includes/ratib-home-public-chrome-top.php"
  "pages/home.php"
  "pages/about.php"
  "pages/company-profile.php"
  "pages/deploy-root.php"
  "public/ratib-build.txt"
  "js/pages/ratib-mega-nav.js"
  "js/pages/home-page.js"
  "css/pages/home-public.css"
  "css/pages/about-enterprise.css"
  "js/pages/about-enterprise.js"
)

TARGETS=()

add_target() {
  local t="${1%/}"
  [ -n "$t" ] || return 0
  [ -d "$t" ] || return 0
  local i
  for i in "${TARGETS[@]:-}"; do
    [ "$i" = "$t" ] && return 0
  done
  TARGETS+=("$t")
}

for UD in \
  "/var/cpanel/userdata/${USER}/out.ratib.sa" \
  "/var/cpanel/userdata/${USER}/out.ratib.sa_SSL" \
  "/var/cpanel/userdata/outratib/out.ratib.sa" \
  "/var/cpanel/userdata/outratib/out.ratib.sa_SSL"
do
  if [ -f "$UD" ]; then
    DR="$(grep -E '^documentroot:' "$UD" 2>/dev/null | head -1 | sed 's/^documentroot:[[:space:]]*//' | tr -d '\r')"
    add_target "$DR"
    log "userdata ${UD} -> ${DR:-<empty>}"
  fi
done

LIST="${ROOT}/config/cpanel-deploy-targets.txt"
if [ -f "$LIST" ]; then
  while IFS= read -r line || [ -n "$line" ]; do
    line="${line%%#*}"
    line="$(echo "$line" | xargs)"
    add_target "$line"
  done < "$LIST"
fi

add_target "$PUBLIC_HTML"
add_target "/home/outratib/public_html"
add_target "${CPANEL_REPO_ROOT:-}"
add_target "${HOME}/public_html"
add_target "${HOME}/domains/out.ratib.sa/public_html"

ROOT_REAL="$(realpath "$ROOT" 2>/dev/null || echo "$ROOT")"
PUBLIC_REAL="$(realpath "$PUBLIC_HTML" 2>/dev/null || echo "$PUBLIC_HTML")"

sync_one_target() {
  local TARGET="$1"
  local TARGET_REAL
  TARGET_REAL="$(realpath "$TARGET" 2>/dev/null || echo "$TARGET")"

  if [ "$TARGET_REAL" = "$ROOT_REAL" ]; then
    log "skip self (git root is docroot) ${TARGET}"
    printf '%s\n' "$STAMP" > "${TARGET}/.ratib-deploy-stamp" 2>/dev/null || true
    printf '%s\n' "$STAMP" > "${TARGET}/pages/ratib-deploy-status.txt" 2>/dev/null || true
    return 0
  fi

  log "sync -> ${TARGET}"
  local ok=0
  if command -v rsync >/dev/null 2>&1; then
    if rsync -a --delete --exclude='.git/' "${ROOT}/" "${TARGET}/"; then
      ok=1
    else
      log "WARN rsync failed for ${TARGET}"
    fi
  fi
  if [ "$ok" -eq 0 ]; then
    log "fallback cp -a -> ${TARGET}"
    if cp -a "${ROOT}/." "${TARGET}/" 2>/dev/null; then
      ok=1
    else
      log "WARN cp -a failed for ${TARGET}"
      return 1
    fi
  fi

  for rel in "${CRITICAL_FILES[@]}"; do
    src="${ROOT}/${rel}"
    [ -f "$src" ] || continue
    mkdir -p "$(dirname "${TARGET}/${rel}")"
    cp -f "$src" "${TARGET}/${rel}" 2>/dev/null || true
  done

  printf '%s\n' "$STAMP" > "${TARGET}/.ratib-deploy-stamp"
  printf '%s\n' "$STAMP" > "${TARGET}/pages/ratib-deploy-status.txt" 2>/dev/null || true

  local profile=no
  [ -f "${TARGET}/pages/company-profile.php" ] && profile=yes
  log "done ${TARGET} company_profile=${profile} marker=${MARKER}"
  return 0
}

SYNCED=0
PUBLIC_OK=0

if [ "${#TARGETS[@]}" -eq 0 ]; then
  log "ERROR no deploy targets — add ${PUBLIC_HTML} in cPanel or config/cpanel-deploy-targets.txt"
  exit 1
fi

for TARGET in "${TARGETS[@]}"; do
  if sync_one_target "$TARGET"; then
    SYNCED=$((SYNCED + 1))
    TARGET_REAL="$(realpath "$TARGET" 2>/dev/null || echo "$TARGET")"
    if [ "$TARGET_REAL" = "$PUBLIC_REAL" ] || [ "$TARGET" = "$PUBLIC_HTML" ]; then
      PUBLIC_OK=1
    fi
  fi
done

log "finished synced=${SYNCED} public_html_ok=${PUBLIC_OK} marker=${MARKER}"

if [ ! -d "$PUBLIC_HTML" ]; then
  log "ERROR public_html missing: ${PUBLIC_HTML}"
  exit 1
fi

if [ "$ROOT_REAL" != "$PUBLIC_REAL" ] && [ "$PUBLIC_OK" -eq 0 ]; then
  log "ERROR git checkout (${ROOT}) did not sync to live docroot (${PUBLIC_HTML})"
  exit 1
fi

exit 0
