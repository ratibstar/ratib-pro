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
startControlLayout('Worker Mobile Onboarding', ['css/control/government.css', 'css/control/tracking-onboarding.css'], []);
?>
<div id="tracking-onboarding-page">
    <p class="text-muted tracking-onb-lead">Generate QR credentials for worker mobile app onboarding.</p>
    <div class="card gov-card">
        <div class="card-body">
            <div class="row g-2 align-items-end gov-form">
                <div class="col-md-3">
                    <label class="form-label visually-hidden" for="onbWorkerId">Worker ID or name</label>
                    <input id="onbWorkerId" name="onb_worker_id" class="form-control form-control-sm" type="text" autocomplete="off" placeholder="Worker ID / code / name (e.g. 2, W0002, Ahmed)">
                </div>
                <div class="col-md-3">
                    <label class="form-label visually-hidden" for="onbTenantId">Tenant ID</label>
                    <input id="onbTenantId" name="onb_tenant_id" class="form-control form-control-sm" type="number" placeholder="Tenant ID (optional)">
                </div>
                <div class="col-md-3">
                    <label class="form-label visually-hidden" for="onbDeviceId">Device ID</label>
                    <input id="onbDeviceId" name="onb_device_id" class="form-control form-control-sm" type="text" autocomplete="off" placeholder="Device ID (optional)">
                </div>
                <div class="col-md-3">
                    <label class="form-label visually-hidden" for="onbIdentity">Identity</label>
                    <input id="onbIdentity" name="onb_identity" class="form-control form-control-sm" type="text" autocomplete="off" placeholder="Identity (optional)">
                </div>
            </div>
            <div class="row g-2 mt-2 align-items-end gov-form">
                <div class="col-md-3">
                    <label class="form-label visually-hidden" for="onbPassword">Password</label>
                    <input id="onbPassword" name="onb_password" class="form-control form-control-sm" type="password" autocomplete="new-password" placeholder="Password (optional)">
                </div>
                <div class="col-md-3">
                    <button id="onbGenerateBtn" type="button" class="btn btn-sm btn-primary w-100">Generate QR</button>
                </div>
            </div>
            <div id="onbQr" class="onb-qr mt-2"></div>
        </div>
    </div>
    <div id="onbFlash" class="alert d-none mt-2" role="alert"></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<?php
endControlLayout(['js/control/tracking-onboarding.js']);
