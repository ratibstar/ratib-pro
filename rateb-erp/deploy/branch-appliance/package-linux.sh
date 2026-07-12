#!/usr/bin/env bash
# Phase D — build Linux customer deployment package (repository files only).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${ROOT}/storage/branch/package/linux"
STAMP="$(date -u +%Y%m%d%H%M%S)"
DEST="${OUT}/rateb-branch-appliance-${STAMP}"
mkdir -p "${DEST}"

copy_tree() {
  local src="$1" dst="$2"
  mkdir -p "$(dirname "$dst")"
  cp -a "$src" "$dst"
}

# Minimal runtime surface (not a second ERP — pointers + ops tools)
mkdir -p "${DEST}/bin" "${DEST}/deploy/systemd" "${DEST}/docs/branch-appliance" "${DEST}/config"

cp "${ROOT}/bin/hybrid-branch-appliance-install.php" "${DEST}/bin/"
cp "${ROOT}/bin/hybrid-branch-serve.php" "${DEST}/bin/"
cp "${ROOT}/bin/hybrid-branch-register.php" "${DEST}/bin/"
cp "${ROOT}/bin/hybrid-branch-diagnostics.php" "${DEST}/bin/"
cp "${ROOT}/bin/hybrid-branch-health.php" "${DEST}/bin/"
cp "${ROOT}/bin/hybrid-branch-backup.php" "${DEST}/bin/"
cp "${ROOT}/bin/hybrid-branch-update.php" "${DEST}/bin/"
cp "${ROOT}/bin/hybrid-branch-recover.php" "${DEST}/bin/"
cp "${ROOT}/bin/hybrid-branch-certify.php" "${DEST}/bin/"
cp "${ROOT}/bin/hybrid-sync-service.php" "${DEST}/bin/"
cp "${ROOT}/deploy/systemd/rateb-hybrid-sync.service" "${DEST}/deploy/systemd/"
cp "${ROOT}/config/hybrid.runtime.example.env" "${DEST}/config/"
cp "${ROOT}/VERSION" "${DEST}/"
cp -a "${ROOT}/docs/branch-appliance/." "${DEST}/docs/branch-appliance/"
cp "${ROOT}/deploy/branch-appliance/README.md" "${DEST}/README.md"

cat > "${DEST}/INSTALL.sh" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.." 2>/dev/null || cd "$(dirname "$0")"
# When extracted beside full ERP tree, run from rateb-erp root:
php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-appliance-install.php "$@"
EOF
chmod +x "${DEST}/INSTALL.sh"

MANIFEST="${DEST}/MANIFEST.txt"
{
  echo "RATEB Branch Appliance Linux Package"
  echo "built=${STAMP}"
  echo "version=$(cat "${ROOT}/VERSION")"
  find "${DEST}" -type f | sort
} > "${MANIFEST}"

TAR="${OUT}/rateb-branch-appliance-${STAMP}.tar.gz"
tar -C "${OUT}" -czf "${TAR}" "rateb-branch-appliance-${STAMP}"
echo "OK ${TAR}"
