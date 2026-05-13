<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'services';
$RCP_HEADING = 'Services';
$RCP_SUBHEADING = 'Service lifecycle, provisioning visibility, renewals, and active resources inside Client Hub.';
require __DIR__ . '/_common-start.inc.php';
$legacyServicesQuery = [
    'embed' => '1',
    'compatibility' => '1',
];
$legacyServicesAgencyId = (int) ($_SESSION['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
if (function_exists('ratib_control_pro_bridge') && ratib_control_pro_bridge() && $legacyServicesAgencyId > 0) {
    $legacyServicesQuery['control'] = '1';
    $legacyServicesQuery['agency_id'] = (string) $legacyServicesAgencyId;
}
$legacyServicesSrc = htmlspecialchars(
    rtrim((string) getBaseUrl(), '/') . '/modules/infrastructure-marketplace/Views/client/services.php?' . http_build_query($legacyServicesQuery),
    ENT_QUOTES,
    'UTF-8'
);
$catalogHref = htmlspecialchars(ratib_nav_url('client/domains.php', 'catalog=1'), ENT_QUOTES, 'UTF-8');
?>
            <div class="ratib-cp-board">
                <section id="client-service-lifecycle" class="ratib-cp-card mb-4">
                    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                        <div>
                            <h2>Service lifecycle</h2>
                            <p class="rcp-note mb-0">Canonical service visibility now lives in Client Hub while the infrastructure module remains the internal capability layer.</p>
                        </div>
                        <a class="ratib-cp-pillbtn" href="<?php echo $catalogHref; ?>">Browse plans &amp; domains</a>
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
                <div class="ratib-cp-metrics" role="list">
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
                        <section class="ratib-cp-card" role="listitem" aria-label="<?php echo htmlspecialchars($c[0], ENT_QUOTES, 'UTF-8'); ?>">
                            <h2><?php echo htmlspecialchars($c[0], ENT_QUOTES, 'UTF-8'); ?></h2>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="ratib-status <?php echo $c[1] === 'healthy' ? 'ratib-status--active' : 'ratib-status--pending'; ?>">
                                    <?php echo htmlspecialchars($c[1], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="rcp-muted-span">Uptime 99.98%</span>
                            </div>
                            <p class="rcp-note mb-1"><?php echo htmlspecialchars($c[2], ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="rcp-note"><?php echo htmlspecialchars($c[3], ENT_QUOTES, 'UTF-8'); ?></p>
                            <div class="ratib-cp-quick mt-3" role="group">
                                <button type="button" onclick="RatibClientActions.restart('svc-demo')">Restart</button>
                                <button type="button" class="secondary" onclick="RatibClientActions.suspend('svc-demo')">Suspend</button>
                                <button type="button" class="secondary" onclick="RatibClientActions.upgrade('svc-demo')">Upgrade</button>
                            </div>
                        </section>
                        <?php
                    }
                    ?>
                </div>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
