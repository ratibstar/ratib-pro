<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'domains';
$RCP_HEADING = 'Domains';
$RCP_SUBHEADING = 'Search, compare, and manage domains inside the unified Client Hub experience.';
require __DIR__ . '/_common-start.inc.php';
$catalogMode = isset($_GET['catalog']) && (string) $_GET['catalog'] === '1';
$legacyMarketplaceQuery = [
    'focus' => 'domains',
    'embed' => '1',
    'compatibility' => '1',
];
$legacyMarketplaceAgencyId = (int) ($_SESSION['agency_id'] ?? ($_SESSION['control_agency_id'] ?? 0));
if (function_exists('rateb_control_pro_bridge') && rateb_control_pro_bridge() && $legacyMarketplaceAgencyId > 0) {
    $legacyMarketplaceQuery['control'] = '1';
    $legacyMarketplaceQuery['agency_id'] = (string) $legacyMarketplaceAgencyId;
}
$legacyMarketplaceSrc = htmlspecialchars(
    rateb_client_dashboard_public_site_base_url() . '/modules/infrastructure-marketplace/Views/marketplace/index.php?' . http_build_query($legacyMarketplaceQuery),
    ENT_QUOTES,
    'UTF-8'
);
?>
            <div class="rateb-cp-board">
                <section id="client-domain-catalog" class="rateb-cp-card mb-4">
                    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                        <div>
                            <h2><?php echo $catalogMode ? 'Domain search & service catalog' : 'Domain search'; ?></h2>
                            <p class="rcp-note mb-0">The infrastructure marketplace module is rendered here as an internal capability so the client journey stays inside Client Hub.</p>
                        </div>
                        <a class="rateb-cp-pillbtn" href="<?php echo htmlspecialchars(rateb_client_dashboard_context_url('services.php'), ENT_QUOTES, 'UTF-8'); ?>">Open services</a>
                    </div>
                    <div class="mt-3" style="border:1px solid rgba(255,255,255,.08);border-radius:18px;overflow:hidden;background:rgba(5,10,24,.45);">
                        <iframe
                            src="<?php echo $legacyMarketplaceSrc; ?>"
                            title="Domains and catalog"
                            loading="lazy"
                            referrerpolicy="same-origin"
                            style="width:100%;min-height:640px;border:0;display:block;background:transparent;"
                        ></iframe>
                    </div>
                </section>
                <div class="rateb-cp-table-wrap mb-4" role="region" aria-label="Domain list placeholder">
                    <div class="rateb-cp-toolbar">
                        <span class="rcp-muted-span">Read-only scaffolding — bind to registrar tables when available.</span>
                    </div>
                    <div class="rateb-cp-table-scroll">
                        <table class="rateb-cp-table">
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
                                    <td>RATEB</td>
                                    <td><?php echo htmlspecialchars(gmdate('Y-m-d', strtotime('+300 days')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="rateb-status rateb-status--active">On</span></td>
                                    <td><span class="rateb-status rateb-status--active">Transfer lock</span></td>
                                    <td><span class="rateb-status rateb-status--processing">DNSSEC pending</span></td>
                                </tr>
                                <tr>
                                    <td>marketing.io</td>
                                    <td>Partner</td>
                                    <td><?php echo htmlspecialchars(gmdate('Y-m-d', strtotime('+41 days')), ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><span class="rateb-status rateb-status--pending">Off</span></td>
                                    <td><span class="rateb-status rateb-status--neutral">Open</span></td>
                                    <td><span class="rateb-status rateb-status--active">OK</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="rateb-cp-metrics">
                    <section class="rateb-cp-card">
                        <h2>Expiry radar</h2>
                        <p class="rcp-note">Feeds home dashboard widgets + email digests.</p>
                    </section>
                    <section class="rateb-cp-card">
                        <h2>DNS controls</h2>
                        <p class="rcp-note">Pluggable to PowerDNS / CloudDNS connectors — unchanged routes.</p>
                    </section>
                    <section class="rateb-cp-card">
                        <h2>WHOIS &amp; RDAP</h2>
                        <p class="rcp-note">Status mirrors registry policy (proxy, GDPR redaction).</p>
                    </section>
                </div>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
