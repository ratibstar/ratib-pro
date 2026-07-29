<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'services';
$RCP_HEADING = 'Services';
$RCP_SUBHEADING = 'Service lifecycle, provisioning visibility, renewals, and active resources inside Client Hub.';
require __DIR__ . '/_common-start.inc.php';

$clientErpAgencyId = (int) ($_GET['agency_id'] ?? ($_SESSION['control_agency_id'] ?? ($_SESSION['agency_id'] ?? 0)));
$clientErpAgency = null;
$clientErpApiBase = '';
if ($clientErpAgencyId > 0) {
    $agencyLookup = dirname(__DIR__, 2) . '/config/env/agency_lookup.php';
    if (is_file($agencyLookup)) {
        require_once $agencyLookup;
        $clientErpAgency = rateb_lookup_agency_by_id($clientErpAgencyId);
    }
}
if (function_exists('getBaseUrl')) {
    $clientErpApiBase = rtrim((string) getBaseUrl(), '/') . '/api/control';
}
if ($clientErpApiBase === '' && isset($_SERVER['HTTP_HOST'])) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $clientErpApiBase = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/control-panel/api/control';
}
$clientErpStatus = is_array($clientErpAgency) ? (string) ($clientErpAgency['erp_status'] ?? 'none') : 'none';
$clientErpPlan = is_array($clientErpAgency) ? (string) ($clientErpAgency['erp_plan_slug'] ?? 'professional') : 'professional';
$clientErpDb = is_array($clientErpAgency) ? (string) ($clientErpAgency['erp_db_name'] ?? '') : '';
$legacyServicesQuery = [
    'embed' => '1',
    'compatibility' => '1',
];
$legacyServicesAgencyId = (int) ($_SESSION['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
if (function_exists('rateb_control_pro_bridge') && rateb_control_pro_bridge() && $legacyServicesAgencyId > 0) {
    $legacyServicesQuery['control'] = '1';
    $legacyServicesQuery['agency_id'] = (string) $legacyServicesAgencyId;
}
$legacyServicesSrc = htmlspecialchars(
    rateb_client_dashboard_public_site_base_url() . '/modules/infrastructure-marketplace/Views/client/services.php?' . http_build_query($legacyServicesQuery),
    ENT_QUOTES,
    'UTF-8'
);
$catalogHref = htmlspecialchars(rateb_client_dashboard_context_url('domains.php', 'catalog=1'), ENT_QUOTES, 'UTF-8');
?>
            <div class="rateb-cp-board">
                <?php if ($clientErpAgencyId > 0 && is_array($clientErpAgency)) { ?>
                <section id="client-erp-provision" class="rateb-cp-card mb-4" data-api-base="<?php echo htmlspecialchars($clientErpApiBase, ENT_QUOTES, 'UTF-8'); ?>" data-agency-id="<?php echo (int) $clientErpAgencyId; ?>">
                    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                        <div>
                            <h2>RATEB ERP</h2>
                            <p class="rcp-note mb-1">Dedicated ERP database for <strong><?php echo htmlspecialchars((string) ($clientErpAgency['name'] ?? 'Agency'), ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
                            <p class="rcp-note mb-0">Status: <span class="rateb-status <?php echo $clientErpStatus === 'ready' ? 'rateb-status--active' : 'rateb-status--pending'; ?>"><?php echo htmlspecialchars($clientErpStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php if ($clientErpDb !== '') { ?> · DB <code><?php echo htmlspecialchars($clientErpDb, ENT_QUOTES, 'UTF-8'); ?></code><?php } ?>
                            </p>
                        </div>
                        <?php if ($clientErpStatus === 'ready' && !empty($clientErpAgency['site_url'])) {
                            $clientErpLogin = function_exists('rateb_agency_erp_login_url')
                                ? rateb_agency_erp_login_url((string) $clientErpAgency['site_url'])
                                : (rtrim((string) $clientErpAgency['site_url'], '/') . '/rateb-erp/public/login');
                        ?>
                        <a class="rateb-cp-pillbtn" href="<?php echo htmlspecialchars($clientErpLogin, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Open ERP login</a>
                        <?php } ?>
                    </div>
                    <?php if ($clientErpStatus !== 'ready') { ?>
                    <div class="row g-3 align-items-end mt-2">
                        <div class="col-md-5">
                            <label class="form-label" for="clientErpPlanSelect">ERP package</label>
                            <select class="form-select" id="clientErpPlanSelect">
                                <option value="starter"<?php echo $clientErpPlan === 'starter' ? ' selected' : ''; ?>>Starter</option>
                                <option value="professional"<?php echo $clientErpPlan === 'professional' ? ' selected' : ''; ?>>Professional</option>
                                <option value="enterprise"<?php echo $clientErpPlan === 'enterprise' ? ' selected' : ''; ?>>Enterprise</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-primary" id="clientErpProvisionBtn">Provision ERP database</button>
                        </div>
                    </div>
                    <p id="clientErpProvisionMsg" class="rcp-note mt-3 mb-0" hidden></p>
                    <?php } else { ?>
                    <p class="rcp-note mt-3 mb-0">Package: <strong><?php echo htmlspecialchars($clientErpPlan, ENT_QUOTES, 'UTF-8'); ?></strong> · one company per database.</p>
                    <?php } ?>
                </section>
                <?php } ?>
                <section id="client-service-lifecycle" class="rateb-cp-card mb-4">
                    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                        <div>
                            <h2>Service lifecycle</h2>
                            <p class="rcp-note mb-0">Canonical service visibility now lives in Client Hub while the infrastructure module remains the internal capability layer.</p>
                        </div>
                        <a class="rateb-cp-pillbtn" href="<?php echo $catalogHref; ?>">Browse plans &amp; domains</a>
                    </div>
                    <div class="mt-3" style="border:1px solid rgba(255,255,255,.08);border-radius:18px;overflow:hidden;background:rgba(5,10,24,.45);">
                        <iframe
                            src="<?php echo $legacyServicesSrc; ?>"
                            title="Client services lifecycle"
                            loading="lazy"
                            referrerpolicy="same-origin"
                            style="width:100%;min-height:420px;border:0;display:block;background:transparent;"
                        ></iframe>
                    </div>
                </section>
                <div class="rateb-cp-metrics" role="list">
                    <?php
                    $cards = [
                        ['Shared hosting', 'healthy', '92% seat utilisation', 'renew in 24d'],
                        ['VPS / Cloud', 'degraded', 'CPU 68% · RAM 74%', 'live resize ready'],
                        ['Domains', 'healthy', '3 zones monitored', '2 expiring < 30d'],
                        ['Email &amp; collaboration', 'healthy', 'SPF/DKIM aligned', 'no queue backlog'],
                        ['SSL &amp; security', 'healthy', 'Auto-renew on', 'HSTS enforced'],
                    ];
                    foreach ($cards as $c) {
                        ?>
                        <section class="rateb-cp-card" role="listitem" aria-label="<?php echo htmlspecialchars($c[0], ENT_QUOTES, 'UTF-8'); ?>">
                            <h2><?php echo htmlspecialchars($c[0], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="rateb-status <?php echo $c[1] === 'healthy' ? 'rateb-status--active' : 'rateb-status--pending'; ?>">
                                    <?php echo htmlspecialchars($c[1], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="rcp-muted-span">Uptime 99.98%</span>
                            </div>
                            <p class="rcp-note mb-1"><?php echo htmlspecialchars($c[2], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="rcp-note"><?php echo htmlspecialchars($c[3], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="rateb-cp-quick mt-3" role="group">
                                <button type="button" onclick="RATEBClientActions.restart('svc-demo')">Restart</button>
                                <button type="button" class="secondary" onclick="RATEBClientActions.suspend('svc-demo')">Suspend</button>
                                <button type="button" class="secondary" onclick="RATEBClientActions.upgrade('svc-demo')">Upgrade</button>
                            </div>
                        </section>
                        <?php
                    }
                    ?>
                </div>
            </div>
<?php if ($clientErpAgencyId > 0 && is_array($clientErpAgency) && $clientErpStatus !== 'ready') { ?>
<script>
(function () {
    var root = document.getElementById('client-erp-provision');
    var btn = document.getElementById('clientErpProvisionBtn');
    var plan = document.getElementById('clientErpPlanSelect');
    var msg = document.getElementById('clientErpProvisionMsg');
    if (!root || !btn || !plan) return;
    var apiBase = root.getAttribute('data-api-base') || '';
    var agencyId = parseInt(root.getAttribute('data-agency-id') || '0', 10);
    btn.addEventListener('click', function () {
        if (!agencyId || !apiBase) return;
        var planSlug = plan.value || 'professional';
        btn.disabled = true;
        if (msg) { msg.hidden = false; msg.textContent = 'Saving package and provisioning ERP…'; }
        fetch(apiBase + '/agencies-erp-plan.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ agency_id: agencyId, plan_slug: planSlug })
        }).then(function () {
            return fetch(apiBase + '/agencies-provision-erp.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ agency_id: agencyId, plan_slug: planSlug })
            });
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.success) {
                if (msg) msg.textContent = (data && data.message) ? data.message : 'Provisioning failed';
                btn.disabled = false;
                return;
            }
            var seed = data.data && data.data.seed ? data.data.seed : null;
            var text = 'ERP ready (' + (data.data.erp_plan_slug || planSlug) + ')';
            if (seed && seed.admin_email) {
                text += ' — Admin: ' + seed.admin_email;
                if (seed.admin_password) text += ' / Password: ' + seed.admin_password;
            }
            if (msg) msg.textContent = text;
            window.setTimeout(function () { window.location.reload(); }, 2500);
        }).catch(function () {
            if (msg) msg.textContent = 'Provisioning request failed';
            btn.disabled = false;
        });
    });
})();
</script>
<?php } ?>
<?php
require __DIR__ . '/_common-end.inc.php';
