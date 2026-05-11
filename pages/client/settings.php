<?php
require_once __DIR__ . '/_auth.inc.php';
$RCP_SECTION = 'settings';
$RCP_HEADING = 'Account &amp; team';
$RCP_SUBHEADING = 'Profile, organisation, seats, API keys — mirrors existing settings modules.';
require __DIR__ . '/_common-start.inc.php';
?>
            <div class="ratib-cp-split">
                <section class="ratib-cp-card">
                    <h2>Shortcuts</h2>
                    <div class="d-flex flex-column gap-2">
                        <a class="ratib-cp-pillbtn justify-content-center" href="<?php echo htmlspecialchars(ratib_nav_url('profile.php'), ENT_QUOTES, 'UTF-8'); ?>">Profile</a>
                        <a class="ratib-cp-pillbtn justify-content-center" href="<?php echo htmlspecialchars(ratib_nav_url('settings.php'), ENT_QUOTES, 'UTF-8'); ?>">Legacy settings</a>
                        <a class="ratib-cp-pillbtn justify-content-center" href="<?php echo htmlspecialchars(ratib_nav_url('system-settings.php'), ENT_QUOTES, 'UTF-8'); ?>">System settings (role gated)</a>
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
<?php
require __DIR__ . '/_common-end.inc.php';
