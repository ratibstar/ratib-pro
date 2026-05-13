<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'settings';
$RCP_HEADING = 'Account &amp; team';
$RCP_SUBHEADING = 'Profile, organisation, seats, and role-gated settings managed through the unified platform ownership flow.';
$clientSettingsAgencyId = (int) ($_SESSION['control_agency_id'] ?? ($_SESSION['agency_id'] ?? 0));
$clientSettingsQuery = ['control' => '1'];
if ($clientSettingsAgencyId > 0) {
    $clientSettingsQuery['agency_id'] = (string) $clientSettingsAgencyId;
}
$controlPanelBase = rtrim((string) getBaseUrl(), '/') . '/control-panel/pages/control';
$clientProfileUrl = htmlspecialchars(ratib_nav_url('client/settings.php', 'section=profile'), ENT_QUOTES, 'UTF-8');
$panelSettingsUrl = htmlspecialchars($controlPanelBase . '/panel-settings.php?' . http_build_query($clientSettingsQuery), ENT_QUOTES, 'UTF-8');
$systemSettingsUrl = htmlspecialchars($controlPanelBase . '/system-settings.php?' . http_build_query($clientSettingsQuery), ENT_QUOTES, 'UTF-8');
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="ratib-cp-split">
                <section class="ratib-cp-card">
                    <h2>Shortcuts</h2>
                    <div class="d-flex flex-column gap-2">
                        <a class="ratib-cp-pillbtn justify-content-center" href="<?php echo $clientProfileUrl; ?>">Profile</a>
                        <a class="ratib-cp-pillbtn justify-content-center" href="<?php echo $panelSettingsUrl; ?>" target="_blank" rel="noopener noreferrer">Control panel settings</a>
                        <a class="ratib-cp-pillbtn justify-content-center" href="<?php echo $systemSettingsUrl; ?>" target="_blank" rel="noopener noreferrer">System settings (role gated)</a>
                    </div>
                </section>
                <section class="ratib-cp-card">
                    <h2>Team controls</h2>
                    <p class="rcp-note">RBAC, SCIM, and audit exports plug into existing permission tables without refactors.</p>
                    <ul class="ratib-cp-feed" role="list">
                        <li><strong>Seat model:</strong> floating</li>
                        <li><strong>Break-glass:</strong> disabled</li>
                        <li><strong>Audit trail:</strong> streaming to SIEM stub</li>
                    </ul>
                </section>
            </div>
            <section id="client-settings-profile" class="ratib-cp-card mt-4">
                <h2>Profile ownership</h2>
                <p class="rcp-note">Client Hub keeps the account journey here, while privileged configuration opens in the main control panel instead of the legacy app shell.</p>
                <ul class="ratib-cp-feed" role="list">
                    <li><strong>User:</strong> <?php echo htmlspecialchars((string) ($_SESSION['username'] ?? 'Account'), ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><strong>Agency:</strong> <?php echo htmlspecialchars((string) ($_SESSION['control_agency_name'] ?? ($_SESSION['agency_name'] ?? 'Current workspace')), ENT_QUOTES, 'UTF-8'); ?></li>
                    <li><strong>Settings owner:</strong> Main control panel</li>
                </ul>
                <div class="ratib-cp-quick mt-3" role="group" aria-label="Settings ownership actions">
                    <a class="ratib-cp-pillbtn" href="<?php echo $panelSettingsUrl; ?>" target="_blank" rel="noopener noreferrer">Open control panel settings</a>
                    <a class="ratib-cp-pillbtn" href="<?php echo $systemSettingsUrl; ?>" target="_blank" rel="noopener noreferrer">Open gated system settings</a>
                </div>
            </section>
<?php
require __DIR__ . '/_common-end.inc.php';
