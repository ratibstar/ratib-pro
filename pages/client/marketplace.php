<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'marketplace';
$RCP_HEADING = 'Marketplace access';
$RCP_SUBHEADING = 'Deep link into the infrastructure marketplace shell.';
require __DIR__ . '/_common-start.inc.php';
$mk = htmlspecialchars(ratib_client_dashboard_marketplace_href(), ENT_QUOTES, 'UTF-8');
?>
            <div class="ratib-cp-card">
                <p class="rcp-note mb-3">Browse catalog, compare providers, and attach SKUs to your tenant context.</p>
                <a class="ratib-cp-pillbtn" href="<?php echo $mk; ?>">Open marketplace UI</a>
            </div>
<?php
require __DIR__ . '/_common-end.inc.php';
