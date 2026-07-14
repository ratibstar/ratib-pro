#!/bin/bash
set -e
KEY=/c/Users/Public/ratib_da_deploy_runtime
HOST=admin@167.233.71.107
ssh -i "$KEY" -o StrictHostKeyChecking=no "$HOST" 'python3 -c "p=open(\"/tmp/phase-pb-cold-fpm.sh\",\"rb\").read().replace(b\"\r\n\",b\"\n\").replace(b\"\r\",b\"\n\"); open(\"/tmp/pb.sh\",\"wb\").write(p)" && bash /tmp/pb.sh'
