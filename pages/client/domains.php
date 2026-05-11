<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'domains';
$RCP_HEADING = 'Domains management';
$RCP_SUBHEADING = 'Registry sync, expiry radar, transfers, DNS, WHOIS.';
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="ratib-cp-board">
                <div class="ratib-cp-table-wrap mb-4" role="region" aria-label="Domain list placeholder">
                    <div class="ratib-cp-toolbar">
                        <span class="rcp-muted-span">Read-only scaffolding — bind to registrar tables when available.</span>
                    </div>
                    <div class="ratib-cp-table-scroll">
                        <table class="ratib-cp-table">
                            <thead>
                                <tr>
                                    <th scope="col">Domain</th>
                                    <th scope="col">Registrar</th>
                                    <th scope="col">Expires</th>
                                    <th scope="col">Auto renew</th>
                                    <th scope="col">Lock</th>
                                    <th scope="col">Health</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>example.sa</td>
                                    <td>RATIB</td>
                                    <td><?php echo htmlspecialchars(gmdate('Y-m-d', strtotime('+300 days')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="ratib-status ratib-status--active">On</span></td>
                                    <td><span class="ratib-status ratib-status--active">Transfer lock</span></td>
                                    <td><span class="ratib-status ratib-status--processing">DNSSEC pending</span></td>
                                </tr>
                                <tr>
                                    <td>marketing.io</td>
                                    <td>Partner</td>
                                    <td><?php echo htmlspecialchars(gmdate('Y-m-d', strtotime('+41 days')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="ratib-status ratib-status--pending">Off</span></td>
                                    <td><span class="ratib-status ratib-status--neutral">Open</span></td>
                                    <td><span class="ratib-status ratib-status--active">OK</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="ratib-cp-metrics">
                    <section class="ratib-cp-card">
                        <h2>Expiry radar</h2>
                        <p class="rcp-note">Feeds home dashboard widgets + email digests.</p>
                    </section>
                    <section class="ratib-cp-card">
                        <h2>DNS controls</h2>
                        <p class="rcp-note">Pluggable to PowerDNS / CloudDNS connectors — unchanged routes.</p>
                    </section>
                    <section class="ratib-cp-card">
                        <h2>WHOIS &amp; RDAP</h2>
                        <p class="rcp-note">Status mirrors registry policy (proxy, GDPR redaction).</p>
                    </section>
                </div>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
