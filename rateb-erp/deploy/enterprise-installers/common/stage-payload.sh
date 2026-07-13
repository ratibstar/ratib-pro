#!/usr/bin/env bash
# Stage ERP tree for Branch Appliance packages (no live SQLite / sessions).
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
DEST="${1:-}"
if [[ -z "${DEST}" ]]; then
  echo "Usage: stage-payload.sh <dest-dir>" >&2
  exit 1
fi
rm -rf "${DEST}"
mkdir -p "${DEST}"

while IFS= read -r line || [[ -n "${line}" ]]; do
  line="${line%%#*}"
  line="$(echo "${line}" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
  [[ -z "${line}" ]] && continue
  src="${ROOT}/${line}"
  if [[ ! -e "${src}" ]]; then
    echo "WARN missing: ${line}" >&2
    continue
  fi
  mkdir -p "${DEST}/$(dirname "${line}")"
  cp -a "${src}" "${DEST}/${line}"
done < "${SCRIPT_DIR}/../payload/include-manifest.txt"

# Empty writable trees (never copy live DB)
mkdir -p \
  "${DEST}/storage/branch/logs" \
  "${DEST}/storage/branch/backups" \
  "${DEST}/storage/branch/tmp" \
  "${DEST}/storage/sessions"

# Enterprise systemd units (opt path) + universal (Phase D.3)
mkdir -p "${DEST}/deploy/enterprise-installers/systemd"
cp -a "${SCRIPT_DIR}/../systemd/." "${DEST}/deploy/enterprise-installers/systemd/"
mkdir -p "${DEST}/deploy/enterprise-installers/universal"
cp -a "${SCRIPT_DIR}/../universal/." "${DEST}/deploy/enterprise-installers/universal/"
mkdir -p "${DEST}/deploy/enterprise-installers/common"
cp -a "${SCRIPT_DIR}/../common/." "${DEST}/deploy/enterprise-installers/common/"
mkdir -p "${DEST}/deploy/enterprise-installers/zero-touch"
cp -a "${SCRIPT_DIR}/../zero-touch/." "${DEST}/deploy/enterprise-installers/zero-touch/"
# Runtime placeholder (self-contained PHP dropped here by packaging/CI)
mkdir -p "${DEST}/runtime/php"
if [[ -f "${SCRIPT_DIR}/../runtime/README.md" ]]; then
  cp -a "${SCRIPT_DIR}/../runtime/README.md" "${DEST}/runtime/"
fi

echo "${DEST}"
