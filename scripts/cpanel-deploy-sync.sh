#!/bin/bash
# cPanel Version Control: after git pull, sync checkout → /home/admin/public_html
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

MARKER="$(tr -d '\r\n' < public/rateb-build.txt 2>/dev/null || echo unknown)"
STAMP="deploy-$(date -u +%Y%m%dT%H%M%SZ)-${MARKER}"
LOG="${HOME}/.rateb-deploy-log"
PUBLIC_HTML="${RATEB_PUBLIC_HTML:-/home/admin/public_html}"
# Do NOT use rsync --delete on live public_html unless explicitly enabled (can break the site).
RATEB_RSYNC_DELETE="${RATEB_RSYNC_DELETE:-0}"

mkdir -p "$(dirname "$LOG")" 2>/dev/null || true

log() { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" | tee -a "$LOG"; }

log "start marker=${MARKER} ROOT=${ROOT} PUBLIC_HTML=${PUBLIC_HTML} rsync_delete=${RATEB_RSYNC_DELETE}"

# Keep in sync with scripts/github-cpanel-fileman-deploy-core.py (FAST_FILES + CRITICAL).
# GitHub fast deploy also uploads commit-changed paths under includes/, pages/, control-panel/, js/, css/, api/, public/.

CRITICAL_FILES=(
  ".htaccess"
  "favicon.php"
  "erp-health.php"
  "rateb-profile-fix.php"
  "pages/company-profile.php"
  "includes/rateb-php74-compat.php"
  "includes/rateb_html_global_ai_patch.php"
  "control-panel/includes/config.php"
  "control-panel/includes/control/public-marketing-urls.php"
  "control-panel/includes/control/sidebar.php"
  "control-panel/includes/control/contact-center-bridge.php"
  "control-panel/includes/control/contact-center-nav.php"
  "control-panel/pages/control/contact-center.php"
  "control-panel/pages/control/contact-center-migrate.php"
  "control-panel/pages/control/contact-center-app.php"
  "control-panel/pages/control/contact-center-ops.php"
  "control-panel/pages/control/contact-center-supervisor.php"
  "control-panel/pages/control/rcc-asset.php"
  "ratib-contact-center/bootstrap.php"
  "ratib-contact-center/public/asset.php"
  "ratib-contact-center/config/assets-manifest.php"
  "ratib-contact-center/public/assets/.htaccess"
  "control-panel/includes/control/registration-requests-content.php"
  "control-panel/pages/control/control-hub.php"
  "control-panel/pages/control-support-chats.php"
  "control-panel/pages/control-agencies.php"
  "control-panel/includes/control/layout-wrapper.php"
  "app/UI/GlobalAIButton.php"
  "css/global-ai-action.css"
  "css/chat-widget.css"
  "includes/rateb-overlay-dismiss-guard.php"
  "includes/rateb-home-public-nav-sync.php"
  "includes/rateb-profile-nav-guard.php"
  "includes/rateb-public-base-url.php"
  "includes/rateb-mega-nav-config.php"
  "includes/rateb-mega-nav-resolve.php"
  "includes/rateb-mega-nav-resolve.fallback.php"
  "includes/rateb-mega-nav-render.php"
  "includes/rateb-home-public-chrome-top.php"
  "includes/rateb-home-public-nav-bootstrap.php"
  "includes/rateb-public-marketing-density.php"
  "includes/rateb-marketing-expand-bar.php"
  "css/pages/home-marketing-focused.css"
  "js/pages/rateb-marketing-focused.js"
  "includes/rateb-home-public-footer.php"
  "includes/rateb-public-deploy-ensure.php"
  "includes/rateb-enterprise-trust-home.dist.php"
  "includes/rateb-operational-proof-render.dist.php"
  "includes/rateb-operational-proof-data.dist.php"
  "pages/home.php"
  "pages/about.php"
  "profile/index.php"
  "pages/site-content-media.php"
  "public/cms-media.php"
  "public/cms_media.php"
  "control-panel/pages/control/site-content.php"
  "pages/company-profile.php"
  "pages/about.php"
  "pages/home.php"
  "pages/about.php"
  "pages/company-profile.php"
  "pages/deploy-root.php"
  "public/rateb-build.txt"
  "rateb-platform-catalog/.htaccess"
  "rateb-platform-catalog/public/index.php"
  "rateb-platform-catalog/public/.htaccess"
  "rateb-platform-catalog/public/rateb-catalog-build.txt"
  "rateb-platform-catalog/bin/migrate.php"
  "rateb-platform-catalog/config/app.php"
  "js/pages/rateb-profile-nav-guard.js"
  "js/pages/rateb-mega-nav.js"
  "includes/rateb-about-sections.php"
  "includes/rateb-operational-proof-render.php"
  "includes/rateb-operational-proof-data.php"
  "js/pages/home-page.js"
  "css/pages/home-public.css"
  "css/pages/rateb-mega-nav.css"
  "control-panel/includes/control/layout-wrapper.php"
  "control-panel/includes/control/client-platform-nav.php"
  "css/pages/about-enterprise.css"
  "js/pages/about-enterprise.js"
  "pages/partner-portal-login.php"
  "pages/architecture.php"
  "pages/security-compliance.php"
  "pages/procurement-legal.php"
  "includes/rateb-enterprise-trust-home.php"
  "includes/rateb-operational-proof-data.php"
  "includes/rateb-operational-proof-render.php"
  "css/pages/enterprise-trust-layer.css"
  "css/pages/operational-proof.css"
  "includes/rateb-security-compliance-data.php"
  "includes/rateb-security-compliance-sections.php"
  "includes/rateb-architecture-data.php"
  "includes/rateb-architecture-sections.php"
  "includes/rateb-procurement-legal-data.php"
  "includes/rateb-procurement-legal-sections.php"
  "pages/security-compliance.php"
  "pages/architecture.php"
  "pages/procurement-legal.php"
  "css/pages/security-compliance.css"
  "css/pages/architecture.css"
  "css/pages/procurement-legal.css"
  "includes/rateb-public-cms.php"
  "includes/rateb-site-content-rebrand-sanitize.php"
  "includes/site-content.php"
  "includes/site-content-home-data.php"
  "includes/site-content-profile-data.php"
  "includes/site-content-public-data.php"
  "api/site-content-media.php"
  "public/cms-bundle-about.png"
  "public/cms-bundle-pipeline.svg"
  "public/cms-bundle-workers.svg"
  "public/cms-bundle-finance.svg"
  "public/cms-bundle-gov-control.png"
  "public/cms-bundle-gov-control-v2.png"
  "public/cms-bundle-gov-inspections.png"
  "public/cms-bundle-gov-tracking.png"
  "public/cms-bundle-gov-onboarding.png"
  "public/cms-bundle-diagram-workflow.svg"
  "public/cms-bundle-diagram-onboarding.svg"
  "public/cms-bundle-diagram-deployment.svg"
  "public/cms-bundle-diagram-tenant.svg"
  "public/cms-bundle-diagram-events.svg"
  "modules/infrastructure-marketplace/Infrastructure/InfraEnvBootstrap.php"
  "modules/infrastructure-marketplace/Security/Secrets/InfraProviderSecretsSync.php"
  "modules/infrastructure-marketplace/Cli/infra-ensure-secret-key.php"
  "modules/infrastructure-marketplace/Config/RuntimeOverrideStore.php"
  "modules/infrastructure-marketplace/Config/ModuleConfig.php"
  "modules/infrastructure-marketplace/bootstrap.php"
  "api/infrastructure-marketplace/control-update.php"
  "storage/infrastructure-marketplace/.htaccess"
  "modules/infrastructure-marketplace/Infrastructure/SchemaHelpers.php"
  "modules/infrastructure-marketplace/Migrations/005_provider_activation_marketplace.sql"
  "modules/infrastructure-marketplace/Migrations/008_provider_secrets_and_events.sql"
  "modules/infrastructure-marketplace/Security/Secrets/ProviderSecretCipher.php"
  "modules/infrastructure-marketplace/Security/Secrets/ProviderSecretStore.php"
  "modules/infrastructure-marketplace/Security/Secrets/ProviderSecretDbProvider.php"
  "modules/infrastructure-marketplace/Observability/ProviderEventLogger.php"
  "modules/infrastructure-marketplace/Observability/ProviderEventBus.php"
  "modules/infrastructure-marketplace/Providers/Health/ProviderHealthMonitor.php"
  "modules/infrastructure-marketplace/Cli/provider-health-monitor.php"
  "modules/infrastructure-marketplace/Cli/provider-events-retention.php"
  "modules/infrastructure-marketplace/Cli/production-verify.php"
  "modules/infrastructure-marketplace/Observability/ProviderFailureThrottle.php"
  "modules/infrastructure-marketplace/Observability/ProviderEventsRetention.php"
  "scripts/run-infra-migrations-safe.sh"
  "modules/infrastructure-marketplace/Diagnostics/ProviderDiagnosticsService.php"
  "modules/infrastructure-marketplace/Workers/InfrastructureProvisioningWorker.php"
  "modules/infrastructure-marketplace/Hosting/Adapters/CpanelWhmAdapter.php"
  "modules/infrastructure-marketplace/DNS/Adapters/CloudflareDnsAdapter.php"
  "modules/infrastructure-marketplace/SSL/Adapters/LetsEncryptSslAdapter.php"
  "modules/infrastructure-marketplace/Registrars/Adapters/NamecheapRegistrarAdapter.php"
  "modules/infrastructure-marketplace/Verification/MigrationVerifier.php"
  "modules/infrastructure-marketplace/Health/PrelaunchHealthService.php"
  "modules/infrastructure-marketplace/Security/Secrets/SecretManager.php"
  "modules/infrastructure-marketplace/Security/Secrets/PreparedEncryptedDbSecretProvider.php"
  "modules/infrastructure-marketplace/Migrations/ALL_for_admin_control_panel_db.sql"
  "modules/infrastructure-marketplace/Migrations/ALL_for_admin_out.sql"
  "api/infrastructure-marketplace/providers.php"
  "api/infrastructure-marketplace/provider-activation.php"
  "api/infrastructure-marketplace/domain-search.php"
)

TARGETS=()

add_target() {
  local t="${1%/}"
  [ -n "$t" ] || return 0
  [ -d "$t" ] || return 0
  local i
  for i in "${TARGETS[@]:-}"; do
    [ "$i" = "$t" ] && return 0
  done
  TARGETS+=("$t")
}

for UD in \
  "/var/cpanel/userdata/${USER}/rateb.sa" \
  "/var/cpanel/userdata/${USER}/rateb.sa_SSL" \
  "/var/cpanel/userdata/admin/rateb.sa" \
  "/var/cpanel/userdata/admin/rateb.sa_SSL"
do
  if [ -f "$UD" ]; then
    DR="$(grep -E '^documentroot:' "$UD" 2>/dev/null | head -1 | sed 's/^documentroot:[[:space:]]*//' | tr -d '\r')"
    add_target "$DR"
    log "userdata ${UD} -> ${DR:-<empty>}"
  fi
done

LIST="${ROOT}/config/cpanel-deploy-targets.txt"
if [ -f "$LIST" ]; then
  while IFS= read -r line || [ -n "$line" ]; do
    line="${line%%#*}"
    line="$(echo "$line" | xargs)"
    add_target "$line"
  done < "$LIST"
fi

add_target "$PUBLIC_HTML"
add_target "/home/admin/public_html"

ROOT_REAL="$(realpath "$ROOT" 2>/dev/null || echo "$ROOT")"
PUBLIC_REAL="$(realpath "$PUBLIC_HTML" 2>/dev/null || echo "$PUBLIC_HTML")"

fix_live_permissions() {
  local TARGET="$1"
  log "fix permissions ${TARGET}"
  # Apache must read every .htaccess (644). Wrong mode after deploy → 403 + unstyled site.
  find "${TARGET}" -type d -exec chmod 755 {} \; 2>/dev/null || true
  find "${TARGET}" -type f -name '.htaccess' -exec chmod 644 {} \; 2>/dev/null || true
  [ -f "${TARGET}/.htaccess" ] && chmod 644 "${TARGET}/.htaccess" 2>/dev/null || true
  find "${TARGET}/css" "${TARGET}/js" "${TARGET}/pages" "${TARGET}/includes" "${TARGET}/control-panel" \
    -type f \( -name '*.css' -o -name '*.js' -o -name '*.php' -o -name '*.svg' -o -name '*.woff2' \) \
    -exec chmod 644 {} \; 2>/dev/null || true
  # Log result for debugging in cPanel deploy log
  if [ -r "${TARGET}/.htaccess" ]; then
    log "OK .htaccess readable"
  else
    log "WARN .htaccess NOT readable — set 644 in File Manager"
  fi
}

sync_one_target() {
  local TARGET="$1"
  local TARGET_REAL
  TARGET_REAL="$(realpath "$TARGET" 2>/dev/null || echo "$TARGET")"

  if [ "$TARGET_REAL" = "$ROOT_REAL" ]; then
    log "git root is docroot ${TARGET} (no rsync copy needed; git pull updates files here)"
    fix_live_permissions "$TARGET"
    printf '%s\n' "$STAMP" > "${TARGET}/.rateb-deploy-stamp" 2>/dev/null || true
    printf '%s\n' "$STAMP" > "${TARGET}/pages/rateb-deploy-status.txt" 2>/dev/null || true
    chmod 644 "${TARGET}/pages/rateb-deploy-status.txt" 2>/dev/null || true
    if [ -d "${TARGET}/.git" ]; then
      git -C "${TARGET}" rev-parse HEAD 2>/dev/null | head -1 | tee -a "$LOG" || true
    fi
    return 0
  fi

  log "sync -> ${TARGET}"
  local ok=0
  local RSYNC_EXTRA=()
  if [ "$RATEB_RSYNC_DELETE" = "1" ]; then
    RSYNC_EXTRA+=(--delete)
  fi

  if command -v rsync >/dev/null 2>&1; then
    if rsync -a "${RSYNC_EXTRA[@]}" --exclude='.git/' "${ROOT}/" "${TARGET}/"; then
      ok=1
    else
      log "WARN rsync failed for ${TARGET}"
    fi
  fi
  if [ "$ok" -eq 0 ]; then
    log "fallback cp -a -> ${TARGET}"
    if cp -a "${ROOT}/." "${TARGET}/" 2>/dev/null; then
      ok=1
    else
      log "WARN cp -a failed for ${TARGET}"
      return 1
    fi
  fi

  for rel in "${CRITICAL_FILES[@]}"; do
    src="${ROOT}/${rel}"
    [ -f "$src" ] || continue
    mkdir -p "$(dirname "${TARGET}/${rel}")"
    cp -f "$src" "${TARGET}/${rel}" 2>/dev/null || true
  done

  fix_live_permissions "$TARGET"

  printf '%s\n' "$STAMP" > "${TARGET}/.rateb-deploy-stamp"
  if [ -f "${TARGET}/pages/rateb-deploy-status.txt" ]; then
    printf '%s\n' "$STAMP" > "${TARGET}/pages/rateb-deploy-status.txt"
    chmod 644 "${TARGET}/pages/rateb-deploy-status.txt" 2>/dev/null || true
  else
    printf '%s\n' "$STAMP" > "${TARGET}/pages/rateb-deploy-status.txt" 2>/dev/null || true
    chmod 644 "${TARGET}/pages/rateb-deploy-status.txt" 2>/dev/null || true
  fi

  local profile=no
  [ -f "${TARGET}/pages/company-profile.php" ] && profile=yes
  log "done ${TARGET} company_profile=${profile}"
  return 0
}

SYNCED=0
PUBLIC_OK=0

if [ "${#TARGETS[@]}" -eq 0 ]; then
  log "ERROR no deploy targets"
  exit 1
fi

for TARGET in "${TARGETS[@]}"; do
  if sync_one_target "$TARGET"; then
    SYNCED=$((SYNCED + 1))
    TARGET_REAL="$(realpath "$TARGET" 2>/dev/null || echo "$TARGET")"
    if [ "$TARGET_REAL" = "$PUBLIC_REAL" ] || [ "$TARGET" = "$PUBLIC_HTML" ]; then
      PUBLIC_OK=1
    fi
  fi
done

log "finished synced=${SYNCED} public_html_ok=${PUBLIC_OK}"

if [ ! -d "$PUBLIC_HTML" ]; then
  log "ERROR public_html missing: ${PUBLIC_HTML}"
  exit 1
fi

if [ "$ROOT_REAL" != "$PUBLIC_REAL" ] && [ "$PUBLIC_OK" -eq 0 ]; then
  log "ERROR did not sync to ${PUBLIC_HTML}"
  exit 1
fi

exit 0
