Name:           ratib-branch-installer
Version:        1.0.0
Release:        1%{?dist}
Summary:        RATEB ERP Enterprise Branch Appliance
License:        Proprietary
URL:            https://rateb.sa
BuildArch:      noarch
Requires:       php-cli >= 8.2, php-pdo, php-sqlite3, php-gd, php-curl, php-zip, php-mbstring, php-xml, sqlite, openssl

%description
Official Branch Appliance installer for RATEB ERP.
Installs to /opt/ratib-branch, configures SQLite branch runtime,
registers systemd Hybrid Sync and Branch Web services.
One ERP • One Runtime • One Hybrid Sync Engine.

# Spec expects sources staged by build-rpm.sh into BUILDROOT via install section.
# Payload is provided as a tar in SOURCES.

%prep
%setup -q -n ratib-branch-payload

%install
rm -rf %{buildroot}
mkdir -p %{buildroot}/opt/ratib-branch
cp -a . %{buildroot}/opt/ratib-branch/
mkdir -p %{buildroot}/usr/lib/systemd/system
install -m 0644 deploy/enterprise-installers/systemd/ratib-hybrid-sync.service \
  %{buildroot}/usr/lib/systemd/system/ratib-hybrid-sync.service
install -m 0644 deploy/enterprise-installers/systemd/ratib-branch-web.service \
  %{buildroot}/usr/lib/systemd/system/ratib-branch-web.service

%pre
if [ -f /opt/ratib-branch/storage/branch/rateb-branch.sqlite ]; then
  stamp=$(date -u +%Y%m%d%H%M%S)
  mkdir -p /opt/ratib-branch/storage/branch/backups
  cp -a /opt/ratib-branch/storage/branch/rateb-branch.sqlite \
    /opt/ratib-branch/storage/branch/backups/pre-upgrade-${stamp}.sqlite || true
fi
systemctl stop ratib-branch-web.service ratib-hybrid-sync.service 2>/dev/null || true

%post
if ! id rateb >/dev/null 2>&1; then
  useradd --system --home /opt/ratib-branch --shell /sbin/nologin rateb || true
fi
mkdir -p /opt/ratib-branch/storage/branch/{logs,backups,tmp} /opt/ratib-branch/storage/sessions
chown -R rateb:rateb /opt/ratib-branch/storage || true
if [ ! -f /opt/ratib-branch/storage/branch/rateb-branch.sqlite ]; then
  cd /opt/ratib-branch
  php -d extension=pdo_sqlite -d extension=sqlite3 bin/hybrid-branch-install.php || true
fi
systemctl daemon-reload >/dev/null 2>&1 || true
systemctl enable ratib-hybrid-sync.service ratib-branch-web.service >/dev/null 2>&1 || true
systemctl restart ratib-hybrid-sync.service ratib-branch-web.service >/dev/null 2>&1 || true
cat > /usr/share/applications/ratib-branch.desktop <<'EOF'
[Desktop Entry]
Type=Application
Name=RATEB Branch
Exec=xdg-open http://127.0.0.1/
Terminal=false
Categories=Office;
EOF
exit 0

%preun
if [ "$1" -eq 0 ]; then
  systemctl stop ratib-branch-web.service ratib-hybrid-sync.service 2>/dev/null || true
  systemctl disable ratib-branch-web.service ratib-hybrid-sync.service 2>/dev/null || true
fi

%postun
if [ "$1" -eq 0 ]; then
  rm -f /usr/share/applications/ratib-branch.desktop
  # Keep database by default
  if [ "${RATIB_PURGE_STORAGE:-0}" = "1" ]; then
    rm -rf /opt/ratib-branch
  else
    if [ -d /opt/ratib-branch ]; then
      find /opt/ratib-branch -mindepth 1 -maxdepth 1 ! -name storage -exec rm -rf {} + 2>/dev/null || true
    fi
  fi
  systemctl daemon-reload >/dev/null 2>&1 || true
fi

%files
/opt/ratib-branch
/usr/lib/systemd/system/ratib-hybrid-sync.service
/usr/lib/systemd/system/ratib-branch-web.service

%changelog
* Mon Jul 13 2026 RATEB <support@rateb.sa> - 1.0.0-1
- Phase D.2 enterprise Branch Appliance package
