<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'security';
$RCP_HEADING = 'Security center';
$RCP_SUBHEADING = 'Sessions, MFA, tokens, anomalies.';
$RCP_EXTRA_JS = [rateb_client_dashboard_asset_url('js/client-dashboard-security.js')];
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="rateb-cp-split">
                <section class="rateb-cp-card" id="rcp-security-overview">
                    <h2>Posture snapshot</h2>
                    <p class="rcp-note" id="rcp-mfa-chip">Checking MFA telemetry…</p>
                    <ul class="rateb-cp-feed" role="list">
                        <li><strong>Sessions:</strong> 2 active consoles</li>
                        <li><strong>API tokens:</strong> 6 scoped secrets</li>
                        <li><strong>Adaptive alerts:</strong> idle</li>
                    </ul>
                    <div class="rateb-cp-quick mt-3">
                        <button type="button" id="rcp-revoke-sessions">Revoke other sessions</button>
                    </div>
                </section>
                <section class="rateb-cp-card">
                    <h2>Recent security events</h2>
                    <div class="rateb-cp-timeline" role="list" id="rcp-security-feed">
                        <article><strong>Successful login · Edge</strong><br><span class="rcp-muted-span">Adaptive risk low</span></article>
                        <article><strong>API token rotated</strong><br><span class="rcp-muted-span">Automation CI</span></article>
                        <article><strong>Suspicious sign-in suppressed</strong><br><span class="rcp-muted-span">Captcha challenge passed</span></article>
                    </div>
                </section>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
