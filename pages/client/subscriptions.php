<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'subs';
$RCP_HEADING = 'Subscriptions';
$RCP_SUBHEADING = 'Plans, add-ons, proration previews, renewal buffers.';
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="rateb-cp-split">
                <section class="rateb-cp-card">
                    <h2>Primary subscription</h2>
                    <p class="rcp-value mb-2">Enterprise Dashboard</p>
                    <span class="rateb-status rateb-status--processing">Renews · auto</span>
                    <ul class="rateb-cp-feed mt-3" role="list">
                        <li><strong>Add-on:</strong> premium support · SLA 15m</li>
                        <li><strong>Seat packs:</strong> 48 / 50 active operators</li>
                        <li><strong>Billing rhythm:</strong> monthly · SAR</li>
                    </ul>
                    <div class="rateb-cp-quick mt-3">
                        <button type="button" onclick="RATEBClientActions.upgrade('subscription')">Upgrade</button>
                        <button type="button" class="secondary" onclick="RATEBClientActions.cancel('subscription-demo')">Cancel at term</button>
                    </div>
                </section>
                <section class="rateb-cp-card">
                    <h2>History</h2>
                    <div class="rateb-cp-timeline" role="list">
                        <article><strong>Billing synced</strong><br><span class="rcp-muted-span">Snapshot API ready</span></article>
                        <article><strong>Grace window</strong><br><span class="rcp-muted-span">Configurable per tenant DB</span></article>
                        <article><strong>Proration preview</strong><br><span class="rcp-muted-span">Hook-ready for checkout services</span></article>
                    </div>
                </section>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
