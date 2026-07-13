#!/usr/bin/env bash
# Phase D.3 — find first free port from candidate list.
set -euo pipefail
CANDIDATES=(${RATEB_PORT_CANDIDATES:-80 443 8080 8088 8099})
EXTRA_START=${RATEB_PORT_FALLBACK_START:-8100}
EXTRA_END=${RATEB_PORT_FALLBACK_END:-8199}

port_free() {
  local p="$1"
  if command -v ss >/dev/null 2>&1; then
    ! ss -ltn 2>/dev/null | awk '{print $4}' | grep -Eq "[:.]${p}$"
  elif command -v netstat >/dev/null 2>&1; then
    ! netstat -ltn 2>/dev/null | awk '{print $4}' | grep -Eq "[:.]${p}$"
  else
    # bash /dev/tcp probe
    ! (echo >/dev/tcp/127.0.0.1/"${p}") 2>/dev/null
  fi
}

for p in "${CANDIDATES[@]}"; do
  if port_free "${p}"; then
    echo "${p}"
    exit 0
  fi
done
for ((p=EXTRA_START; p<=EXTRA_END; p++)); do
  if port_free "${p}"; then
    echo "${p}"
    exit 0
  fi
done
echo "ERROR: no free HTTP port" >&2
exit 1
