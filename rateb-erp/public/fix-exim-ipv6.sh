#!/bin/bash
set -e

# Force Exim to use IPv4 only for outbound delivery.
# This avoids Gmail rejecting mail due to missing IPv6 PTR records.
# Run as root: sudo bash /home/admin/public_html/rateb-erp/public/fix-exim-ipv6.sh

EXIM_CONF="/etc/exim.conf"

echo "=== Exim IPv4-only Fix ==="

if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Must run as root. Use: sudo bash $0"
    exit 1
fi

if [ ! -f "$EXIM_CONF" ]; then
    echo "ERROR: Exim config not found at $EXIM_CONF"
    exit 1
fi

# Backup
cp "$EXIM_CONF" "$EXIM_CONF.bak.$(date +%s)"
echo "Backup created: $EXIM_CONF.bak.*"

# Check if disable_ipv6 is already set
if grep -qE '^\s*disable_ipv6\s*=\s*true' "$EXIM_CONF"; then
    echo "disable_ipv6 = true already set."
else
    echo "Adding disable_ipv6 = true to $EXIM_CONF..."
    # Add at the top of the file
    sed -i '1s/^/# Force IPv4 for outbound delivery to avoid IPv6 PTR issues\ndisable_ipv6 = true\n\n/' "$EXIM_CONF"
fi

# Restart Exim
echo "Restarting Exim..."
if command -v systemctl &> /dev/null; then
    systemctl restart exim || service exim restart || true
else
    service exim restart || true
fi

# Verify Exim is running
echo "Checking Exim status..."
if command -v exim &> /dev/null; then
    exim -bV | head -n 1 || true
fi

echo ""
echo "Done. Exim should now use IPv4 only for outbound connections."
echo "Re-test email delivery from ERP admin/settings."
