#!/bin/bash
# cPanel Version Control deployment — sync git checkout to live web roots.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

MARKER="$(tr -d '\r\n' < public/ratib-build.txt 2>/dev/null || echo unknown)"
STAMP="deploy-$(date -u +%Y%m%dT%H%M%SZ)-${MARKER}"
LOG="${HOME}/.ratib-deploy-log"
mkdir -p "$(dirname "$LOG")" 2>/dev/null || true

log() { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$LOG"; }

log "start bundle=about-enterprise-20260516-v15 marker=${MARKER} pwd=${ROOT} user=$(whoami 2>/dev/null || echo unknown)"

CRITICAL_FILES=(
  "includes/ratib-home-public-nav-sync.php"
  "includes/ratib-php74-compat.php"
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

for t in \
  "/home/outratib/public_html" \
  "/home/outratib/repositories/ratib-pro" \
  "/home/outratib/domains/out.ratib.sa/public_html" \
  "/home/outratib/out.ratib.sa/public_html" \
  "/home/outratib/out.ratib.sa" \
  "${CPANEL_REPO_ROOT:-}" \
  "${HOME}/public_html" \
  "${HOME}/out.ratib.sa/public_html" \
  "${HOME}/domains/out.ratib.sa/public_html"
do
  add_target "$t"
done

if [ "${#TARGETS[@]}" -eq 0 ]; then
  log "WARN no deploy targets found — git checkout updated only"
  exit 0
fi

SYNCED=0
for TARGET in "${TARGETS[@]}"; do
  if [ "$(realpath "$TARGET" 2>/dev/null || echo "$TARGET")" = "$(realpath "$ROOT" 2>/dev/null || echo "$ROOT")" ]; then
    log "skip self ${TARGET}"
    continue
  fi
  log "rsync -> ${TARGET}"
  if command -v rsync >/dev/null 2>&1; then
    if rsync -a --delete --exclude='.git/' "${ROOT}/" "${TARGET}/"; then
      printf '%s\n' "$STAMP" > "${TARGET}/.ratib-deploy-stamp"
      for rel in "${CRITICAL_FILES[@]}"; do
        src="${ROOT}/${rel}"
        [ -f "$src" ] || continue
        mkdir -p "$(dirname "${TARGET}/${rel}")"
        cp -f "$src" "${TARGET}/${rel}" 2>/dev/null || true
      done
      ABOUT=no
      [ -f "${TARGET}/pages/about.php" ] && ABOUT=yes
      PROFILE=no
      [ -f "${TARGET}/pages/company-profile.php" ] && PROFILE=yes
      printf '%s\n' "$STAMP" > "${TARGET}/pages/ratib-deploy-status.txt" 2>/dev/null || true
      log "done target=${TARGET} about=${ABOUT} company_profile=${PROFILE}"
      find "${TARGET}" -type d -exec chmod 755 {} \; 2>/dev/null || true
      find "${TARGET}" -name .htaccess -type f -exec chmod 644 {} \; 2>/dev/null || true
      [ -f "${TARGET}/.htaccess" ] && chmod 644 "${TARGET}/.htaccess" || true
      SYNCED=$((SYNCED + 1))
    else
      log "WARN rsync failed for ${TARGET}"
    fi
  else
    log "WARN rsync not available"
  fi
done

log "finished synced=${SYNCED} targets marker=${MARKER}"
exit 0
