#!/bin/bash
set -e

# Fix CSF outbound port 25 block for Rateb ERP mail delivery.
# Run as root: sudo bash /home/admin/public_html/rateb-erp/public/fix-port25-csf.sh

CSF_CONF="/etc/csf/csf.conf"

echo "=== CSF Port 25 Fix ==="

if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Must run as root. Use: sudo bash $0"
    exit 1
fi

if [ ! -f "$CSF_CONF" ]; then
    echo "ERROR: CSF config not found at $CSF_CONF"
    exit 1
fi

if ! command -v csf &> /dev/null; then
    echo "ERROR: csf command not found. Is CSF installed?"
    exit 1
fi

# Backup
cp "$CSF_CONF" "$CSF_CONF.bak.$(date +%s)"
echo "Backup created: $CSF_CONF.bak.*"

# Check if TCP_OUT already contains 25
if grep -qE '^TCP_OUT = "[^"]*\b25\b' "$CSF_CONF"; then
    echo "TCP_OUT already contains 25."
else
    echo "Adding 25 to TCP_OUT..."
    sed -i -E 's/^(TCP_OUT = "[^"]+)"/\1,25"/' "$CSF_CONF"
fi

# Disable SMTP Block if enabled (prevents PHP/ERP from sending mail)
if grep -qE '^SMTP_BLOCK = "1"' "$CSF_CONF"; then
    echo "Disabling CSF SMTP_BLOCK..."
    sed -i -E 's/^(SMTP_BLOCK = )"1"/\1"0"/' "$CSF_CONF"
else
    echo "SMTP_BLOCK already disabled or not set."
fi

# Restart CSF
echo "Restarting CSF..."
csf -r

# Test
echo ""
echo "=== Testing port 25 connectivity ==="
if command -v nc &> /dev/null; then
    nc -vz -w 5 gmail-smtp-in.l.google.com 25 || true
elif command -v telnet &> /dev/null; then
    echo -e "quit\r" | telnet gmail-smtp-in.l.google.com 25 || true
else
    echo "Neither nc nor telnet installed. Skipping live test."
fi

echo ""
echo "Done. You can re-test via:"
echo "https://rateb.sa/rateb-erp/public/port25-test.php"
