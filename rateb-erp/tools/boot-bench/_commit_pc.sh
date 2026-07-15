#!/bin/bash
cd "/c/Users/انا/Documents/ratibprogram" || exit 1
git add rateb-erp/public/pos-sw.js \
  rateb-erp/tools/boot-bench/phase-pc-profile-sw.js \
  rateb-erp/tools/boot-bench/phase-pc-accept.js \
  rateb-erp/tools/boot-bench/reports/phase-pc-verdict.json \
  rateb-erp/tools/boot-bench/reports/phase-pc-accept-latest.json \
  rateb-erp/tools/boot-bench/reports/phase-pc-profile-latest.json
git commit -m "perf(offline): unblock SW install/activate (Phase PC)"
git push
git log -1 --oneline
git status --porcelain
