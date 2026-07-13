#!/usr/bin/env bash
# Phase D.3 — schedule daily/weekly/monthly backups + recovery watchdog.
set -euo pipefail
INSTALL_ROOT="${1:-/opt/ratib-branch}"
PHP_BIN="${2:-$(command -v php)}"
USER_NAME="${RATEB_BRANCH_USER:-rateb}"

mkdir -p /etc/ratib
cat > /etc/ratib/branch-backup.env <<EOF
RATEB_BRANCH_ROOT=${INSTALL_ROOT}
RATEB_PHP_BIN=${PHP_BIN}
EOF

# systemd timers
cat > /etc/systemd/system/ratib-branch-backup@.service <<EOF
[Unit]
Description=RATIB Branch Backup (%i)
After=network.target

[Service]
Type=oneshot
EnvironmentFile=/etc/ratib/branch-backup.env
WorkingDirectory=${INSTALL_ROOT}
User=${USER_NAME}
ExecStart=${PHP_BIN} -d extension=pdo_sqlite -d extension=sqlite3 ${INSTALL_ROOT}/bin/hybrid-branch-backup.php --label=%i
EOF

cat > /etc/systemd/system/ratib-branch-backup-daily.timer <<'EOF'
[Unit]
Description=RATIB Branch daily backup
[Timer]
OnCalendar=daily
Persistent=true
Unit=ratib-branch-backup@daily.service
[Install]
WantedBy=timers.target
EOF

cat > /etc/systemd/system/ratib-branch-backup-weekly.timer <<'EOF'
[Unit]
Description=RATIB Branch weekly backup
[Timer]
OnCalendar=weekly
Persistent=true
Unit=ratib-branch-backup@weekly.service
[Install]
WantedBy=timers.target
EOF

cat > /etc/systemd/system/ratib-branch-backup-monthly.timer <<'EOF'
[Unit]
Description=RATIB Branch monthly backup
[Timer]
OnCalendar=monthly
Persistent=true
Unit=ratib-branch-backup@monthly.service
[Install]
WantedBy=timers.target
EOF

# Integrity watchdog → auto-recover
cat > /etc/systemd/system/ratib-branch-recover.service <<EOF
[Unit]
Description=RATIB Branch SQLite auto-recovery check
[Service]
Type=oneshot
WorkingDirectory=${INSTALL_ROOT}
User=${USER_NAME}
ExecStart=${PHP_BIN} -d extension=pdo_sqlite -d extension=sqlite3 ${INSTALL_ROOT}/bin/hybrid-branch-recover.php
EOF

cat > /etc/systemd/system/ratib-branch-recover.timer <<'EOF'
[Unit]
Description=RATIB Branch recovery watchdog (hourly)
[Timer]
OnCalendar=hourly
Persistent=true
[Install]
WantedBy=timers.target
EOF

systemctl daemon-reload
systemctl enable --now ratib-branch-backup-daily.timer ratib-branch-backup-weekly.timer ratib-branch-backup-monthly.timer ratib-branch-recover.timer
echo "OK backup+recover timers enabled"
