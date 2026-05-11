<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'support';
$RCP_HEADING = 'Support center';
$RCP_SUBHEADING = 'Tickets, priorities, SLA, service-linked escalations.';
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="ratib-cp-split">
                <section class="ratib-cp-card">
                    <div class="d-flex gap-3 flex-wrap mb-3">
                        <button type="button" class="ratib-cp-pillbtn" onclick="RatibClientActions.openTicket();">Compose ticket</button>
                        <a class="ratib-cp-pillbtn" href="<?php echo htmlspecialchars(ratib_nav_url('help-center.php'), ENT_QUOTES, 'UTF-8'); ?>">Knowledge base</a>
                    </div>
                    <h2>Lifecycle</h2>
                    <div class="ratib-cp-table-scroll">
                        <table class="ratib-cp-table">
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
                                    <td><span class="ratib-status ratib-status--failed">P1</span></td>
                                    <td>VPS · cx31</td>
                                    <td><span class="ratib-status ratib-status--processing">Engineering</span></td>
                                </tr>
                                <tr>
                                    <td>SUP-1040</td>
                                    <td><span class="ratib-status ratib-status--pending">P3</span></td>
                                    <td>Billing</td>
                                    <td><span class="ratib-status ratib-status--active">Resolved</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="ratib-cp-card">
                    <h2>Timeline</h2>
                    <div class="ratib-cp-timeline" role="list">
                        <article><strong>Customer update</strong><br><span class="rcp-muted-span">Template ready for chat bridge</span></article>
                        <article><strong>Provider note</strong><br><span class="rcp-muted-span">Infra marketplace hooks</span></article>
                    </div>
                </section>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
