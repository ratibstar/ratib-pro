<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'notifications';
$RCP_HEADING = 'Notifications center';
$RCP_SUBHEADING = 'Operational, billing, and security digests — parallel to core notifications module.';
$notificationsControlMode = (!empty($_GET['control']) && (string) $_GET['control'] === '1') || !empty($_SESSION['control_logged_in']);
$notificationsQuery = ['control' => '1'];
$notificationsAgencyId = (int) ($_GET['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
if ($notificationsAgencyId > 0) {
    $notificationsQuery['agency_id'] = (string) $notificationsAgencyId;
}
$notificationsLegacyUrl = $notificationsControlMode
    ? (rtrim((string) getBaseUrl(), '/') . '/control-panel/pages/control/control-hub.php?' . http_build_query($notificationsQuery + ['legacy_module' => 'notifications']))
    : ratib_nav_url('notifications.php');
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="ratib-cp-board">
                <div class="ratib-cp-alert mb-3" role="status">
                    Client Hub uses the same session as the main platform. If you need the legacy alert flow,
                    open the <a class="text-white text-decoration-underline" href="<?php echo htmlspecialchars($notificationsLegacyUrl, ENT_QUOTES, 'UTF-8'); ?>">notifications route owner</a>.
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
