#!/bin/bash
# Build dist/rateb-profile-hotfix.zip for manual cPanel upload (extract into site docroot).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
OUT="${ROOT}/dist/rateb-profile-hotfix.zip"
mkdir -p dist
MANIFEST=(
  includes/rateb-home-public-nav-sync.php
  includes/rateb-home-public-chrome-top.php
  includes/rateb-home-public-nav-bootstrap.php
  includes/rateb-about-profile-data.php
  includes/rateb-about-sections.php
  includes/rateb-mega-nav-config.php
  includes/rateb-mega-nav-resolve.php
  includes/rateb-mega-nav-render.php
  pages/home.php
  pages/about.php
  pages/company-profile.php
  pages/deploy-root.php
  public/rateb-build.txt
  js/pages/rateb-mega-nav.js
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
