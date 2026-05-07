<?php
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

requireControlPermission(
    CONTROL_PERM_SYSTEM_SETTINGS,
    'view_control_system_settings',
    CONTROL_PERM_DASHBOARD
);

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Tenants & Rollout', ['css/control/tenant-rollout.css'], []);
?>

<section id="tenantRolloutPage" class="tenant-rollout-page">
    <div class="tenant-rollout-hero">
        <h3 class="tenant-rollout-title"><i class="fas fa-diagram-project me-2"></i>Global Multi-Agency Rollout Plan</h3>
        <p class="tenant-rollout-text">
            Use this page as the single reference for deploying one Ratib Pro update across all agencies, with country-specific requirements handled by feature flags.
        </p>
        <div class="tenant-rollout-actions">
            <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(control_panel_page_with_control('control/dashboard.php'), ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
            </a>
            <button type="button" id="copyRolloutLinkBtn" class="btn btn-sm btn-primary">
                <i class="fas fa-link me-1"></i>Copy This Page Link
            </button>
        </div>
        <div id="tenantRolloutFlash" class="tenant-rollout-flash d-none" role="status" aria-live="polite"></div>
    </div>

    <div class="tenant-rollout-grid">
        <article class="tenant-rollout-card">
            <h4>Phase 1 - Tenant Registry</h4>
            <p>Store tenant identity and routing data in one control table to avoid hardcoded per-country links.</p>
            <ul>
                <li>Required: <code>tenant_id</code>, <code>country_code</code>, <code>domain</code>, <code>db_key</code>, <code>status</code></li>
                <li>Route incoming domain to one agency tenant before opening DB connection</li>
                <li>Keep tenant records active/inactive for safe maintenance windows</li>
            </ul>
            <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/agencies.php'), ENT_QUOTES, 'UTF-8'); ?>" class="tenant-rollout-link">Open Manage Agencies</a>
        </article>

        <article class="tenant-rollout-card">
            <h4>Phase 2 - Feature Flags</h4>
            <p>Release one app version globally while enabling specific requirements per country or tenant.</p>
            <ul>
                <li>Global defaults, then country overrides, then tenant overrides</li>
                <li>Roll out features by wave: Canary, Wave 1, Wave 2, Full</li>
                <li>Disable flag instantly to stop risky behavior without rollback</li>
            </ul>
            <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-profiles.php'), ENT_QUOTES, 'UTF-8'); ?>" class="tenant-rollout-link">Open Country Profiles</a>
        </article>

        <article class="tenant-rollout-card">
            <h4>Phase 3 - Migration Pipeline</h4>
            <p>Apply schema updates in controlled batches across all tenant databases.</p>
            <ul>
                <li>Use idempotent versioned migrations only</li>
                <li>Run migrations in batches with retry and per-tenant logs</li>
                <li>Pause or resume safely on partial failures</li>
            </ul>
            <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/system-settings.php'), ENT_QUOTES, 'UTF-8'); ?>" class="tenant-rollout-link">Open System Settings</a>
        </article>

        <article class="tenant-rollout-card">
            <h4>Phase 4 - Observability and Recovery</h4>
            <p>Track rollout status and isolate incidents quickly by country, region, and tenant.</p>
            <ul>
                <li>Monitor errors, latency, and failed tenant checks per wave</li>
                <li>Auto-stop rollout when failure threshold is exceeded</li>
                <li>Keep rollback path documented for app and migration changes</li>
            </ul>
            <a href="<?php echo htmlspecialchars(control_panel_page_with_control('control/support-chats.php'), ENT_QUOTES, 'UTF-8'); ?>" class="tenant-rollout-link">Open Support Chats</a>
        </article>
    </div>

    <div class="tenant-rollout-url-box">
        <h4>Browser Access</h4>
        <p>
            Open this page from the Control Panel menu:
            <strong>Administration -> Tenants &amp; Rollout</strong>
        </p>
        <p class="tenant-rollout-url-label">Direct URL:</p>
        <code id="tenantRolloutDirectUrl"><?php echo htmlspecialchars(control_panel_page_with_control('control/tenant-rollout.php'), ENT_QUOTES, 'UTF-8'); ?></code>
    </div>
</section>

<?php endControlLayout(['js/control/tenant-rollout.js']); ?>
