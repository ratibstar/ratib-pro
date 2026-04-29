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

requireControlPermission(CONTROL_PERM_DASHBOARD);

require_once __DIR__ . '/../../includes/control/request-url.php';
$socCssUrl = function_exists('control_ratib_pro_asset_url')
    ? control_ratib_pro_asset_url('admin/assets/dashboard.css')
    : (rtrim(control_ratib_pro_public_base_url(), '/') . '/admin/assets/dashboard.css?v=' . time());
$socJsUrl = function_exists('control_ratib_pro_asset_url')
    ? control_ratib_pro_asset_url('admin/assets/dashboard.js')
    : (rtrim(control_ratib_pro_public_base_url(), '/') . '/admin/assets/dashboard.js?v=' . time());

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('SOC Dashboard', [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    $socCssUrl,
], [
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
    // Keep wrapper JS list minimal; SOC JS is injected explicitly below.
]);
?>
<div class="soc-shell">
    <header class="soc-header panel">
        <div>
            <h1>SOC Real-time Control Dashboard</h1>
            <p class="sub">Frontend-only monitoring · local telemetry driven</p>
        </div>
        <div class="head-controls">
            <input id="workerSearch" class="input" type="search" placeholder="Search worker by name/id/identity/device">
            <select id="statusFilter" class="input">
                <option value="ALL">All status</option>
                <option value="GOOD">GOOD</option>
                <option value="LIMITED">LIMITED</option>
                <option value="POOR">POOR</option>
            </select>
            <button id="platformAll" class="btn" type="button" onclick="if(window.SOCDashboardControls){window.SOCDashboardControls.setPlatform('ALL');}">All</button>
            <button id="platformAndroid" class="btn" type="button" onclick="if(window.SOCDashboardControls){window.SOCDashboardControls.setPlatform('ANDROID');}">Android</button>
            <button id="platformIOS" class="btn" type="button" onclick="if(window.SOCDashboardControls){window.SOCDashboardControls.setPlatform('IOS');}">iOS</button>
            <button id="focusToggle" class="btn" type="button" onclick="if(window.SOCDashboardControls){window.SOCDashboardControls.toggleFocus();}">Focus: OFF</button>
        </div>
    </header>

    <section class="top-stats" id="topStats">
        <article class="stat panel"><span>Total workers</span><strong id="sTotal">0</strong></article>
        <article class="stat panel"><span>% GOOD</span><strong id="sGood">0%</strong></article>
        <article class="stat panel"><span>% LIMITED</span><strong id="sLimited">0%</strong></article>
        <article class="stat panel"><span>% POOR</span><strong id="sPoor">0%</strong></article>
        <article class="stat panel"><span>Recoveries</span><strong id="sRecoveries">0</strong></article>
        <article class="stat panel"><span>Prediction triggers</span><strong id="sPredictions">0</strong></article>
        <article class="stat panel"><span>Threat indicator</span><strong id="sThreat" class="risk-low">LOW</strong></article>
    </section>

    <section class="soc-main">
        <aside class="panel workers-panel">
            <div class="panel-head">
                <h2>Workers</h2>
                <span class="small" id="workersStamp">-</span>
            </div>
            <div class="worker-list" id="workersList"></div>
        </aside>

        <div class="panel map-panel">
            <div id="map"></div>
            <div class="details" id="workerDetails">
                <div class="small">Select worker from map/list.</div>
            </div>
        </div>
    </section>

    <section class="panel alerts-panel">
        <div class="panel-head">
            <h2>Live Alerts Stream</h2>
            <span class="small">INFO / WARNING / CRITICAL</span>
        </div>
        <ul class="alerts-list" id="alertsList"></ul>
    </section>
</div>
<script src="<?php echo htmlspecialchars($socJsUrl, ENT_QUOTES, 'UTF-8'); ?>"></script>
<?php endControlLayout(); ?>
