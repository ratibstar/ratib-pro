<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'notifications';
$RCP_HEADING = 'Notifications center';
$RCP_SUBHEADING = 'Operational, billing, and security digests — parallel to core notifications module.';
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="ratib-cp-board">
                <div class="ratib-cp-alert mb-3" role="status">
                    Client hub uses the same session as the main app. For legacy contact notifications
                    open the standard <a class="text-white text-decoration-underline" href="<?php echo htmlspecialchars(ratib_nav_url('notifications.php'), ENT_QUOTES, 'UTF-8'); ?>">notifications workspace</a>.
                </div>
                <div class="ratib-cp-metrics">
                    <section class="ratib-cp-card">
                        <h2>Delivery channels</h2>
                        <p class="rcp-note">Email · Push · Webhook — adapter slots reserved.</p>
                    </section>
                    <section class="ratib-cp-card">
                        <h2>Digest controls</h2>
                        <p class="rcp-note">Frequency + quiet hours — stored per tenant without schema breaks.</p>
                    </section>
                    <section class="ratib-cp-card">
                        <h2>Streams</h2>
                        <ul class="ratib-cp-feed" role="list">
                            <li><strong>Billing:</strong> invoice issued</li>
                            <li><strong>Security:</strong> token created</li>
                            <li><strong>Infra:</strong> maintenance window</li>
                        </ul>
                    </section>
                </div>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
