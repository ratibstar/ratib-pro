#!/usr/bin/env bash
# Build ratib-branch-installer.run (self-extracting bash + tar.gz).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
EI="${ROOT}/deploy/enterprise-installers"
OUT="${ROOT}/storage/branch/enterprise-installers"
STAGE="${OUT}/payload/linux-run"
mkdir -p "${OUT}"

bash "${EI}/common/stage-payload.sh" "${STAGE}"
# Ensure common + systemd inside payload
mkdir -p "${STAGE}/deploy/enterprise-installers"
cp -a "${EI}/common" "${STAGE}/deploy/enterprise-installers/"
cp -a "${EI}/systemd" "${STAGE}/deploy/enterprise-installers/"
cp -a "${EI}/linux-run" "${STAGE}/deploy/enterprise-installers/" 2>/dev/null || true

# Docs
mkdir -p "${STAGE}/docs/install"
if [[ -d "${ROOT}/docs/install" ]]; then
  cp -a "${ROOT}/docs/install/." "${STAGE}/docs/install/"
fi

TAR="${OUT}/ratib-branch-payload.tar.gz"
tar -C "${STAGE}" -czf "${TAR}" .

RUN="${OUT}/ratib-branch-installer.run"
{
  cat "${EI}/linux-run/ratib-branch-installer.sh"
  echo "__RATIB_BRANCH_PAYLOAD_BELOW__"
  cat "${TAR}"
} > "${RUN}"
chmod +x "${RUN}"
rm -f "${TAR}"
echo "OK ${RUN}"
ls -lh "${RUN}"
