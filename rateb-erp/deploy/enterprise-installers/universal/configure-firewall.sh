#!/usr/bin/env bash
# Phase D.3 — open local HTTP port on ufw / firewalld / iptables.
set -euo pipefail
PORT="${1:?port required}"

opened=0
if command -v ufw >/dev/null 2>&1 && ufw status 2>/dev/null | grep -qi 'Status: active'; then
  ufw allow "${PORT}/tcp" comment 'RATIB Branch Web' || true
  opened=1
  echo "firewall=ufw port=${PORT}"
fi
if command -v firewall-cmd >/dev/null 2>&1 && systemctl is-active --quiet firewalld 2>/dev/null; then
  firewall-cmd --permanent --add-port="${PORT}/tcp" || true
  firewall-cmd --reload || true
  opened=1
  echo "firewall=firewalld port=${PORT}"
fi
if [[ "${opened}" -eq 0 ]] && command -v iptables >/dev/null 2>&1; then
  iptables -C INPUT -p tcp --dport "${PORT}" -j ACCEPT 2>/dev/null \
    || iptables -I INPUT -p tcp --dport "${PORT}" -j ACCEPT || true
  echo "firewall=iptables port=${PORT}"
  opened=1
fi
if [[ "${opened}" -eq 0 ]]; then
  echo "firewall=none (localhost-only OK)"
fi
exit 0
