<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'home';
$RCP_HEADING = 'Enterprise dashboard';
$RCP_SUBHEADING = 'Hosting, domains, billing, security, marketplace — unified cockpit.';
$RCP_EXTRA_JS = [ratib_client_dashboard_asset_url('js/client-dashboard-home.js')];
require __DIR__ . '/_common-start.inc.php';
?>
            <span id="rcp-home-config" class="visually-hidden">home shell</span>
            <div class="ratib-cp-board">
                <p id="rcp-loading-state" class="rcp-muted-span" role="status" aria-live="polite">
                    Loading live metrics…
                </p>

                <div class="ratib-cp-metrics" aria-label="Key metrics">
                    <div class="ratib-cp-card">
                        <h2>Billing snapshots</h2>
                        <div class="rcp-value"><span id="rcp-inv-count" class="ratib-cp-skeleton">&nbsp;</span></div>
                        <p class="rcp-note">
                            Tenant invoice count when <code class="rcp-code">accounting_invoices</code> exists —
                            graceful zero otherwise.
                        </p>
                    </div>
                    <div class="ratib-cp-card">
                        <h2>Subscription health</h2>
                        <div class="rcp-value">
                            <span id="rcp-sub-health" class="ratib-cp-skeleton">&nbsp;</span>
                        </div>
                        <p class="rcp-note">
                            Wired to provisioning data when schemas land — never blocks dashboard render.
                        </p>
                    </div>
                    <div class="ratib-cp-card">
                        <h2>Infrastructure</h2>
                        <div class="rcp-value">
                            <span id="rcp-infra" class="ratib-cp-skeleton">&nbsp;</span>
                        </div>
                        <p class="rcp-note">
                            Optional live probe endpoint link is exposed inside JSON <code class="rcp-code">links.infra_dashboard</code>.
                        </p>
                    </div>
                </div>

                <div class="ratib-cp-split">
                    <section class="ratib-cp-card" aria-labelledby="rcp-recent-orders-h">
                        <h2 id="rcp-recent-orders-h">Recent orders</h2>
                        <div id="rcp-recent-orders" class="pt-2"></div>
                        <p class="rcp-note mt-3">
                            <a href="<?php echo htmlspecialchars(ratib_nav_url('client/orders.php'), ENT_QUOTES, 'UTF-8'); ?>">Open orders center →</a>
                        </p>
                    </section>
                    <div class="d-flex flex-column gap-3">
                        <section class="ratib-cp-card" aria-labelledby="rcp-activity-h">
                            <h2 id="rcp-activity-h">Activity</h2>
                            <ul id="rcp-activity-feed" class="ratib-cp-feed" role="list"></ul>
                        </section>
                        <section class="ratib-cp-card" aria-labelledby="rcp-sec-h">
                            <h2 id="rcp-sec-h">Security signals</h2>
                            <div id="rcp-security-mini" class="rcp-note"></div>
                        </section>
                    </div>
                </div>

                <section class="ratib-cp-card" aria-labelledby="rcp-quick-h">
                    <h2 id="rcp-quick-h">Quick actions</h2>
                    <p class="rcp-note">Central action layer — posts to <code class="rcp-code">api/client-dashboard/actions.php</code> (stub, production-safe).</p>
                    <div id="rcp-quick-actions" class="ratib-cp-quick" role="group" aria-label="Quick actions">
                        <button type="button" data-rcp-action="open_ticket">Open ticket</button>
                        <button type="button" data-rcp-action="renew" data-rcp-target-id="demo-service">Renew</button>
                        <button type="button" data-rcp-action="retry_payment" data-rcp-target-id="demo-invoice">Retry payment</button>
                        <button type="button" data-rcp-action="upgrade" data-rcp-target-id="demo-plan">Upgrade</button>
                    </div>
                </section>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
