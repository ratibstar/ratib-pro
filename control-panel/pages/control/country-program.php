<?php
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
require_once __DIR__ . '/../../includes/control/country-program-scope.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
requireControlPermission(CONTROL_PERM_GOVERNMENT, 'view_control_government', 'manage_control_government', 'gov_admin', CONTROL_PERM_ADMINS);

$ctrl = $GLOBALS['control_conn'] ?? null;
$sessCountry = ($ctrl instanceof mysqli) ? control_country_program_session_country_row($ctrl) : null;
$scopeSlugs = ($ctrl instanceof mysqli) ? control_country_program_allowed_slugs($ctrl) : null;
$allCountries = control_country_program_can_operate_all_countries();

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Country program', ['css/control/government.css'], []);
?>
<div id="country-program-page">
    <div class="card gov-card mb-3">
        <div class="card-body py-3">
            <p class="mb-2 country-program-lead text-muted">Operate tracking, government checks, and worker rules only inside your assigned countries — sessions stay scoped so teams do not overwrite each other.</p>
            <?php if ($sessCountry): ?>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <span class="badge bg-primary">Active context</span>
                    <strong><?php echo htmlspecialchars($sessCountry['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span class="text-muted small"><?php echo htmlspecialchars($sessCountry['slug'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php else: ?>
                <div class="alert alert-secondary py-2 mb-0 small">No country is pinned in this session. Use <strong>Select Country</strong> / choose an agency so filters apply to one program.</div>
            <?php endif; ?>
            <?php if (!$allCountries && is_array($scopeSlugs)): ?>
                <div class="small text-muted mt-2">Your account is limited to: <strong><?php echo htmlspecialchars(implode(', ', $scopeSlugs), ENT_QUOTES, 'UTF-8'); ?></strong></div>
            <?php elseif ($allCountries): ?>
                <div class="small text-muted mt-2">You may switch countries via <strong>Select Country</strong>; APIs still respect the country filters you choose.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card gov-card h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-shield-halved me-2"></i>Government Control</h6>
                    <p class="small text-muted mb-3">Inspections and worker checks for agencies in the active country scope.</p>
                    <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(control_panel_page_with_control('control/government.php')); ?>">Open</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card gov-card h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-map-location-dot me-2"></i>Tracking Map</h6>
                    <p class="small text-muted mb-3">Live sessions filtered to your allowed countries (country operators cannot query other states).</p>
                    <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-map.php')); ?>">Open</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card gov-card h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-heart-pulse me-2"></i>Tracking Health</h6>
                    <p class="small text-muted mb-3">Session/device overview for scoped tenants.</p>
                    <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-health.php')); ?>">Open</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card gov-card h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-qrcode me-2"></i>Tracking Onboarding</h6>
                    <p class="small text-muted mb-3">QR onboarding credentials (manage permission required).</p>
                    <?php if (hasControlPermission('manage_control_government') || hasControlPermission('gov_admin') || hasControlPermission(CONTROL_PERM_ADMINS)): ?>
                        <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(control_panel_page_with_control('control/tracking-onboarding.php')); ?>">Open</a>
                    <?php else: ?>
                        <span class="small text-muted">Requires manage government access</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php if (control_country_profiles_can_edit($ctrl instanceof mysqli ? $ctrl : null)): ?>
        <div class="col-md-6">
            <div class="card gov-card h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-sliders me-2"></i>Country Profiles</h6>
                    <p class="small text-muted mb-3">Worker labels and required fields — scoped editors only see their countries.</p>
                    <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(control_panel_page_with_control('control/country-profiles.php')); ?>">Open</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="col-md-6">
            <div class="card gov-card h-100">
                <div class="card-body">
                    <h6 class="card-title"><i class="fas fa-globe me-2"></i>Select Country</h6>
                    <p class="small text-muted mb-3">Switch program context (respects your country permissions).</p>
                    <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(pageUrl('select-country.php')); ?>">Open</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endControlLayout([]); ?>
