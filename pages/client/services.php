<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'services';
$RCP_HEADING = 'Services center';
$RCP_SUBHEADING = 'Shared hosting, VPS, cloud, domains, email, SSL, security SKUs.';
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="ratib-cp-board">
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
