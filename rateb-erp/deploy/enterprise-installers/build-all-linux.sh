#!/usr/bin/env bash
# Build all Linux enterprise artifacts (.run / .deb / .rpm) when tools allow.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
EI="${ROOT}/deploy/enterprise-installers"
chmod +x "${EI}/common/"*.sh "${EI}/linux-run/"*.sh "${EI}/deb/"*.sh "${EI}/rpm/"*.sh 2>/dev/null || true
bash "${EI}/linux-run/build-run.sh"
bash "${EI}/deb/build-deb.sh"
bash "${EI}/rpm/build-rpm.sh"
echo "Artifacts under: ${ROOT}/storage/branch/enterprise-installers/"
ls -lh "${ROOT}/storage/branch/enterprise-installers/" || true
