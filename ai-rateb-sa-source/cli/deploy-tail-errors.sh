#!/bin/bash
set -eu
for f in /home/admin/domains/ai.rateb.sa/error_log /home/admin/logs/ai.rateb.sa.error.log /var/log/httpd/domains/ai.rateb.sa.error.log; do
  if [ -f "$f" ]; then
    echo "LOG=$f"
    tail -n 15 "$f"
    exit 0
  fi
done
echo NO_ERROR_LOG_FOUND
