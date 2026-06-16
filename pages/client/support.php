<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'support';
$RCP_HEADING = 'Support center';
$RCP_SUBHEADING = 'Tickets, priorities, SLA, service-linked escalations.';
$supportControlMode = (!empty($_GET['control']) && (string) $_GET['control'] === '1') || !empty($_SESSION['control_logged_in']);
$supportQuery = ['control' => '1'];
$supportAgencyId = (int) ($_GET['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
if ($supportAgencyId > 0) {
    $supportQuery['agency_id'] = (string) $supportAgencyId;
}
$supportKnowledgeBaseUrl = $supportControlMode
    ? (rtrim((string) getBaseUrl(), '/') . '/control-panel/pages/control/help-center.php?' . http_build_query($supportQuery))
    : rateb_nav_url('help-center.php');
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="rateb-cp-split">
                <section class="rateb-cp-card">
                    <div class="d-flex gap-3 flex-wrap mb-3">
                        <button type="button" class="rateb-cp-pillbtn" onclick="RATEBClientActions.openTicket();">Compose ticket</button>
                        <a class="rateb-cp-pillbtn" href="<?php echo htmlspecialchars($supportKnowledgeBaseUrl, ENT_QUOTES, 'UTF-8'); ?>">Knowledge base</a>
                    </div>
                    <h2>Lifecycle</h2>
                    <div class="rateb-cp-table-scroll">
                        <table class="rateb-cp-table">
                            <thead>
                                <tr>
                                    <th scope="col">Ticket</th>
                                    <th scope="col">Severity</th>
                                    <th scope="col">Linked service</th>
                                    <th scope="col">Badge</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>SUP-1044</td>
                                    <td><span class="rateb-status rateb-status--failed">P1</span></td>
                                    <td>VPS · cx31</td>
                                    <td><span class="rateb-status rateb-status--processing">Engineering</span></td>
                                </tr>
                                <tr>
                                    <td>SUP-1040</td>
                                    <td><span class="rateb-status rateb-status--pending">P3</span></td>
                                    <td>Billing</td>
                                    <td><span class="rateb-status rateb-status--active">Resolved</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="rateb-cp-card">
                    <h2>Timeline</h2>
                    <div class="rateb-cp-timeline" role="list">
                        <article><strong>Customer update</strong><br><span class="rcp-muted-span">Template ready for chat bridge</span></article>
                        <article><strong>Provider note</strong><br><span class="rcp-muted-span">Infra marketplace hooks</span></article>
                    </div>
                </section>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
