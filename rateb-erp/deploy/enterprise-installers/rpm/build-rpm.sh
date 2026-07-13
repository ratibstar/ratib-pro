#!/usr/bin/env bash
# Build ratib-branch-installer.rpm (RHEL / Alma / Rocky / Oracle / Fedora)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
EI="${ROOT}/deploy/enterprise-installers"
OUT="${ROOT}/storage/branch/enterprise-installers"
STAGE="${OUT}/rpm-payload/ratib-branch-payload"
VER="1.0.0"

rm -rf "${OUT}/rpm-payload"
mkdir -p "${STAGE}"
bash "${EI}/common/stage-payload.sh" "${STAGE}"
mkdir -p "${STAGE}/deploy/enterprise-installers"
cp -a "${EI}/common" "${STAGE}/deploy/enterprise-installers/"
cp -a "${EI}/systemd" "${STAGE}/deploy/enterprise-installers/"
mkdir -p "${STAGE}/docs/install"
[[ -d "${ROOT}/docs/install" ]] && cp -a "${ROOT}/docs/install/." "${STAGE}/docs/install/"

mkdir -p "${OUT}"
TAR="${OUT}/ratib-branch-payload.tar.gz"
tar -C "${OUT}/rpm-payload" -czf "${TAR}" ratib-branch-payload

if ! command -v rpmbuild >/dev/null 2>&1; then
  echo "WARN: rpmbuild not found. Spec + payload ready:"
  echo "  Spec: ${EI}/rpm/ratib-branch-installer.spec"
  echo "  Tar:  ${TAR}"
  echo "On RHEL/Alma/Rocky: rpmbuild -tb ${TAR}  (or copy to ~/rpmbuild/SOURCES and -ba)"
  # Still copy a placeholder path for CI
  cp -f "${EI}/rpm/ratib-branch-installer.spec" "${OUT}/ratib-branch-installer.spec"
  exit 0
fi

RPMBUILD="${OUT}/rpmbuild"
mkdir -p "${RPMBUILD}"/{BUILD,RPMS,SOURCES,SPECS,SRPMS}
cp "${TAR}" "${RPMBUILD}/SOURCES/"
cp "${EI}/rpm/ratib-branch-installer.spec" "${RPMBUILD}/SPECS/"
sed -i "s/^Version:.*/Version:        ${VER}/" "${RPMBUILD}/SPECS/ratib-branch-installer.spec" 2>/dev/null || true

rpmbuild --define "_topdir ${RPMBUILD}" -ba "${RPMBUILD}/SPECS/ratib-branch-installer.spec"
find "${RPMBUILD}/RPMS" -name '*.rpm' -exec cp -f {} "${OUT}/ratib-branch-installer.rpm" \;
echo "OK ${OUT}/ratib-branch-installer.rpm"
ls -lh "${OUT}/ratib-branch-installer.rpm"
