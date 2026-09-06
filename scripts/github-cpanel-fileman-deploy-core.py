#!/usr/bin/env python3
"""
cPanel Fileman deploy — text via save_file_content; images/SVG via upload_files (multipart).
fast mode: FAST_FILES baseline + any commit-changed paths under DEPLOY_ALLOW_PREFIXES.
critical/all on manual full sync only.
"""
from __future__ import annotations

import json
import mimetypes
import os
import secrets
import shutil
import subprocess
import sys
import glob
import urllib.parse
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from threading import Lock

# Paths auto-uploaded when changed in the pushed commit (see build_file_list).
DEPLOY_ALLOW_PREFIXES = (
    "app/Accounting/",
    "includes/",
    "pages/",
    "control-panel/",
    "rateb-erp/",
    "rateb-platform-catalog/",
    "ratib-contact-center/",
    "js/",
    "css/",
    "api/",
    "storage/",
    "modules/infrastructure-marketplace/",
    "config/env/",
    "public/",
    "public/profile-media/",
    "assets/images/government/",
    "assets/images/diagrams/",
    "assets/images/about-rateb-command.png",
    "assets/images/program-preview-pipeline.svg",
    "assets/images/program-preview-workers.svg",
    "assets/images/program-preview-finance.svg",
    "uploads/rateb_cms_media/",
)
DEPLOY_ALLOW_FILES = frozenset({
    ".htaccess",
    "index.php",
    "erp-health.php",
    "config/env.php",
    "config/accounting.php",
    "config/test-control-db.php",
})
DEPLOY_DENY_PREFIXES = (
    "Designed/",
    ".git/",
    ".github/",
    ".cursor/",
    "archive/",
    "node_modules/",
)
FAST_DEPLOY_CHANGED_CAP = 200
# When a commit touches more than this many deployable paths, upload all of them (no cap).
FAST_DEPLOY_LARGE_COMMIT_THRESHOLD = 200

# save_file_content percent-encoded bodies break cPanel JSON serializer on PNG/large files.
BINARY_EXTENSIONS = frozenset({
    ".png",
    ".jpg",
    ".jpeg",
    ".gif",
    ".webp",
    ".ico",
    ".svg",
    ".woff",
    ".woff2",
    ".ttf",
    ".eot",
    ".pdf",
    ".apk",
    ".aab",
    # Offline V2 SQLite WASM runtime (corrupt if uploaded as text via save_file_content)
    ".wasm",
})


def is_binary_upload(rel: str) -> bool:
    norm = rel.replace("\\", "/")
    if norm.startswith("rateb-erp/migrations/") and norm.endswith(".sql"):
        return True
    return os.path.splitext(rel)[1].lower() in BINARY_EXTENSIONS


def fileman_upload_dir(abs_dir: str) -> str:
    """upload_files expects dir relative to account home (e.g. public_html/public)."""
    marker = "/public_html"
    idx = abs_dir.find(marker)
    if idx >= 0:
        return "public_html" + abs_dir[idx + len(marker) :]
    return abs_dir


def fileman_home_rel(abs_dir: str, name: str) -> str:
    """Path from account home, e.g. public_html/public/cms-bundle-gov-control.png."""
    parent = fileman_upload_dir(abs_dir)
    return f"{parent}/{name}" if parent else name


def api2_fileop_unlink(home_rel_path: str, *, require_ok: bool = False) -> bool:
    """Remove a remote file via cPanel Fileman. Returns True on API success."""
    host = os.environ["CPANEL_HOST"]
    port = os.environ.get("CPANEL_PORT", "2083")
    user = os.environ["CPANEL_USER"]
    token = os.environ["CPANEL_API_TOKEN"]
    params = urllib.parse.urlencode(
        {
            "cpanel_jsonapi_user": user,
            "cpanel_jsonapi_apiversion": "2",
            "cpanel_jsonapi_module": "Fileman",
            "cpanel_jsonapi_func": "fileop",
            "op": "unlink",
            "sourcefiles": home_rel_path,
        }
    ).encode("utf-8")
    req = urllib.request.Request(
        f"https://{host}:{port}/json-api/cpanel",
        data=params,
        method="POST",
        headers={
            "Authorization": f"cpanel {user}:{token}",
            "Content-Type": "application/x-www-form-urlencoded",
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            payload = json.loads(resp.read().decode("utf-8", errors="replace"))
        # cPanel API2 often returns result:1 on success.
        ok = True
        if isinstance(payload, dict):
            cpanelresult = payload.get("cpanelresult") or {}
            data = cpanelresult.get("data")
            if isinstance(data, list) and data:
                row = data[0] if isinstance(data[0], dict) else {}
                if "result" in row:
                    ok = str(row.get("result")) in ("1", "true", "True")
                if row.get("error") or row.get("reason"):
                    ok = False
        if require_ok and not ok:
            raise RuntimeError(f"fileman_unlink_failed:{home_rel_path}:{payload}")
        return ok
    except Exception as exc:
        if require_ok:
            raise
        # Best-effort for overwrite-before-upload paths.
        return False


def build_multipart_body(
    fields: dict[str, str],
    files: list[tuple[str, str, bytes, str]],
) -> tuple[bytes, str]:
    boundary = secrets.token_hex(16)
    bnd = boundary.encode("ascii")
    chunks: list[bytes] = []
    for key, val in fields.items():
        chunks.append(b"--" + bnd + b"\r\n")
        chunks.append(
            f'Content-Disposition: form-data; name="{key}"\r\n\r\n'.encode("utf-8")
        )
        chunks.append(val.encode("utf-8") + b"\r\n")
    for field, filename, content, mime in files:
        chunks.append(b"--" + bnd + b"\r\n")
        chunks.append(
            (
                f'Content-Disposition: form-data; name="{field}"; filename="{filename}"\r\n'
                f"Content-Type: {mime}\r\n\r\n"
            ).encode("utf-8")
        )
        chunks.append(content + b"\r\n")
    chunks.append(b"--" + bnd + b"--\r\n")
    return b"".join(chunks), f"multipart/form-data; boundary={boundary}"


def mime_for_filename(name: str) -> str:
    guessed, _ = mimetypes.guess_type(name)
    return guessed or "application/octet-stream"


# Security-retired HTTP scripts must be removed from the live tree because fast deploy
# uploads changed files but cannot infer repository deletions.
SECURITY_REMOTE_DELETE_FILES = [
    "fix_perms.php",
    "config/setup-admin.php",
    "pages/setup-admin.php",
    "api/hr/fix-employees-only.php",
    "api/hr/debug-query.php",
    "RATEB-DEPLOY-NOW.php",
    "rateb-deploy.php",
    "rateb-profile-fix.php",
    "rateb-profile-check.php",
    "designed-status.php",
    "control-panel/fix-500.php",
    "control-panel/create_admin.php",
    "control-panel/check_db.php",
    "control-panel/diagnose.php",
    "pages/check-login.php",
    "pages/test-error.php",
    "pages/test-config.php",
    "pages/tenant-test.php",
    "pages/deploy-root.php",
    "pages/rateb-reset-country-test-admin.php",
    "pages/rateb-fix-status.php",
    "pages/rateb-fix-perms.php",
    "pages/rateb-perms-check.php",
    "pages/rateb-profile-deploy.php",
    "pages/rateb-purge-cache.php",
    "pages/rateb-sync-from-github.php",
    "pages/rateb-mysql-probe.php",
    "pages/rateb-test-domain-probe.php",
    "pages/rateb-check-all-country-dbs.php",
    "api/tenants/create.php",
    "api/tenants/create-full.php",
    "api/tenants/self-test.php",
    "api/agents/add.php",
    "api/visa-applications.php",
    "api/visa-applications-simple.php",
    "api/visa-applications-basic.php",
    "api/settings/handler.php",
    "api/migrations/add_password_plain_column.php",
    "api/support-chat-health.php",
    "api/rateb-payment-trace.php",
    "api/diagnostics/tenant-isolation-self-test.php",
    "api/diagnostics/rateb-site-content-status.php",
    "api/diagnostics/rateb-home-deploy-probe.php",
    "api/accounting/transactions/run-gaap-migration-safe.php",
    "api/accounting/migrate-voucher-entry-approval.php",
    "api/accounting/migrate-trial-balance-view.php",
    "api/accounting/migrate-journal-entries-posting.php",
    "api/accounting/migrate-invoice-payment-journal-entries.php",
    "api/accounting/migrate-general-ledger.php",
    "api/accounting/migrate-erp-gl-foundation.php",
    "api/accounting/migrate-erp-audit-trail.php",
    "api/accounting/migrate-accounts-enums.php",
    "api/accounting/diagnostic-accounts.php",
    "api/accounting/settings.php",
    "control-panel/api/diagnostics/tenant-isolation-self-test.php",
    "control-panel/api/diagnostic/tenant-isolation-self-test.php",
    "control-panel/api/control/tenant-isolation-self-test.php",
    "control-panel/api/control/rcc-migrate-run.php",
    "control-panel/api/control/rateb-erp-migrate-run.php",
    "control-panel/api/control/platform-catalog-migrate-run.php",
    "control-panel/api/control/rcc-realtime-hub-run.php",
    "control-panel/api/control/agencies-erp-migrate.php",
    "control-panel/api/control/agencies-bootstrap-from-reference.php",
    "control-panel/pages/control/rateb-erp-migrate.php",
    "control-panel/pages/control/contact-center-migrate.php",
    "control-panel/pages/control/sync-test-domain.php",
    "paypal-checkout/test.php",
    "tap-payments/test-config.php",
    "config/test-control-db.php",
    "rateb-deploy-probe.php",
    "public/run-accounting-phase5-migrate.php",
    "public/run-accounting-phase4-migrate.php",
    "public/run-accounting-event-store-migrate.php",
    "public/run-accounting-schema-catchup.php",
    "rateb-erp/public/run-migrations.php",
    "rateb-platform-catalog/public/run-migrations.php",
    "rateb-erp/public/restore-super-admins.php",
    "rateb-erp/public/fix-erp-arabic.php",
    "rateb-erp/public/fix-cms-arabic.php",
    "rateb-erp/public/probe-router.php",
    "rateb-erp/public/erp-security-cert.php",
    "rateb-erp/public/enterprise-cert-run.php",
    "rateb-erp/public/qa-manifest-resolve.php",
    "rateb-erp/storage/deploy-migrate-token",
    "rateb-erp/modules/pos/app/Services/PosDemoDataSetupService.php",
    "ratib-contact-center/public/run-realtime-hub.php",
    "admin/debug-dashboard.php",
    "admin/dev/event-load-test.php",
    "admin/dev/event-retention.php",
    "admin/dev/validate-observability.php",
    "api/upload-document.php",
    "api/notifications/notifications.php",
    "api/reports/reports-real.php",
    "api/admin/backup_system.php",
    "api/agents/activate.php",
    "rateb-chrome-bust.php",
]

# Phase 2.1 was rolled back after the production runtime regression gate failed.
# Fast deploy cannot infer deleted repository files, so remove the aborted
# Admin-owned extraction artifacts from the live tree.
EXTRACTION_ROLLBACK_REMOTE_DELETE_FILES = [
    "rateb-erp/public/assets/offline/shared/runtime/runtime.js",
    "rateb-erp/public/assets/offline/shared/db/migrations.js",
    "rateb-erp/public/assets/offline/shared/db/sqlite-runtime.js",
    "rateb-erp/public/assets/offline/shared/identity/identity-module.js",
    "rateb-erp/public/assets/offline/shared/sync/sync-engine.js",
    "rateb-erp/public/assets/offline/shared/vendor/sqlite/README.md",
    "rateb-erp/public/assets/offline/shared/vendor/sqlite/index.d.mts",
    "rateb-erp/public/assets/offline/shared/vendor/sqlite/index.mjs",
    "rateb-erp/public/assets/offline/shared/vendor/sqlite/node.mjs",
    "rateb-erp/public/assets/offline/shared/vendor/sqlite/sqlite3-opfs-async-proxy.js",
    "rateb-erp/public/assets/offline/shared/vendor/sqlite/sqlite3-worker1.mjs",
    "rateb-erp/public/assets/offline/shared/vendor/sqlite/sqlite3.wasm",
]

SECURITY_REMOTE_DELETE_FILES += [
    "rateb-erp/tools/boot-bench/" + name
    for name in (
        "unlock-user.php", "phase-aa2-fresh-bench.php", "_aa2_fresh_worker.php",
        "phase-aa2-measure.php", "analyze-admin-profile.php", "opcache-probe.php",
        "phase-aa3-fresh-bench.php", "_aa3_fresh_worker.php", "mint-admin-cookie.php",
        "phase-aa1-route-signature.php", "_ae_af_rateb_app_route.inc.php",
        "phase-ae-ttfb-rootcause.php", "_ae_fpm_probe.php", "phase-p04-server-ttfb.php",
        "phase-pa-pos-register-audit.php", "probe-boot.php", "profile-admin-get-deep.php",
        "profile-admin-get.php", "_ad_resolve_routes.php", "remote-auth.php",
        "_ae_ag_rateb_app_route.inc.php", "phase-ah-accounting-audit.php",
        "phase-x-admin-sql-bench.php", "phase-ab-e2e-profile.php",
        "phase-ac-infra-audit.php", "phase-ab-warm-pass.php",
        "server-timing-probe.php", "render-shell.php",
    )
]


# Always-sync core (ships every push even when unchanged) + build marker LAST.
# Keep this list MINIMAL: only the public shell pieces that must never drift between
# repo and server, plus the build marker. Everything else deploys automatically when it
# changes in the pushed commit (see build_file_list -> git_changed_paths +
# is_auto_deploy_path / DEPLOY_ALLOW_PREFIXES). Do NOT re-add whole pages/modules here —
# that is what made every push upload ~130 unchanged files.
FAST_FILES = [
    ".htaccess",
    "index.php",
    "erp-health.php",
    "includes/config.php",
    "includes/rateb-clean-url.php",
    "includes/rateb-public-base-url.php",
    "config/env/load.php",
    "config/env/dotenv_bridge.php",
    "config/env/directadmin_db.php",
    "config/env/rateb_sa.php",
    "config/env.php",
    "includes/site-content.php",
    "includes/site-content-home-data.php",
    "includes/control_lookup_conn.php",
    "includes/rateb-users-schema.php",
    "control-panel/api/control/agency-db-helper.php",
    "pages/login.php",
    "pages/dashboard.php",
    "pages/rateb-check-all-country-dbs.php",
    "pages/rateb-reset-country-test-admin.php",
    "pages/rateb-mysql-probe.php",
    "pages/rateb-fix-status.php",
    "includes/header.php",
    "pages/home.php",
    "js/pages/rateb-profile-nav-guard.js",
    "includes/rateb-mega-nav-render.php",
    "includes/rateb-home-public-nav-bootstrap.php",
    "control-panel/includes/control/sidebar.php",
    "control-panel/includes/control/ErpProvisioningService.php",
    "rateb-erp/app/services/MigrationService.php",
    "rateb-erp/app/services/MobileDeviceRegistryService.php",
    "rateb-erp/app/services/MobileDeviceSchemaBootstrap.php",
    "rateb-erp/app/services/MobileDeviceService.php",
    "rateb-erp/app/services/MobileDeviceDbStore.php",
    "rateb-erp/app/controllers/Api/MobileDeviceController.php",
    "rateb-erp/app/controllers/Api/MobileConfigController.php",
    "rateb-erp/app/controllers/Api/HrEssPermissionRequestsController.php",
    "rateb-erp/app/services/HrEssPermissionRequestService.php",
    "rateb-erp/routes/modules/api.php",
    "rateb-erp/migrations/003_permissions_ar_evaluations.sql",
    "rateb-erp/migrations/206_mobile_devices_registry.sql",
    "rateb-erp/migrations/207_mobile_devices_push_foundation.sql",
    "rateb-erp/migrations/208_mobile_push_outbox.sql",
    "control-panel/includes/control/contact-center-bridge.php",
    "control-panel/includes/control/contact-center-nav.php",
    "control-panel/pages/control/contact-center.php",
    "control-panel/pages/control/contact-center-app.php",
    "control-panel/pages/control/contact-center-ops.php",
    "control-panel/pages/control/contact-center-supervisor.php",
    "control-panel/pages/control/rcc-asset.php",
    "ratib-contact-center/bootstrap.php",
    "ratib-contact-center/public/asset.php",
    "ratib-contact-center/config/assets-manifest.php",
    "ratib-contact-center/public/assets/.htaccess",
    "assets/rateb-logo.svg",
    "rateb-erp/views/admin/dashboard.php",
    "rateb-erp/views/company/dashboard.php",
    "rateb-erp/views/components/accounting-dashboard.php",
    "rateb-erp/views/components/dashboard/head.php",
    "rateb-erp/views/components/dashboard/hero.php",
    "rateb-erp/views/components/dashboard/metrics-strip.php",
    "rateb-erp/app/services/DashboardService.php",
    "rateb-erp/app/services/AccountingDashboardService.php",
    "rateb-erp/app/services/ModulePageStatsService.php",
    "rateb-erp/views/components/module-page-stats.php",
    "rateb-erp/views/layouts/main.php",
    "rateb-erp/views/components/crud-index.php",
    "rateb-erp/views/admin/company-permissions/index.php",
    "rateb-erp/views/admin/company-permissions/edit.php",
    "rateb-erp/public/assets/css/company-permissions.css",
    "rateb-erp/app/Core/Database.php",
    "rateb-erp/app/Core/HybridRuntime.php",
    "rateb-erp/app/controllers/Admin/CompanyPermissionsController.php",
    "rateb-erp/routes/modules/platform.php",
    "rateb-erp/views/company/accounting/profit-loss.php",
    "rateb-erp/views/company/accounting/balance-sheet.php",
    "rateb-erp/views/company/accounting/vat-report.php",
    "rateb-erp/views/company/accounting/cost-of-sales.php",
    "rateb-erp/views/company/accounting/bank-reconciliation-detail.php",
    "rateb-erp/views/company/accounting/zatca-settings.php",
    "rateb-erp/views/company/product-categories/index.php",
    "rateb-erp/views/company/supplier-comms/index.php",
    "rateb-erp/views/company/hr/employees/show.php",
    "rateb-erp/views/admin/inventory/index.php",
    "rateb-erp/views/components/crud-index.php",
    "rateb-erp/views/components/pagination.php",
    "rateb-erp/views/components/table-search.php",
    "rateb-erp/app/controllers/Admin/AdminControllers.php",
    "rateb-erp/app/services/HrService.php",
    "rateb-erp/views/components/dashboard/alerts.php",
    "rateb-erp/views/components/dashboard/ranks.php",
    "rateb-erp/public/assets/css/dashboard.css",
    "rateb-erp/public/assets/js/charts.js",
    "rateb-erp/public/assets/js/dashboard-charts-defer.js",
    "rateb-erp/public/assets/js/dashboard-tabs.js",
    "rateb-erp/config/lang/en.php",
    "rateb-erp/config/lang/ar.php",
    "rateb-erp/public/assets/js/table-tools.js",
    "rateb-erp/views/components/table-toolbar.php",
    "rateb-erp/views/components/crud-index.php",
    "rateb-erp/app/controllers/CrudController.php",
    "rateb-erp/app/services/ApprovalOversightService.php",
    "rateb-erp/app/controllers/Admin/AdminControllers.php",
    "rateb-erp/app/controllers/Admin/ExtendedControllers.php",
    "rateb-erp/views/layouts/main.php",
    "rateb-erp/routes/company.php",
    "rateb-erp/routes/web.php",
    "rateb-erp/routes/cms.php",
    "rateb-erp/public/assets/css/components.css",
    "rateb-erp/public/assets/css/variables.css",
    "rateb-erp/config/app.php",
    "rateb-erp/bin/enterprise-test/EnterpriseTestRunner.php",
    "rateb-erp/public/dashboard-skin.txt",
    "rateb-erp/public/assets/css/components.css",
    "rateb-erp/views/admin/approvals/index.php",
    "rateb-erp/public/assets/js/approvals-oversight.js",
    "rateb-erp/public/assets/js/erp-nav-instant.js",
    "rateb-erp/public/ratib-erp-build.txt",
    "api/create-order.php",
    "api/verify.php",
    "api/registration-request.php",
    "includes/payment_orders_schema.php",
    "includes/payment_api_bootstrap.php",
    "js/payment.js",
    "js/pages/home-page.js",
    "css/pages/home-public.css",
    "rateb-erp/views/marketing/partials/plans-grid.php",
    "rateb-erp/views/marketing/partials/agency-checkout-panel.php",
    "rateb-erp/public/assets/js/marketing-agency-register.js",
    "rateb-erp/public/assets/css/marketing-agency-register.css",
    "rateb-erp/views/layouts/marketing.php",
    "rateb-erp/public/index.php",
    "rateb-erp/app/Website/WebsiteKernel.php",
    "rateb-erp/public/agency-commercial-lock.php",
    "rateb-erp/public/assets/build-stamp.txt",
    "rateb-erp/public/erp-build-probe.php",
    "rateb-erp/public/erp-opcache-bust.php",
    "rateb-erp/public/pos-sw.js",
    "rateb-erp/public/offline-shell.html",
    "rateb-erp/public/manifest.webmanifest",
    "rateb-erp/public/connectivity-probe.json",
    "rateb-erp/public/assets/pwa/erp-icon-192.png",
    "rateb-erp/public/assets/pwa/erp-icon-512.png",
    "rateb-erp/public/assets/pwa/erp-icon.svg",
    "rateb-erp/public/assets/offline/rateb-offline.js",
    "rateb-erp/public/assets/offline/rateb-offline.min.js",
    "rateb-erp/public/assets/offline/erp-offline-tenant-context.js",
    "rateb-erp/public/assets/offline/erp-offline-shell-auth.js",
    "rateb-erp/public/assets/offline/erp-offline-shell-rbac.js",
    "rateb-erp/public/assets/offline/erp-shell-bootstrap.js",
    "rateb-erp/public/assets/offline/erp-offline-full-warm.js",
    "rateb-erp/public/assets/offline/erp-offline-nav-guard.js",
    "rateb-erp/public/assets/offline/ops-page-allowlist.json",
    "rateb-erp/public/assets/offline/erp-pwa-install.js",
    "rateb-erp/public/assets/css/variables.css",
    "rateb-erp/public/assets/css/main.css",
    "rateb-erp/public/assets/css/marketing.css",
    "rateb-erp/config/tenant-branding.php",
    "rateb-erp/config/permissions-system.php",
    "rateb-erp/app/services/CmsService.php",
    "rateb-erp/app/controllers/Marketing/MarketingController.php",
    "rateb-erp/views/layouts/marketing.php",
    "rateb-erp/views/marketing/home.php",
    "rateb-erp/views/marketing/partials/header.php",
    "rateb-erp/views/marketing/partials/footer.php",
    "rateb-erp/public/assets/css/components.css",
    "rateb-erp/public/assets/css/dark.css",
    "rateb-erp/public/assets/css/rtl.css",
    "rateb-erp/public/assets/css/light.css",
    "rateb-erp/public/assets/css/dashboard.css",
    "rateb-erp/public/assets/css/ar-typography.css",
    "rateb-erp/public/assets/js/connectivity-indicator.js",
    "rateb-erp/views/partials/sidebar-ops-nav.php",
    "rateb-erp/views/layouts/main.php",
    "rateb-erp/config/app.php",
    "rateb-erp/offline/OfflineModule.php",
    "rateb-platform-catalog/.htaccess",
    "rateb-platform-catalog/public/index.php",
    "rateb-platform-catalog/public/.htaccess",
    "rateb-platform-catalog/public/rateb-catalog-build.txt",
    "rateb-platform-catalog/bin/migrate.php",
    "rateb-platform-catalog/config/app.php",
    "uploads/.htaccess",
    # Marketing build marker — MUST stay last.
    "public/rateb-build.txt",
]

CRITICAL = [
    ".htaccess",
    "favicon.php",
    "erp-health.php",
    "public/rateb-build.txt",
    "pages/about.php",
    "profile/index.php",
    "pages/company-profile.php",
    "includes/rateb-public-base-url.php",
    "includes/rateb-clean-url.php",
    "includes/rateb-mega-nav-config.php",
    "includes/rateb-mega-nav-resolve.php",
    "includes/rateb-mega-nav-resolve.fallback.php",
    "includes/rateb-mega-nav-render.php",
    "includes/rateb-nav-asset-preflight.php",
    "includes/rateb-home-public-nav-bootstrap.php",
    "includes/rateb-home-public-nav-styles.php",
    "includes/rateb-enterprise-trust-home.php",
    "includes/rateb-public-deploy-ensure.php",
    "includes/rateb-home-public-chrome-top.php",
    "includes/rateb-overlay-dismiss-guard.php",
    "includes/rateb-home-public-nav-sync.php",
    "includes/rateb-home-public-footer.php",
    "includes/rateb-profile-nav-guard.php",
    "js/pages/rateb-mega-nav.js",
    "includes/rateb_html_global_ai_patch.php",
    "includes/rateb-about-profile-data.php",
    "includes/rateb-about-sections.php",
    "js/pages/rateb-profile-nav-guard.js",
    "js/pages/rateb-mega-nav.js",
    "js/pages/rateb-home-nav-chrome.js",
    "js/pages/about-enterprise.js",
    "js/pages/home-page.js",
    "css/pages/about-enterprise.css",
    "css/pages/home-public.css",
    "css/pages/rateb-mega-nav.css",
    "public/index.php",
    "includes/designed_bootstrap.php",
    "public/cms-media.php",
    "public/cms_media.php",
    "public/cms-bundle-gov-control.png",
    "public/cms-bundle-gov-control-v2.png",
    "pages/home.php",
    "control-panel/includes/control/public-marketing-urls.php",
    "control-panel/includes/control/sidebar.php",
    "control-panel/includes/control/registration-requests-content.php",
    "control-panel/includes/i18n.php",
    "control-panel/lang/modules/ar.php",
    "control-panel/lang/modules/en.php",
    "control-panel/lang/phrases/ar.php",
    "control-panel/pages/control/registration-requests.php",
    "control-panel/includes/control/registration-requests-purge.php",
    "control-panel/js/control/registration-requests-page.js",
    "control-panel/css/control/control-registration-requests.css",
    "control-panel/api/control/registration-requests.php",
    "control-panel/pages/control/control-hub.php",
    "control-panel/pages/control-support-chats.php",
    "control-panel/pages/control-agencies.php",
    "control-panel/pages/control/agencies.php",
    "control-panel/includes/control/agencies-content.php",
    "control-panel/js/control/agencies-standalone.js",
    "control-panel/css/control/agencies.css",
    "control-panel/api/control/agencies-reset-erp-data.php",
    "control-panel/api/control/agencies-restore-admin-login.php",
    "control-panel/includes/control/rateb-erp-bridge.php",
    "rateb-erp/app/services/AgencyErpMigrationService.php",
    "rateb-erp/app/services/DedicatedCompanySeedService.php",
    "rateb-erp/app/models/User.php",
    "rateb-erp/app/Core/Auth.php",
    "rateb-erp/app/Core/Middleware/Middleware.php",
    "rateb-erp/app/controllers/Shared/LoginController.php",
    "rateb-erp/app/controllers/Marketing/MarketingAuthController.php",
    "rateb-erp/app/controllers/Marketing/CustomerPortalController.php",
    "rateb-erp/routes/middleware-helpers.php",
    "rateb-erp/config/app.php",
    "rateb-erp/public/favicon.php",
    "rateb-erp/public/.htaccess",
    "rateb-erp/views/shared/auth/login.php",
    "rateb-erp/app/services/CompanyTenantWipeService.php",
    "rateb-erp/bin/ProductionResetRunner.php",
    "control-panel/includes/control/layout-wrapper.php",
    "app/UI/GlobalAIButton.php",
    "css/global-ai-action.css",
    "css/chat-widget.css",
]

CRITICAL_SET = set(CRITICAL) | set(FAST_FILES)

MUST_OK = [
    ".htaccess",
    "public/rateb-build.txt",
    "public/cms-media.php",
    "public/cms_media.php",
    "public/cms-bundle-gov-control.png",
    "public/cms-bundle-gov-control-v2.png",
    "pages/about.php",
    "pages/home.php",
    "includes/site-content.php",
    "includes/rateb-mega-nav-resolve.php",
    "includes/rateb-mega-nav-resolve.fallback.php",
    "js/pages/rateb-profile-nav-guard.js",
]

_print_lock = Lock()
_mkdir_cache: set[str] = set()


def api_post_raw(module: str, data: bytes, content_type: str) -> dict:
    host = os.environ["CPANEL_HOST"]
    port = os.environ.get("CPANEL_PORT", "2083")
    user = os.environ["CPANEL_USER"]
    token = os.environ["CPANEL_API_TOKEN"]
    req = urllib.request.Request(
        f"https://{host}:{port}/execute/{module}",
        data=data,
        method="POST",
        headers={
            "Authorization": f"cpanel {user}:{token}",
            "Content-Type": content_type,
        },
    )
    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.loads(resp.read().decode("utf-8", errors="replace"))


def api_post(module: str, params: dict) -> dict:
    host = os.environ["CPANEL_HOST"]
    port = os.environ.get("CPANEL_PORT", "2083")
    user = os.environ["CPANEL_USER"]
    token = os.environ["CPANEL_API_TOKEN"]
    data = urllib.parse.urlencode(params).encode("utf-8")
    req = urllib.request.Request(
        f"https://{host}:{port}/execute/{module}",
        data=data,
        method="POST",
        headers={
            "Authorization": f"cpanel {user}:{token}",
            "Content-Type": "application/x-www-form-urlencoded",
        },
    )
    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.loads(resp.read().decode("utf-8", errors="replace"))


def uapi_ok(payload: dict) -> bool:
    rblock = payload.get("result", payload) or {}
    st = int(rblock.get("status", payload.get("status", 0)) or 0)
    return st == 1


def upload_files_ok(payload: dict) -> bool:
    if uapi_ok(payload):
        return True
    data = payload.get("data")
    if not isinstance(data, dict):
        rblock = payload.get("result", payload) or {}
        data = rblock.get("data") if isinstance(rblock, dict) else None
    if isinstance(data, dict) and int(data.get("succeeded", 0) or 0) >= 1:
        return True
    return False


def ensure_remote_dir(remote_dir: str, remote_base: str) -> None:
    """Create nested remote dirs (required before save_file_content for public/profile-media/...)."""
    remote_dir = remote_dir.rstrip("/")
    remote_base = remote_base.rstrip("/")
    if remote_dir in _mkdir_cache or remote_dir == remote_base:
        return
    suffix = remote_dir[len(remote_base) :].strip("/")
    if suffix == "":
        _mkdir_cache.add(remote_dir)
        return
    built = remote_base
    for part in suffix.split("/"):
        if not part:
            continue
        built = f"{built}/{part}"
        if built in _mkdir_cache:
            continue
        try:
            api_post("Fileman/mkdir", {"path": built, "permissions": "0755"})
        except Exception:
            pass
        _mkdir_cache.add(built)


RATEB_ERP_TRIGGER_PREFIXES = (
    "rateb-erp/",
    "control-panel/pages/control/rateb-erp",
    "control-panel/includes/control/rateb-erp",
)

# Full rateb-erp tree upload only when CP bridge or migrations change (not every PHP edit).
RATEB_ERP_FULL_BUNDLE_PREFIXES = (
    "control-panel/pages/control/rateb-erp",
    "control-panel/includes/control/rateb-erp",
    "control-panel/css/control/rateb-erp",
    "control-panel/api/control/rateb-erp",
)


def rateb_site_accounting_app_files() -> list[str]:
    """Phase 6 — site app/Accounting beside rateb-erp (not inside rateb-erp/). Always ship on deploy."""
    out: list[str] = []
    acc_root = os.path.join(os.getcwd(), "app", "Accounting")
    if os.path.isdir(acc_root):
        for dirpath, _dirnames, filenames in os.walk(acc_root):
            for name in filenames:
                if not name.endswith(".php"):
                    continue
                rel = os.path.relpath(os.path.join(dirpath, name), os.getcwd()).replace("\\", "/")
                if is_auto_deploy_path(rel):
                    out.append(rel)
    for rel in ("app/Core/Autoloader.php", "config/accounting.php"):
        if os.path.isfile(rel) and is_auto_deploy_path(rel):
            out.append(rel)
    return sorted(set(out))


def rateb_accounting_control_ui_files() -> list[str]:
    """Phase 6 Control Center — ERP views + static assets (always ship).

    Runtime canonical set: rateb-erp/public/assets/accounting-control/ (layout.php L18–21).
    js/accounting-control/, css/accounting-control/, and nested public/assets/js|css paths
    are deployment mirrors only — not loaded by the Control Center UI.
    """
    fixed = [
        "rateb-erp/views/admin/accounting-control/layout.php",
        "rateb-erp/views/admin/accounting-control/i18n-payload.php",
        "rateb-erp/public/assets/accounting-control/build.txt",
        "rateb-erp/public/assets/js/accounting-control/control-center.js",
        "rateb-erp/public/assets/css/accounting-control/control-center.css",
        "rateb-erp/public/assets/accounting-control/control-center.js",
        "rateb-erp/public/assets/accounting-control/control-center.css",
        "rateb-erp/app/bootstrap/accounting-control-bridge.php",
        "rateb-erp/app/controllers/Admin/AccountingControlController.php",
        "public/run-accounting-schema-catchup.php",
        "config/accounting.php",
        "js/accounting-control/control-center.js",
        "css/accounting-control/control-center.css",
    ]
    out: list[str] = []
    acc_views = os.path.join(os.getcwd(), "rateb-erp", "views", "admin", "accounting-control")
    if os.path.isdir(acc_views):
        for dirpath, _dirnames, filenames in os.walk(acc_views):
            for name in filenames:
                rel = os.path.relpath(os.path.join(dirpath, name), os.getcwd()).replace("\\", "/")
                if is_auto_deploy_path(rel):
                    out.append(rel)
    for rel in fixed:
        if os.path.isfile(rel) and is_auto_deploy_path(rel):
            out.append(rel)
    return sorted(set(out))


def needs_full_erp_bundle(changed: list[str]) -> bool:
    flag = os.environ.get("CPANEL_ERP_FULL_BUNDLE", "").strip().lower()
    if flag in ("1", "true", "yes", "on"):
        return True
    if any(c.startswith("rateb-erp/migrations/") for c in changed):
        return True
    return any(
        any(c.startswith(prefix) for prefix in RATEB_ERP_FULL_BUNDLE_PREFIXES)
        for c in changed
    )


def rateb_erp_bundle_files() -> list[str]:
    """Upload full rateb-erp tree when ERP control-panel files change."""
    root = os.path.join(os.getcwd(), "rateb-erp")
    if not os.path.isdir(root):
        return []
    out: list[str] = []
    for dirpath, _dirnames, filenames in os.walk(root):
        for name in filenames:
            rel = os.path.relpath(os.path.join(dirpath, name), os.getcwd()).replace("\\", "/")
            if is_auto_deploy_path(rel):
                out.append(rel)
    return sorted(set(out))


def rateb_erp_control_panel_files() -> list[str]:
    """Companion Control Panel files (migrate page, hub CSS, bridge, API runners)."""
    cp_roots = [
        os.path.join(os.getcwd(), "control-panel/pages/control"),
        os.path.join(os.getcwd(), "control-panel/includes/control"),
        os.path.join(os.getcwd(), "control-panel/css/control"),
        os.path.join(os.getcwd(), "control-panel/api/control"),
    ]
    out: list[str] = []
    for root in cp_roots:
        if not os.path.isdir(root):
            continue
        for name in os.listdir(root):
            if not name.startswith("rateb-erp"):
                continue
            rel = os.path.relpath(os.path.join(root, name), os.getcwd()).replace("\\", "/")
            if os.path.isfile(os.path.join(os.getcwd(), rel)) and is_auto_deploy_path(rel):
                out.append(rel)
    return sorted(set(out))


RATIB_CC_TRIGGER_PREFIXES = (
    "ratib-contact-center/",
    "control-panel/pages/control/contact-center",
    "control-panel/includes/control/contact-center",
)


def needs_full_rcc_bundle(changed: list[str]) -> bool:
    flag = os.environ.get("CPANEL_RCC_FULL_BUNDLE", "").strip().lower()
    if flag in ("1", "true", "yes", "on"):
        return True
    if any(c.startswith("ratib-contact-center/migrations/") for c in changed):
        return True
    return any(
        any(c.startswith(prefix) for prefix in RATIB_CC_TRIGGER_PREFIXES)
        for c in changed
    )


def ratib_contact_center_bundle_files() -> list[str]:
    """Upload full ratib-contact-center tree when CP contact-center files change."""
    root = os.path.join(os.getcwd(), "ratib-contact-center")
    if not os.path.isdir(root):
        return []
    out: list[str] = []
    for dirpath, _dirnames, filenames in os.walk(root):
        for name in filenames:
            if name.endswith(".md"):
                continue
            rel = os.path.relpath(os.path.join(dirpath, name), os.getcwd()).replace("\\", "/")
            if is_auto_deploy_path(rel):
                out.append(rel)
    return sorted(set(out))


def ratib_cc_control_panel_files() -> list[str]:
    cp_roots = [
        os.path.join(os.getcwd(), "control-panel/pages/control"),
        os.path.join(os.getcwd(), "control-panel/includes/control"),
    ]
    out: list[str] = []
    for root in cp_roots:
        if not os.path.isdir(root):
            continue
        for name in os.listdir(root):
            if not name.startswith("contact-center"):
                continue
            rel = os.path.relpath(os.path.join(root, name), os.getcwd()).replace("\\", "/")
            if os.path.isfile(os.path.join(os.getcwd(), rel)) and is_auto_deploy_path(rel):
                out.append(rel)
    return sorted(set(out))


def rateb_platform_catalog_bundle_files() -> list[str]:
    """Upload full rateb-platform-catalog runtime tree (exclude storage, tests, docs, binaries)."""
    root = os.path.join(os.getcwd(), "rateb-platform-catalog")
    if not os.path.isdir(root):
        return []
    skip_prefixes = (
        "rateb-platform-catalog/storage/",
        "rateb-platform-catalog/tests/",
        "rateb-platform-catalog/docs/",
    )
    skip_names = frozenset({"meilisearch.exe"})
    out: list[str] = []
    for dirpath, _dirnames, filenames in os.walk(root):
        rel_dir = os.path.relpath(dirpath, os.getcwd()).replace("\\", "/")
        if rel_dir == "rateb-platform-catalog":
            rel_dir_prefix = "rateb-platform-catalog/"
        else:
            rel_dir_prefix = rel_dir + "/"
        if any(rel_dir_prefix.startswith(prefix) or rel_dir == prefix.rstrip("/") for prefix in skip_prefixes):
            continue
        for name in filenames:
            if name in skip_names or name.endswith(".mdb"):
                continue
            rel = os.path.relpath(os.path.join(dirpath, name), os.getcwd()).replace("\\", "/")
            if any(rel.startswith(prefix) for prefix in skip_prefixes):
                continue
            if is_auto_deploy_path(rel):
                out.append(rel)
    return sorted(set(out))


def is_auto_deploy_path(path: str) -> bool:
    if not path or path.startswith("."):
        return False
    for deny in DEPLOY_DENY_PREFIXES:
        if path.startswith(deny) or f"/{deny}" in f"/{path}/":
            return False
    if path in DEPLOY_ALLOW_FILES:
        return True
    return any(path.startswith(prefix) for prefix in DEPLOY_ALLOW_PREFIXES)


def remote_dir(remote_base: str, rel: str) -> str:
    parent = os.path.dirname(rel)
    if not parent or parent == ".":
        return remote_base
    return f"{remote_base}/{parent}"


def upload_binary_via_curl(
    rel: str,
    local_path: str,
    name: str,
    dest_dir: str,
) -> tuple[str, bool, str]:
    """cPanel-documented curl multipart upload (more reliable than hand-built bodies)."""
    host = os.environ["CPANEL_HOST"]
    port = os.environ.get("CPANEL_PORT", "2083")
    user = os.environ["CPANEL_USER"]
    token = os.environ["CPANEL_API_TOKEN"]
    url = f"https://{host}:{port}/execute/Fileman/upload_files"
    cmd = [
        "curl",
        "-sfS",
        "--connect-timeout",
        "60",
        "--max-time",
        "120",
        "-H",
        f"Authorization: cpanel {user}:{token}",
        "-F",
        f"dir={dest_dir}",
        "-F",
        "overwrite=1",
        "-F",
        f"file=@{local_path};filename={name}",
        url,
    ]
    proc = subprocess.run(cmd, capture_output=True, text=True)
    if proc.returncode != 0:
        err = (proc.stderr or proc.stdout or "curl failed").strip()
        return rel, False, err[:200]
    try:
        payload = json.loads(proc.stdout)
    except json.JSONDecodeError:
        return rel, False, (proc.stdout or "invalid JSON")[:200]
    if upload_files_ok(payload):
        return rel, True, ""
    return rel, False, json.dumps(payload)[:200]


def upload_binary_via_multipart(
    rel: str,
    raw: bytes,
    name: str,
    dest_dir: str,
) -> tuple[str, bool, str]:
    body, ctype = build_multipart_body(
        {"dir": dest_dir, "overwrite": "1"},
        [("file", name, raw, mime_for_filename(name))],
    )
    try:
        payload = api_post_raw("Fileman/upload_files", body, ctype)
    except Exception as e:
        return rel, False, str(e)
    if upload_files_ok(payload):
        return rel, True, ""
    rblock = payload.get("result", payload) or {}
    return rel, False, json.dumps(rblock)[:200]


def upload_binary_one(rel: str, remote_base: str) -> tuple[str, bool, str]:
    if not os.path.isfile(rel):
        return rel, False, "missing"
    abs_dir = remote_dir(remote_base, rel)
    name = os.path.basename(rel)
    dest_dir = fileman_upload_dir(abs_dir)
    ensure_remote_dir(abs_dir, remote_base)
    api2_fileop_unlink(fileman_home_rel(abs_dir, name))
    local_path = os.path.abspath(rel)
    if shutil.which("curl"):
        return upload_binary_via_curl(rel, local_path, name, dest_dir)
    with open(rel, "rb") as f:
        raw = f.read()
    return upload_binary_via_multipart(rel, raw, name, dest_dir)


def upload_text_one(rel: str, remote_base: str) -> tuple[str, bool, str]:
    if not os.path.isfile(rel):
        return rel, False, "missing"
    dir_path = remote_dir(remote_base, rel)
    name = os.path.basename(rel)
    ensure_remote_dir(dir_path, remote_base)
    with open(rel, "rb") as f:
        raw = f.read()
    try:
        content = raw.decode("utf-8")
    except UnicodeDecodeError:
        content = raw.decode("latin-1")
    try:
        payload = api_post(
            "Fileman/save_file_content",
            {"dir": dir_path, "file": name, "content": content},
        )
    except Exception as e:
        payload = None
        first_err = str(e)
    else:
        first_err = ""
    if payload is not None and uapi_ok(payload):
        return rel, True, ""
    # Fallback: multipart upload when save_file_content fails (e.g. new nested dirs on Fileman).
    if shutil.which("curl"):
        dest_dir = fileman_upload_dir(dir_path)
        api2_fileop_unlink(fileman_home_rel(dir_path, name))
        rel_path, ok, err = upload_binary_via_curl(rel, os.path.abspath(rel), name, dest_dir)
        if ok:
            return rel_path, True, ""
        if first_err and not err:
            err = first_err
        return rel, False, err or json.dumps(payload or {})[:200]
    rblock = (payload or {}).get("result", payload or {}) or {}
    err = first_err or json.dumps(rblock)[:200]
    return rel, False, err


def upload_one(rel: str, remote_base: str) -> tuple[str, bool, str]:
    if is_binary_upload(rel):
        return upload_binary_one(rel, remote_base)
    return upload_text_one(rel, remote_base)


def git_changed_paths() -> set[str]:
    sha = os.environ.get("GITHUB_SHA", "").strip()
    if not sha:
        return set()
    try:
        out = subprocess.check_output(
            ["git", "diff-tree", "--no-commit-id", "-r", "--name-only", sha],
            text=True,
        )
        return {line.strip() for line in out.splitlines() if line.strip()}
    except Exception:
        return set()


def rateb_erp_migration_files() -> list[str]:
    """ERP migration SQL must upload as raw bytes (UTF-8 Arabic breaks via save_file_content)."""
    out: list[str] = []
    for path in glob.glob("rateb-erp/migrations/*.sql"):
        if os.path.isfile(path):
            out.append(path.replace("\\", "/"))
    return sorted(set(out))


def should_rebaseline_erp_migrations(changed: set[str]) -> bool:
    needles = (
        "scripts/github-cpanel-fileman-deploy-core.py",
        "rateb-erp/app/services/MigrationService.php",
        "rateb-erp/migrations/",
    )
    return any(
        path == needle or path.startswith(needle)
        for path in changed
        for needle in needles
    )


def rateb_branded_asset_files() -> list[str]:
    """Always sync renamed rateb-* assets so fast deploy cannot leave stale ratib-* on server."""
    patterns = (
        "includes/rateb-*.php",
        "includes/rateb_*.php",
        "js/pages/rateb-*.js",
        "js/rateb-*.js",
        "css/pages/rateb-*.css",
        "css/rateb-*.css",
        "pages/rateb-*.php",
        "api/rateb-*.php",
        "api/core/rateb_*.inc.php",
        "api/diagnostics/rateb-*.php",
    )
    out: list[str] = []
    for pattern in patterns:
        for path in glob.glob(pattern):
            if os.path.isfile(path):
                out.append(path.replace("\\", "/"))
    return sorted(set(out))


def deployable_changed_paths() -> list[str]:
    """Changed paths we can upload, priority order (bootstrap before bulk api/)."""
    priority = (
        "config/env/",
        "config/env.php",
        "includes/",
        "pages/",
        "js/",
        "css/",
        "control-panel/",
        "public/",
        "rateb-erp/",
        "modules/",
        "api/",
    )

    def sort_key(path: str) -> tuple[int, int, str]:
        for idx, prefix in enumerate(priority):
            if path == prefix or path.startswith(prefix):
                return (0, idx, path)
        return (1, len(priority), path)

    paths = [
        p
        for p in git_changed_paths()
        if is_auto_deploy_path(p) and os.path.isfile(p)
    ]
    return sorted(paths, key=sort_key)


def _files_from_list_spec(spec: str) -> list[str]:
    paths: list[str] = []
    if os.path.isfile(spec):
        with open(spec, encoding="utf-8") as fh:
            for line in fh:
                line = line.strip()
                if line and not line.startswith("#"):
                    paths.append(line)
    else:
        for part in spec.replace(";", "\n").splitlines():
            part = part.strip()
            if part:
                paths.append(part)
    return [p for p in paths if os.path.isfile(p)]


def build_file_list(mode: str) -> tuple[list[str], int]:
    """Return ordered file list and parallel worker count."""
    list_spec = os.environ.get("CPANEL_DEPLOY_FILELIST", "").strip()
    if not list_spec and mode == "list":
        list_spec = "scripts/infra-deploy-23-files.list"
    if list_spec:
        return _files_from_list_spec(list_spec), 2

    if mode == "all":
        out = subprocess.check_output(
            [
                "find",
                ".",
                "-type",
                "f",
                "!",
                "-path",
                "./.git/*",
                "!",
                "-path",
                "./.github/*",
                "!",
                "-path",
                "./.cursor/*",
                "!",
                "-path",
                "./node_modules/*",
                "!",
                "-path",
                "./archive/*",
                "!",
                "-name",
                "*.md",
                "!",
                "-name",
                "*.map",
                "!",
                "-name",
                "*.log",
                "!",
                "-name",
                "*.zip",
                "-size",
                "-3M",
            ],
            text=True,
        )
        files = sorted({line[2:] for line in out.splitlines() if line.startswith("./")})
        return files, 3

    if mode == "critical":
        return list(CRITICAL), 3

    # fast (default on push): baseline FAST_FILES + CRITICAL + commit-changed paths
    marker = FAST_FILES[-1]
    core = [f for f in FAST_FILES if f != marker]
    seen = set(core)
    seen.add(marker)
    for rel in CRITICAL:
        if rel in seen or rel == marker:
            continue
        if os.path.isfile(rel):
            core.append(rel)
            seen.add(rel)
    for rel in rateb_branded_asset_files():
        if rel in seen or rel == marker:
            continue
        core.append(rel)
        seen.add(rel)
    acc_bundle = rateb_site_accounting_app_files()
    if acc_bundle:
        added_acc = 0
        for rel in acc_bundle:
            if rel in seen or rel == marker:
                continue
            core.append(rel)
            seen.add(rel)
            added_acc += 1
        if added_acc:
            print(
                f"fast deploy: +{added_acc} site app/Accounting file(s) (Control Center baseline)",
                flush=True,
            )
    ui_bundle = rateb_accounting_control_ui_files()
    if ui_bundle:
        added_ui = 0
        for rel in ui_bundle:
            if rel in seen or rel == marker:
                continue
            core.append(rel)
            seen.add(rel)
            added_ui += 1
        if added_ui:
            print(
                f"fast deploy: +{added_ui} accounting-control UI asset(s)",
                flush=True,
            )
    changed_paths = git_changed_paths()
    if should_rebaseline_erp_migrations(changed_paths):
        migration_files = rateb_erp_migration_files()
        print(
            f"fast deploy: rebaseline {len(migration_files)} ERP migration SQL files (binary UTF-8)",
            flush=True,
        )
        for rel in migration_files:
            if rel in seen or rel == marker:
                continue
            core.append(rel)
            seen.add(rel)
    extras: list[str] = []
    deployable = deployable_changed_paths()
    large_commit = len(deployable) > FAST_DEPLOY_LARGE_COMMIT_THRESHOLD
    if large_commit:
        print(
            f"fast deploy: large commit ({len(deployable)} deployable paths) — uploading all",
            flush=True,
        )
    for path in deployable:
        if path in seen:
            continue
        extras.append(path)
        seen.add(path)
        if not large_commit and len(extras) >= FAST_DEPLOY_CHANGED_CAP:
            break
    if needs_full_erp_bundle(set(deployable)):
        bundle = rateb_erp_bundle_files() + rateb_erp_control_panel_files()
        added = 0
        for path in bundle:
            if path in seen:
                continue
            extras.append(path)
            seen.add(path)
            added += 1
        if added:
            print(f"fast deploy: +{added} rateb-erp bundle file(s)", flush=True)
    if needs_full_rcc_bundle(set(deployable)) or os.path.isdir(
        os.path.join(os.getcwd(), "ratib-contact-center")
    ):
        bundle = ratib_contact_center_bundle_files() + ratib_cc_control_panel_files()
        added = 0
        for path in bundle:
            if path in seen:
                continue
            extras.append(path)
            seen.add(path)
            added += 1
        if added:
            print(f"fast deploy: +{added} ratib-contact-center bundle file(s)", flush=True)
    if os.path.isdir(os.path.join(os.getcwd(), "rateb-platform-catalog")):
        bundle = rateb_platform_catalog_bundle_files()
        added = 0
        for path in bundle:
            if path in seen:
                continue
            extras.append(path)
            seen.add(path)
            added += 1
        if added:
            print(f"fast deploy: +{added} rateb-platform-catalog bundle file(s)", flush=True)
    files = core + extras + [marker]
    if extras:
        print(
            f"fast deploy: +{len(extras)} commit-changed file(s): {', '.join(extras[:8])}"
            + (" …" if len(extras) > 8 else ""),
            flush=True,
        )
    return files, 2


def _upload_batch(
    rels: list[str],
    remote_base: str,
    workers: int,
    total: int,
    start_done: int,
) -> tuple[int, int, set[str], int]:
    """Upload a batch; return ok, fail, succeeded, done counter."""
    ok = 0
    fail = 0
    succeeded: set[str] = set()
    done = start_done
    if not rels:
        return ok, fail, succeeded, done

    if workers <= 1 or len(rels) == 1:
        for rel in rels:
            done += 1
            pct = done * 100 // total if total else 100
            print(f"[{done}/{total}] {pct}% upload {rel} ... ", end="", flush=True)
            _, success, err = upload_one(rel, remote_base)
            if success:
                print("OK", flush=True)
                ok += 1
                succeeded.add(rel)
            else:
                print("FAIL", flush=True)
                fail += 1
                if err:
                    print(err[:200], flush=True)
        return ok, fail, succeeded, done

    with ThreadPoolExecutor(max_workers=workers) as pool:
        futures = [pool.submit(upload_one, rel, remote_base) for rel in rels]
        for fut in as_completed(futures):
            rel, success, err = fut.result()
            done += 1
            pct = done * 100 // total if total else 100
            line = f"[{done}/{total}] {pct}% upload {rel} ... {'OK' if success else 'FAIL'}"
            with _print_lock:
                print(line, flush=True)
                if not success and err:
                    print(err[:200], flush=True)
            if success:
                ok += 1
                succeeded.add(rel)
            else:
                fail += 1
    return ok, fail, succeeded, done


def run_uploads(files: list[str], remote_base: str, workers: int) -> tuple[int, int, set[str]]:
    total = len(files)
    ok = 0
    fail = 0
    succeeded: set[str] = set()
    done = 0

    existing = sorted(
        [f for f in files if os.path.isfile(f)],
        key=lambda p: p.count("/"),
    )
    for rel in files:
        if rel in existing:
            continue
        done += 1
        pct = done * 100 // total if total else 100
        print(f"[{done}/{total}] {pct}% SKIP missing {rel}", flush=True)

    if not existing:
        return ok, fail, succeeded

    remote_dirs = sorted({remote_dir(remote_base, rel) for rel in existing}, key=lambda d: d.count("/"))
    for d in remote_dirs:
        ensure_remote_dir(d, remote_base)

    binary_rels = [rel for rel in existing if is_binary_upload(rel)]
    text_rels = [rel for rel in existing if not is_binary_upload(rel)]
    text_workers = max(workers, 2)

    b_ok, b_fail, b_suc, done = _upload_batch(binary_rels, remote_base, 1, total, done)
    ok += b_ok
    fail += b_fail
    succeeded |= b_suc

    t_ok, t_fail, t_suc, done = _upload_batch(text_rels, remote_base, text_workers, total, done)
    ok += t_ok
    fail += t_fail
    succeeded |= t_suc
    return ok, fail, succeeded


def purge_security_retired_files(remote_base: str) -> None:
    print(
        f"security purge: removing {len(SECURITY_REMOTE_DELETE_FILES)} retired HTTP script(s)",
        flush=True,
    )
    for rel in SECURITY_REMOTE_DELETE_FILES:
        abs_dir = remote_dir(remote_base, rel)
        remote_path = fileman_home_rel(abs_dir, os.path.basename(rel))
        api2_fileop_unlink(remote_path, require_ok=False)


def purge_aborted_extraction_files(remote_base: str) -> None:
    print(
        f"extraction rollback purge: removing "
        f"{len(EXTRACTION_ROLLBACK_REMOTE_DELETE_FILES)} aborted shared file(s)",
        flush=True,
    )
    for rel in EXTRACTION_ROLLBACK_REMOTE_DELETE_FILES:
        abs_dir = remote_dir(remote_base, rel)
        remote_path = fileman_home_rel(abs_dir, os.path.basename(rel))
        # Absence is OK (already gone); API soft-fail allowed, integrity SSH gate verifies.
        api2_fileop_unlink(remote_path, require_ok=False)


def purge_commit_deleted_files(remote_base: str) -> list[str]:
    """Delete paths removed in the current commit (rollback-safe for that SHA)."""
    try:
        from deploy_integrity import git_deleted_paths_for_sha, git_sha
    except ImportError:
        here = os.path.dirname(os.path.abspath(__file__))
        if here not in sys.path:
            sys.path.insert(0, here)
        from deploy_integrity import git_deleted_paths_for_sha, git_sha

    deleted = git_deleted_paths_for_sha(git_sha())
    print(f"commit-deleted purge: {len(deleted)} path(s)", flush=True)
    for rel in deleted:
        if not is_auto_deploy_path(rel) and not rel.startswith("rateb-erp/public/"):
            # Still purge managed public orphans introduced by reverted commits.
            if not any(rel.startswith(p) for p in (
                "rateb-erp/public/", "public/", "js/", "css/", "pages/", "api/", "includes/"
            )):
                continue
        abs_dir = remote_dir(remote_base, rel)
        remote_path = fileman_home_rel(abs_dir, os.path.basename(rel))
        api2_fileop_unlink(remote_path, require_ok=False)
    return deleted


def run_px_deploy_integrity_gate(remote_base: str) -> int:
    """
    Mandatory PX-Deploy integrity. Requires SSH credentials (same as rsync path).
    Fails the deployment if orphans remain or tree hashes diverge.
    """
    here = os.path.dirname(os.path.abspath(__file__))
    if here not in sys.path:
        sys.path.insert(0, here)
    import deploy_integrity as integrity
    import stat as statmod
    import tempfile

    creds = integrity.ssh_credentials_from_env()
    if not creds:
        print(
            "::error::PX-Deploy integrity requires DEPLOY_SSH_HOST/USER/PRIVATE_KEY "
            "on every backend (rsync and Fileman)",
            flush=True,
        )
        return 1
    host, user, port = creds
    key = (
        os.environ.get("DEPLOY_SSH_PRIVATE_KEY")
        or os.environ.get("SSH_PRIVATE_KEY")
        or ""
    ).strip()
    if "\\n" in key and "\n" not in key:
        key = key.replace("\\n", "\n")
    fd, key_path = tempfile.mkstemp(prefix="rateb-fileman-integrity-", suffix=".pem")
    os.close(fd)
    with open(key_path, "w", encoding="utf-8", newline="\n") as handle:
        handle.write(key)
        if not key.endswith("\n"):
            handle.write("\n")
    os.chmod(key_path, statmod.S_IRUSR | statmod.S_IWUSR)
    try:
        # This module is the core; pass self for purge lists.
        import sys as _sys
        core = _sys.modules[__name__]
        expected = integrity.build_expected_manifest()
        force = integrity.explicit_purge_paths(core)
        print(
            f"PX-Deploy integrity (Fileman backend): expected={len(expected)} "
            f"force_purge={len(force)} dest={remote_base}",
            flush=True,
        )
        report = integrity.run_ssh_integrity(
            key_path=key_path,
            remote_base=remote_base.rstrip("/"),
            host=host,
            user=user,
            port=port,
            expected=expected,
            force_delete=force,
        )
        heal = sorted(set(report.missing_expected) | {
            row.get("path", "") for row in report.hash_mismatches if row.get("path")
        })
        heal = [p for p in heal if p and os.path.isfile(p)]
        if heal and (report.missing_expected or report.hash_mismatches):
            print(f"PX-Deploy integrity heal upload (Fileman): {len(heal)} file(s)", flush=True)
            for rel in heal:
                upload_one(rel, remote_base)
            report = integrity.run_ssh_integrity(
                key_path=key_path,
                remote_base=remote_base.rstrip("/"),
                host=host,
                user=user,
                port=port,
                expected=expected,
                force_delete=force,
            )
        integrity.write_report(report)
        if not report.ok:
            print("::error::PX-Deploy integrity failed on Fileman backend", flush=True)
            for err in report.errors[:20]:
                print(f"integrity_error: {err}", flush=True)
            return 1
        print(
            "PX-Deploy integrity PASS "
            f"tree_hash={report.local_tree_hash} "
            f"orphans_removed={len(report.orphans_found)}",
            flush=True,
        )
        return 0
    finally:
        try:
            os.remove(key_path)
        except OSError:
            pass


def main() -> int:
    root = os.path.dirname(os.path.abspath(__file__))
    os.chdir(os.path.join(root, ".."))
    remote_base = os.environ.get("CPANEL_REMOTE_BASE", "/home/admin/public_html")
    mode = os.environ.get("CPANEL_DEPLOY_MODE", "fast")
    files, workers = build_file_list(mode)
    total = len(files)

    print(
        f"deploy mode={mode} files={total} parallel={workers} dest={remote_base}",
        flush=True,
    )
    purge_security_retired_files(remote_base)
    purge_aborted_extraction_files(remote_base)
    purge_commit_deleted_files(remote_base)
    ok, fail, succeeded = run_uploads(files, remote_base, workers)
    print(
        f"\n========== Summary: ok={ok} fail={fail} total={total} "
        f"({(ok * 100 // total) if total else 0}% success) ==========",
        flush=True,
    )
    # Only verify MUST_OK for paths in this run's upload list (not every file on disk).
    # After trimming FAST_FILES, requiring all MUST_OK every push caused exit 1 even when
    # ok=total (e.g. cms-media.php not in the 8-file baseline).
    must_check = [m for m in MUST_OK if m in files]
    need = len(must_check)
    must_hit = sum(1 for m in must_check if m in succeeded)
    if fail > 0:
        return 1
    if need > 0 and must_hit < need:
        missing = [m for m in must_check if m not in succeeded]
        print(
            f"MUST_OK gate failed: {must_hit}/{need} required uploads OK; missing: {', '.join(missing)}",
            flush=True,
        )
        return 1

    mirror_test_rateb_entry_files(succeeded)
    gate = run_px_deploy_integrity_gate(remote_base)
    if gate == 0:
        bust_production_opcache()
    return gate


def bust_production_opcache() -> None:
    """Invalidate PHP opcache so freshly uploaded rateb-erp/public/*.php is live.

    Production often runs with opcache.validate_timestamps=0; Fileman uploads then
    leave stale bytecode until reset (device-registry login fix depended on this).
    """
    url = (
        os.environ.get("RATEB_OPCACH_BUST_URL")
        or "https://rateb.sa/rateb-erp/public/erp-opcache-bust.php"
    ).strip()
    if not url:
        return
    try:
        req = urllib.request.Request(
            url,
            method="GET",
            headers={"User-Agent": "rateb-deploy-opcache-bust"},
        )
        with urllib.request.urlopen(req, timeout=45) as resp:
            body = resp.read().decode("utf-8", "replace").strip().replace("\n", " | ")
        print(f"opcache bust OK: {body[:300]}", flush=True)
    except Exception as exc:
        print(f"::warning::opcache bust failed: {exc}", flush=True)


def mirror_test_rateb_entry_files(succeeded: set[str]) -> None:
    """
    Fast deploy targets rateb.sa public_html only. test.rateb.sa is a separate tree;
    mirror entry files so agency root (/) does not keep a stale public/index.php (HTTP 500).
    """
    del succeeded  # reserved for future selective mirrors
    test_base = os.environ.get(
        "CPANEL_TEST_REMOTE_BASE",
        "/home/admin/domains/test.rateb.sa/public_html",
    ).rstrip("/")
    remote_main = os.environ.get("CPANEL_REMOTE_BASE", "/home/admin/public_html").rstrip("/")
    if not test_base or test_base == remote_main:
        return
    mirror = [
        ".htaccess",
        "index.php",
        "public/index.php",
        "includes/designed_bootstrap.php",
        "config/env/test_rateb_sa.php",
        "config/env/agency_lookup.php",
        "config/env/control_db_for_lookup.php",
    ]
    # Keep subscription in-app alert path current on the agency test tree.
    sub_root = "rateb-erp/modules/subscription"
    if os.path.isdir(sub_root):
        for dirpath, _dirnames, filenames in os.walk(sub_root):
            for name in filenames:
                if name.endswith(".php") or name.endswith(".md"):
                    rel = os.path.join(dirpath, name).replace("\\", "/")
                    mirror.append(rel)
    for extra in (
        "rateb-erp/views/layouts/main.php",
        "rateb-erp/views/partials/subscription-alert.php",
        "rateb-erp/views/components/flash.php",
        "rateb-erp/public/index.php",
        "rateb-erp/modules/subscription/SubscriptionAlertService.php",
        "rateb-erp/modules/subscription/views/alert-banner.php",
        "rateb-erp/app/Core/Middleware/Middleware.php",
        "rateb-erp/config/lang/ar.php",
        "rateb-erp/config/lang/en.php",
    ):
        if extra not in mirror:
            mirror.append(extra)
    ordered = [rel for rel in mirror if os.path.isfile(rel)]
    if not ordered:
        return
    print(f"test.rateb.sa mirror: uploading {len(ordered)} entry file(s) → {test_base}", flush=True)
    ok, fail, _ = run_uploads(ordered, test_base, max(2, min(4, len(ordered))))
    print(f"test.rateb.sa mirror: ok={ok} fail={fail}", flush=True)
    if fail > 0:
        print(
            "::warning::test.rateb.sa entry mirror had failures — run Control Panel → Sync test domain",
            flush=True,
        )


if __name__ == "__main__":
    sys.exit(main())
