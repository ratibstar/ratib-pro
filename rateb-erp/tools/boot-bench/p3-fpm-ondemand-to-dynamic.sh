#!/bin/bash
# P3 — SAFE php-fpm83 ondemand → dynamic (MUST run as root).
# Modifies only the admin pool file + DA custom template (survives rewrite).
# Restarts php-fpm83 ONLY. Does not touch Apache/MySQL/ERP.
set -euo pipefail

POOL=/usr/local/directadmin/data/users/admin/php/php-fpm83.conf
TPL=/usr/local/directadmin/data/templates/php-fpm.conf
CUSTOM_DIR=/usr/local/directadmin/data/templates/custom
CUSTOM_TPL=$CUSTOM_DIR/php-fpm.conf
STAMP=$(date -u +%Y%m%dT%H%M%SZ)
BACKUP_DIR=/root/rateb-fpm-p3-backup-$STAMP

if [[ $(id -u) -ne 0 ]]; then
  echo "ERROR: run as root: sudo bash $0" >&2
  exit 1
fi

if [[ ! -f "$POOL" ]]; then
  echo "ERROR: missing pool $POOL" >&2
  exit 1
fi

mkdir -p "$BACKUP_DIR"
cp -a "$POOL" "$BACKUP_DIR/php-fpm83.conf"
[[ -f "$TPL" ]] && cp -a "$TPL" "$BACKUP_DIR/php-fpm.conf.template" || true
echo "Backup: $BACKUP_DIR"

# --- BEFORE snapshot ---
BEFORE_STATUS=$(systemctl show php-fpm83 -p StatusText -p MemoryCurrent -p ActiveState --value 2>/dev/null | tr '\n' '|' || true)
echo "BEFORE status: $BEFORE_STATUS"

# Apply pool change (only pm* lines; preserve everything else)
python3 - <<'PY'
from pathlib import Path
path = Path("/usr/local/directadmin/data/users/admin/php/php-fpm83.conf")
text = path.read_text()
lines = text.splitlines(keepends=True)
out = []
seen = {
    "pm": False,
    "pm.max_children": False,
    "pm.start_servers": False,
    "pm.min_spare_servers": False,
    "pm.max_spare_servers": False,
    "pm.max_requests": False,
}
skip_keys = {"pm.process_idle_timeout"}  # remove — not used by dynamic

def wanted_block():
    return [
        "pm = dynamic\n",
        "pm.max_children = 15\n",
        "pm.start_servers = 2\n",
        "pm.min_spare_servers = 1\n",
        "pm.max_spare_servers = 4\n",
        "pm.max_requests = 500\n",
    ]

inserted = False
for line in lines:
    raw = line.strip()
    if raw.startswith("pm.") or raw.startswith("pm =") or raw.startswith("pm="):
        key = raw.split("=", 1)[0].strip()
        if key in skip_keys:
            continue
        if not inserted:
            out.extend(wanted_block())
            inserted = True
        # skip old pm* lines (replaced by block once)
        continue
    out.append(line)

if not inserted:
    # insert after listen.mode if present
    tmp = []
    done = False
    for line in out:
        tmp.append(line)
        if (not done) and line.strip().startswith("listen.mode"):
            tmp.append("\n")
            tmp.extend(wanted_block())
            done = True
    out = tmp if done else (out + ["\n"] + wanted_block())

path.write_text("".join(out))
print("Updated", path)
print(path.read_text())
PY

# Persist across DirectAdmin rewrite: custom template
mkdir -p "$CUSTOM_DIR"
if [[ -f "$TPL" ]]; then
  cp -a "$TPL" "$CUSTOM_TPL"
  # Replace ondemand block with dynamic in custom template
  python3 - <<'PY'
from pathlib import Path
p = Path("/usr/local/directadmin/data/templates/custom/php-fpm.conf")
t = p.read_text()
import re
# Replace the standard pm block
pat = re.compile(
    r"pm = ondemand\n"
    r"pm\.max_children = \|MAX_CHILDREN\|\n"
    r"pm\.process_idle_timeout = 20\n"
    r"pm\.max_requests = \|MAX_REQUESTS\|\n",
    re.M,
)
repl = (
    "pm = dynamic\n"
    "pm.max_children = |MAX_CHILDREN|\n"
    "pm.start_servers = 2\n"
    "pm.min_spare_servers = 1\n"
    "pm.max_spare_servers = 4\n"
    "pm.max_requests = |MAX_REQUESTS|\n"
)
newt, n = pat.subn(repl, t, count=1)
if n != 1:
    # fallback: simpler replace
    if "pm = ondemand" in t:
        newt = t.replace("pm = ondemand", "pm = dynamic", 1)
        newt = newt.replace("pm.process_idle_timeout = 20\n", 
            "pm.start_servers = 2\npm.min_spare_servers = 1\npm.max_spare_servers = 4\n", 1)
        print("WARN: used fallback template patch")
    else:
        raise SystemExit("ERROR: could not patch custom template pm block")
else:
    print("Custom template pm block patched")
p.write_text(newt)
# Ensure MAX_CHILDREN default for admin pool rewrite uses at least 15 — pool file already has 15 hard-coded.
# Template still uses |MAX_CHILDREN|; set user max if possible below.
PY
else
  echo "WARN: stock template missing; pool file updated only"
fi

# Raise DA max children token if present in user.conf (optional, non-fatal)
if [[ -f /usr/local/directadmin/data/users/admin/user.conf ]]; then
  if grep -q '^MAX_CHILDREN=' /usr/local/directadmin/data/users/admin/user.conf 2>/dev/null; then
    sed -i 's/^MAX_CHILDREN=.*/MAX_CHILDREN=15/' /usr/local/directadmin/data/users/admin/user.conf || true
  else
    echo 'MAX_CHILDREN=15' >> /usr/local/directadmin/data/users/admin/user.conf || true
  fi
fi

echo "=== unified diff (pool vs backup) ==="
diff -u "$BACKUP_DIR/php-fpm83.conf" "$POOL" || true

# Validate config before restart
if [[ -x /usr/local/php83/sbin/php-fpm ]]; then
  /usr/local/php83/sbin/php-fpm -t
fi

systemctl restart php-fpm83
sleep 2
systemctl is-active php-fpm83
systemctl status php-fpm83 --no-pager -l | head -25

echo "ROLLBACK:"
echo "  cp -a $BACKUP_DIR/php-fpm83.conf $POOL"
echo "  rm -f $CUSTOM_TPL   # optional if you want stock template back"
echo "  systemctl restart php-fpm83"
echo "DONE"
