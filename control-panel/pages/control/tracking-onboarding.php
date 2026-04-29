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
requireControlPermission(CONTROL_PERM_GOVERNMENT, 'manage_control_government', 'gov_admin', CONTROL_PERM_ADMINS);

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Worker Mobile Onboarding', ['css/control/tracking-onboarding.css'], []);
?>
<div id="tracking-onboarding-page">
    <p class="text-muted">Generate QR credentials for worker mobile app onboarding.</p>
    <div class="card gov-card">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-3"><input id="onbWorkerId" class="form-control form-control-sm" type="text" placeholder="Worker ID / code / name (e.g. 2, W0002, Ahmed)"></div>
                <div class="col-md-3"><input id="onbTenantId" class="form-control form-control-sm" type="number" placeholder="Tenant ID (optional)"></div>
                <div class="col-md-3"><input id="onbDeviceId" class="form-control form-control-sm" type="text" placeholder="Device ID (optional)"></div>
                <div class="col-md-3"><input id="onbIdentity" class="form-control form-control-sm" type="text" placeholder="Identity (optional)"></div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-3"><input id="onbPassword" class="form-control form-control-sm" type="password" placeholder="Password (optional)"></div>
                <div class="col-md-3"><button id="onbGenerateBtn" type="button" class="btn btn-sm btn-primary">Generate QR</button></div>
            </div>
            <div id="onbQr" class="onb-qr mt-2"></div>
        </div>
    </div>
    <div id="onbFlash" class="alert d-none mt-2" role="alert"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<?php
endControlLayout(['js/control/tracking-onboarding.js']);
