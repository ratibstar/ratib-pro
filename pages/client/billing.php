<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'billing';
$RCP_HEADING = 'Billing &amp; invoices';
$RCP_SUBHEADING = 'Invoices, transactions, wallets, reminders.';
$billingSupportUrl = htmlspecialchars(rateb_client_dashboard_context_url('support.php', 'topic=billing'), ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="rateb-cp-split">
                <section class="rateb-cp-card">
                    <h2>Summary</h2>
                    <p class="rcp-value">Wallet · SAR <?php echo htmlspecialchars(number_format(0.0, 2), ENT_QUOTES, 'UTF-8'); ?></p>
                    <p class="rcp-note">Extend with ledger tables — existing accounting endpoints stay untouched.</p>
                    <ul class="rateb-cp-feed" role="list">
                        <li><strong>Unpaid invoices:</strong> 0 staged</li>
                        <li><strong>Auto-pay:</strong> configurable</li>
                        <li><strong>Tax profile:</strong> VAT ready</li>
                    </ul>
                    <div class="rateb-cp-quick mt-3">
                        <button type="button" onclick="RATEBClientActions.retryPayment('INV-DEMO')">Retry payment</button>
                        <a class="rateb-cp-pillbtn" href="<?php echo $billingSupportUrl; ?>">Billing support</a>
                    </div>
                </section>
                <section class="rateb-cp-card">
                    <h2>Recent transactions</h2>
                    <div class="rateb-cp-table-scroll">
                        <table class="rateb-cp-table">
                            <thead>
                                <tr>
                                    <th scope="col">Reference</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Recorded</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>TX-90012</td>
                                    <td>Hosting renewal</td>
                                    <td><span class="rateb-status rateb-status--pending">Queued</span></td>
                                    <td><?php echo htmlspecialchars(gmdate('Y-m-d H:i'), ENT_QUOTES, 'UTF-8'); ?> UTC</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
