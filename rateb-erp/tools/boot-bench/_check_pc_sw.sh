#!/bin/bash
KEY=/c/Users/Public/ratib_da_deploy_runtime
HOST=admin@167.233.71.107
ssh -i "$KEY" -o StrictHostKeyChecking=no "$HOST" 'python3 -c "t=open(\"/home/admin/domains/rateb.sa/public_html/rateb-erp/public/pos-sw.js\").read(); print(\"pc\" if \"phase-pc-v62\" in t else \"old\"); print(\"bg\" if \"scheduleBackgroundWarm\" in t else \"nobg\")"'
