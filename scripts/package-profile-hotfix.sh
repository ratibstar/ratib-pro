#!/bin/bash
# Build dist/ratib-profile-hotfix.zip for manual cPanel upload (extract into site docroot).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
OUT="${ROOT}/dist/ratib-profile-hotfix.zip"
mkdir -p dist
MANIFEST=(
  includes/ratib-home-public-nav-sync.php
  includes/ratib-home-public-chrome-top.php
  includes/ratib-home-public-nav-bootstrap.php
  includes/ratib-about-profile-data.php
  includes/ratib-about-sections.php
  includes/ratib-mega-nav-config.php
  includes/ratib-mega-nav-resolve.php
  includes/ratib-mega-nav-render.php
  pages/home.php
  pages/about.php
  pages/company-profile.php
  pages/deploy-root.php
  public/ratib-build.txt
  js/pages/ratib-mega-nav.js
  js/pages/home-page.js
  css/pages/home-public.css
  css/pages/about-enterprise.css
  js/pages/about-enterprise.js
)
rm -f "$OUT"
for f in "${MANIFEST[@]}"; do
  [ -f "$ROOT/$f" ] || { echo "missing: $f" >&2; exit 1; }
done
if command -v zip >/dev/null 2>&1; then
  (cd "$ROOT" && zip -r "$OUT" "${MANIFEST[@]}")
else
  echo "zip not found; install zip or upload files from MANIFEST manually" >&2
  exit 1
fi
echo "Created $OUT"
