#!/usr/bin/env bash
# Build ratib-branch-installer.deb
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
EI="${ROOT}/deploy/enterprise-installers"
OUT="${ROOT}/storage/branch/enterprise-installers"
PKG="${OUT}/deb-root"
VER="$(tr -d '\r\n' < "${ROOT}/VERSION" | sed 's/[^0-9.].*//;s/^$/1.0.0/')"
[[ -z "${VER}" || "${VER}" == *"-"* ]] && VER="1.0.0"

rm -rf "${PKG}"
mkdir -p "${PKG}/opt/ratib-branch" "${PKG}/DEBIAN"
bash "${EI}/common/stage-payload.sh" "${PKG}/opt/ratib-branch"
mkdir -p "${PKG}/opt/ratib-branch/deploy/enterprise-installers"
cp -a "${EI}/common" "${PKG}/opt/ratib-branch/deploy/enterprise-installers/"
cp -a "${EI}/systemd" "${PKG}/opt/ratib-branch/deploy/enterprise-installers/"
mkdir -p "${PKG}/opt/ratib-branch/docs/install"
[[ -d "${ROOT}/docs/install" ]] && cp -a "${ROOT}/docs/install/." "${PKG}/opt/ratib-branch/docs/install/"

cp -a "${EI}/deb/DEBIAN/." "${PKG}/DEBIAN/"
# Refresh version in control
sed -i "s/^Version:.*/Version: ${VER}/" "${PKG}/DEBIAN/control" 2>/dev/null \
  || sed -i.bak "s/^Version:.*/Version: ${VER}/" "${PKG}/DEBIAN/control"
chmod 0755 "${PKG}/DEBIAN/preinst" "${PKG}/DEBIAN/postinst" "${PKG}/DEBIAN/prerm" "${PKG}/DEBIAN/postrm"

mkdir -p "${OUT}"
DEB="${OUT}/ratib-branch-installer.deb"
DATA_TAR="${OUT}/data.tar.gz"
CTRL_TAR="${OUT}/control.tar.gz"
tar -C "${PKG}" --exclude=DEBIAN -czf "${DATA_TAR}" .
tar -C "${PKG}/DEBIAN" -czf "${CTRL_TAR}" .
echo "2.0" > "${OUT}/debian-binary"

if command -v dpkg-deb >/dev/null 2>&1; then
  dpkg-deb --build "${PKG}" "${DEB}"
  echo "OK ${DEB}"
elif command -v ar >/dev/null 2>&1; then
  rm -f "${DEB}"
  (cd "${OUT}" && ar r "${DEB}" debian-binary control.tar.gz data.tar.gz)
  echo "OK ${DEB} (ar)"
elif command -v python3 >/dev/null 2>&1 || command -v python >/dev/null 2>&1; then
  PY="$(command -v python3 || command -v python)"
  DEB="${DEB}" "${PY}" - <<'PY'
import os
out = os.environ["DEB"]
base = os.path.dirname(out) or "."
os.chdir(base)
files = ["debian-binary", "control.tar.gz", "data.tar.gz"]
with open(os.path.basename(out), "wb") as f:
    f.write(b"!<arch>\n")
    for name in files:
        data = open(name, "rb").read()
        header = (
            name.encode("ascii").ljust(16)
            + b"0".ljust(12)
            + b"0".ljust(6)
            + b"0".ljust(6)
            + b"100644".ljust(8)
            + str(len(data)).encode().ljust(10)
            + b"`\n"
        )
        f.write(header)
        f.write(data)
        if len(data) % 2:
            f.write(b"\n")
print("OK", out)
PY
else
  echo "WARN: dpkg-deb/ar/python not found. Staged at ${PKG}"
  echo "On Debian/Ubuntu: dpkg-deb --build ${PKG} ${DEB}"
  exit 0
fi
ls -lh "${DEB}" 2>/dev/null || true
