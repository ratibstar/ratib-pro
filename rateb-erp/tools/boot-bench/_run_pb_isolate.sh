#!/bin/bash
KEY=/c/Users/Public/ratib_da_deploy_runtime
HOST=admin@167.233.71.107
scp -i "$KEY" -o StrictHostKeyChecking=no \
  "/c/Users/انا/Documents/ratibprogram/rateb-erp/tools/boot-bench/phase-pb-isolate.sh" \
  "$HOST:/tmp/phase-pb-isolate.sh"
ssh -i "$KEY" -o StrictHostKeyChecking=no "$HOST" \
  'python3 -c "p=open(\"/tmp/phase-pb-isolate.sh\",\"rb\").read().replace(b\"\r\n\",b\"\n\").replace(b\"\r\",b\"\n\"); open(\"/tmp/pbi.sh\",\"wb\").write(p)" && bash /tmp/pbi.sh'
