#!/bin/bash
KEY=/c/Users/Public/ratib_da_deploy_runtime
HOST=admin@167.233.71.107
scp -i "$KEY" -o StrictHostKeyChecking=no \
  "/c/Users/انا/Documents/ratibprogram/rateb-erp/public/pos-sw.js" \
  "$HOST:/home/admin/domains/rateb.sa/public_html/rateb-erp/public/pos-sw.js"
ssh -i "$KEY" -o StrictHostKeyChecking=no "$HOST" 'python3 -c "t=open(\"/home/admin/domains/rateb.sa/public_html/rateb-erp/public/pos-sw.js\").read(); print(\"v63\" if \"phase-pc-v63\" in t else \"other\")"'
