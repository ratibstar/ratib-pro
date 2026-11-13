#!/bin/bash
set -eu
DOC="/home/admin/domains/ai.rateb.sa"
SRC="${DOC}/tmp-upload-batch"
mkdir -p "${DOC}/tests"
if [ -f "${SRC}/platform-public-gate.php" ]; then
  mv -f "${SRC}/platform-public-gate.php" "${DOC}/tests/platform-public-gate.php"
fi
if [ -f "${SRC}/hr-tenant-smoke.php" ]; then
  mv -f "${SRC}/hr-tenant-smoke.php" "${DOC}/tests/hr-tenant-smoke.php"
fi
if [ -d "${SRC}" ]; then
  rmdir "${SRC}" 2>/dev/null || ls -la "${SRC}"
fi
test -f "${DOC}/app/public-gate.php"
test -f "${DOC}/admin/hr-overview.php"
test -f "${DOC}/hr/employees.php"
echo FINISH_OK
