<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'infrastructure';
$RCP_HEADING = 'Infrastructure status';
$RCP_SUBHEADING = 'Control-plane aware read model + optional live JSON probe.';
$RCP_EXTRA_JS = [ratib_client_dashboard_asset_url('js/client-dashboard-infra.js')];
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="ratib-cp-board">
                <div class="ratib-cp-card mb-3">
                    <h2>Live snapshot</h2>
                    <p class="rcp-note" id="rcp-infra-json" role="status">Pulling public infrastructure dashboard JSON…</p>
                </div>
                <div class="ratib-cp-metrics">
                    <section class="ratib-cp-card">
                        <h2>Queue posture</h2>
                        <p class="rcp-note" id="rcp-infra-queue">—</p>
                    </section>
                    <section class="ratib-cp-card">
                        <h2>Providers</h2>
                        <p class="rcp-note" id="rcp-infra-providers">—</p>
                    </section>
                    <section class="ratib-cp-card">
                        <h2>Diagnostics</h2>
                        <p class="rcp-note" id="rcp-infra-diag">—</p>
                    </section>
                </div>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
