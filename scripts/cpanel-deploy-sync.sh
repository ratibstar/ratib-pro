#!/bin/bash
# cPanel Version Control deployment task — sync git checkout to live web roots.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

MARKER="$(tr -d '\r\n' < public/ratib-build.txt 2>/dev/null || echo unknown)"
STAMP="deploy-$(date -u +%Y%m%dT%H%M%SZ)-${MARKER}"
LOG="${HOME}/.ratib-deploy-log"
mkdir -p "$(dirname "$LOG")" 2>/dev/null || true

log() { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$LOG"; }

log "start bundle=about-enterprise-20260516-v10 marker=${MARKER} pwd=${ROOT} user=$(whoami 2>/dev/null || echo unknown)"

# cPanel Version Control may expose the clone path during deploy.
if [ -n "${CPANEL_REPO_ROOT:-}" ]; then
  log "env CPANEL_REPO_ROOT=${CPANEL_REPO_ROOT}"
fi

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

# 1) cPanel userdata document root (most accurate for out.ratib.sa)
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

# 2) Optional repo-maintained list (edit config/cpanel-deploy-targets.txt on server)
LIST="${ROOT}/config/cpanel-deploy-targets.txt"
if [ -f "$LIST" ]; then
  while IFS= read -r line || [ -n "$line" ]; do
    line="${line%%#*}"
    line="$(echo "$line" | xargs)"
    add_target "$line"
  done < "$LIST"
fi

# 3) Documented production paths (see DEPLOY_AUTOMATION_SETUP.md, run-readiness.php)
for t in \
  "/home/outratib/public_html" \
  "/home/outratib/repositories/ratib-pro" \
  "/home/outratib/domains/out.ratib.sa/public_html" \
  "/home/outratib/out.ratib.sa/public_html" \
  "/home/outratib/out.ratib.sa" \
  "${CPANEL_REPO_ROOT:-}" \
  "${HOME}/public_html" \
  "${HOME}/out.ratib.sa" \
  "${HOME}/out.ratib.sa/public_html" \
  "${HOME}/domains/out.ratib.sa/public_html" \
  "${HOME}/public_html/out.ratib.sa" \
  "${HOME}/subdomains/out.ratib.sa/public_html"
do
  add_target "$t"
done

# 4) If the site is served directly from this git checkout, we are already in the live root.
add_target "$ROOT"

if [ "${#TARGETS[@]}" -eq 0 ]; then
  log "ERROR no deploy targets found — add path to config/cpanel-deploy-targets.txt"
  exit 1
fi

SYNCED=0
for TARGET in "${TARGETS[@]}"; do
  [ "$TARGET" = "$ROOT" ] && log "skip self-sync ${TARGET}" && continue
  log "rsync -> ${TARGET}"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --delete --exclude='.git/' "${ROOT}/" "${TARGET}/"
  else
    (cd "${ROOT}" && tar --exclude='.git' -cf - .) | (cd "${TARGET}" && tar -xf -)
  fi
  printf '%s\n' "$STAMP" > "${TARGET}/.ratib-deploy-stamp"
  ABOUT=no
  [ -f "${TARGET}/pages/about.php" ] && ABOUT=yes
  log "done target=${TARGET} about=${ABOUT}"
  find "${TARGET}" -type d -exec chmod 755 {} \; 2>/dev/null || true
  find "${TARGET}" -name .htaccess -type f -exec chmod 644 {} \; 2>/dev/null || true
  [ -f "${TARGET}/.htaccess" ] && chmod 644 "${TARGET}/.htaccess" || true
  SYNCED=$((SYNCED + 1))
done

log "finished synced=${SYNCED} targets marker=${MARKER}"
