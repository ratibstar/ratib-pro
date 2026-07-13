#!/usr/bin/env bash
# Phase D.3 — detect OS, arch, init system, package manager.
set -euo pipefail
OS_FAMILY=unknown
OS_ID=unknown
OS_VERSION=
ARCH="$(uname -m 2>/dev/null || echo unknown)"
case "${ARCH}" in
  x86_64|amd64) ARCH_NORM=x64 ;;
  aarch64|arm64) ARCH_NORM=arm64 ;;
  *) ARCH_NORM="${ARCH}" ;;
esac
INIT=unknown
PKG=unknown

if [[ -f /etc/os-release ]]; then
  # shellcheck disable=SC1091
  . /etc/os-release
  OS_ID="${ID:-unknown}"
  OS_VERSION="${VERSION_ID:-}"
  case "${ID_LIKE:-}${ID:-}" in
    *debian*|*ubuntu*) OS_FAMILY=debian; PKG=apt ;;
    *rhel*|*fedora*|*centos*) OS_FAMILY=rhel; PKG=dnf; command -v dnf >/dev/null || PKG=yum ;;
    *) OS_FAMILY="${ID:-linux}" ;;
  esac
fi
command -v systemctl >/dev/null 2>&1 && INIT=systemd

cat <<EOF
os_family=${OS_FAMILY}
os_id=${OS_ID}
os_version=${OS_VERSION}
arch=${ARCH}
arch_norm=${ARCH_NORM}
init=${INIT}
pkg=${PKG}
supported=1
EOF
