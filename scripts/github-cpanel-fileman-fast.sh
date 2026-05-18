#!/bin/bash
# Full project sync to public_html (all files, parallel). Wrapper for Python sync-all.
set -euo pipefail
cd "$(dirname "$0")/.."
exec python3 scripts/github-cpanel-fileman-sync-all.py
