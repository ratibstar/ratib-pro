<?php
/**
 * RATEB Contact Center — launcher inside Control Panel.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control/contact-center-nav.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}

require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_DASHBOARD, 'control_system_settings', 'view_control_system_settings');

$diag = control_contact_center_diagnostic();
$installed = $diag['installed'];
$dbTest = $installed ? control_contact_center_db_test() : ['ok' => false, 'schema' => false, 'db' => control_contact_center_db_name(), 'user' => control_contact_center_db_user(), 'error' => '', 'tables' => 0];
$schemaReady = $dbTest['ok'] && $dbTest['schema'];
$links = control_contact_center_nav_links();
$realtimeStatus = $installed ? control_contact_center_realtime_hub_status() : ['running' => false, 'port' => 9702, 'ws_url' => control_contact_center_ws_url(), 'pid' => null, 'log' => '', 'error' => ''];
$realtimeFlash = '';

if ($installed && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rcc_start_realtime_hub'])) {
    $startResult = control_contact_center_start_realtime_hub();
    $realtimeStatus = control_contact_center_realtime_hub_status();
    $realtimeFlash = ($startResult['message'] ?? 'done') . ($realtimeStatus['running'] ? ' — Hub is running.' : ' — Port still closed; add cPanel cron below.');
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('RATEB Contact Center', ['css/system-settings.css', 'css/control/rateb-erp-hub.css'], []);
?>

<p class="control-settings-intro mb-3">
    <strong><i class="fas fa-headset me-2"></i>RATEB Contact Center</strong>
    — IVR, AI routing, WebRTC softphone, and unified omnichannel agent inbox.
    Database: <code><?php echo htmlspecialchars(control_contact_center_db_name(), ENT_QUOTES, 'UTF-8'); ?></code>
    · User: <code><?php echo htmlspecialchars(control_contact_center_db_user(), ENT_QUOTES, 'UTF-8'); ?></code>
</p>

<div class="rateb-erp-status mb-4" role="status">
    <div class="rateb-erp-status-item<?php echo $installed ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $installed ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
        <span>Module files: <?php echo $installed ? 'Found' : 'Missing — upload ratib-contact-center/'; ?></span>
    </div>
    <div class="rateb-erp-status-item<?php echo $dbTest['ok'] ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $dbTest['ok'] ? 'fa-link' : 'fa-unlink'; ?>"></i>
        <span>DB connection: <?php echo $dbTest['ok'] ? 'OK' : 'Failed'; ?> — <code><?php echo htmlspecialchars((string) $dbTest['db'], ENT_QUOTES, 'UTF-8'); ?></code></span>
    </div>
    <div class="rateb-erp-status-item<?php echo $schemaReady ? ' rateb-erp-status-ok' : ' rateb-erp-status-warn'; ?>">
        <i class="fas <?php echo $schemaReady ? 'fa-table' : 'fa-database'; ?>"></i>
        <span>Schema: <?php echo $schemaReady ? (int) $dbTest['tables'] . ' rcc_* tables' : 'Run database setup first'; ?></span>
    </div>
</div>

<?php if (!$installed) { ?>
<div class="alert alert-warning mb-4">
    <strong>Files not found on server</strong><br>
    Expected: <code><?php echo htmlspecialchars($diag['resolved'] . '/bootstrap.php', ENT_QUOTES, 'UTF-8'); ?></code><br>
    <span class="small">Push to <code>main</code> and wait for GitHub Actions deploy (ratib-contact-center bundle), or upload the folder via cPanel File Manager.</span>
</div>
<?php } elseif (!$dbTest['ok']) { ?>
<div class="alert alert-danger mb-4">
    <strong><i class="fas fa-database me-1"></i> MySQL connection failed</strong><br>
    In cPanel → MySQL® Databases:<br>
    1. Create database <code><?php echo htmlspecialchars((string) $dbTest['db'], ENT_QUOTES, 'UTF-8'); ?></code><br>
    2. Create user <code><?php echo htmlspecialchars((string) $dbTest['user'], ENT_QUOTES, 'UTF-8'); ?></code><br>
    3. <strong>Add User To Database</strong> → ALL PRIVILEGES<br>
    4. Add to project <code>.env</code> on server:<br>
    <pre class="rateb-erp-migrate-log mb-0 mt-2">RATIB_CC_DB_NAME=admin_call-center
RATIB_CC_DB_USER=admin_call-center
RATIB_CC_DB_PASS=your_mysql_password</pre>
    <?php if (($dbTest['error'] ?? '') !== '') { ?>
    <span class="small text-muted d-block mt-2"><?php echo htmlspecialchars((string) $dbTest['error'], ENT_QUOTES, 'UTF-8'); ?></span>
    <?php } ?>
</div>
<?php } elseif (!$schemaReady) { ?>
<div class="control-settings-card mb-4">
    <h3><i class="fas fa-database"></i> First-time setup</h3>
    <p>Runs migrations 001–025 on <code><?php echo htmlspecialchars(control_contact_center_db_name(), ENT_QUOTES, 'UTF-8'); ?></code>.</p>
    <a href="<?php echo htmlspecialchars(control_contact_center_migrate_page_url(), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary">
        <i class="fas fa-play"></i> Run database setup
    </a>
</div>
<?php } ?>

<div class="control-settings-grid mb-4">
    <?php foreach ($links as $link) { ?>
    <div class="control-settings-card" data-permission="control_dashboard,control_system_settings">
        <h3><i class="fas <?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i> <?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo htmlspecialchars($link['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        <?php
        if (!$installed) {
            $openHref = control_contact_center_migrate_page_url();
            $openLabel = 'Setup first';
            $openClass = 'btn btn-warning';
        } elseif ($link['key'] === 'agent-desktop' && !$schemaReady) {
            $openHref = control_contact_center_migrate_page_url();
            $openLabel = 'Run DB setup';
            $openClass = 'btn btn-warning';
        } else {
            $openHref = $link['href'];
            $openLabel = 'Open';
            $openClass = 'btn btn-primary';
        }
        ?>
        <a href="<?php echo htmlspecialchars($openHref, ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $openClass; ?>">
            <i class="fas fa-arrow-right"></i> <?php echo htmlspecialchars($openLabel, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>
    <?php } ?>
    <div class="control-settings-card">
        <h3><i class="fas fa-book"></i> Phase documentation</h3>
        <p>Architecture and module guides (IVR, Realtime, Softphone, AI Routing, Inbox).</p>
        <ul class="small mb-0">
            <li>PHASE-1 Architecture</li>
            <li>PHASE-2 IVR Runtime</li>
            <li>PHASE-3 Realtime Core</li>
            <li>PHASE-4 Softphone</li>
            <li>PHASE-5 AI Routing</li>
            <li>PHASE-6 Omnichannel Inbox</li>
            <li>PHASE-7 AI Copilot</li>
        </ul>
    </div>
</div>

<div class="control-settings-card mb-4">
    <h3><i class="fas fa-sync-alt"></i> Live updates (Agent Desktop)</h3>
    <?php
    $rtMode = function_exists('control_contact_center_realtime_mode')
        ? control_contact_center_realtime_mode()
        : 'polling';
    ?>
    <div class="alert alert-success py-2 small mb-2">
        <strong>Mode: <?php echo htmlspecialchars(strtoupper($rtMode), ENT_QUOTES, 'UTF-8'); ?></strong>
        <?php if ($rtMode === 'polling') { ?>
        — Inbox refreshes every 8 seconds over HTTPS. <strong>No port 9702, no Hetzner firewall rules needed.</strong>
        <?php } else { ?>
        — WebSocket at <code><?php echo htmlspecialchars((string) ($realtimeStatus['ws_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code>
        <?php } ?>
    </div>
    <?php if ($rtMode === 'websocket') { ?>
    <?php if ($realtimeFlash !== '') { ?>
    <div class="alert alert-<?php echo $realtimeStatus['running'] ? 'success' : 'warning'; ?> py-2 small"><?php echo htmlspecialchars($realtimeFlash, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>
    <p class="small mb-2">Hub status: <?php echo $realtimeStatus['running'] ? '<strong class="text-success">Running</strong>' : '<strong class="text-warning">Stopped</strong>'; ?></p>
    <?php if ($installed) { ?>
    <form method="post" class="mb-3">
        <button type="submit" name="rcc_start_realtime_hub" value="1" class="btn btn-sm btn-primary"><i class="fas fa-play"></i> Start Realtime Hub</button>
    </form>
    <?php } ?>
    <?php } else { ?>
    <p class="small text-muted mb-0">Optional: set <code>RCC_REALTIME_MODE=websocket</code> in <code>.env</code> only if you run the hub and open TCP 9702. Default <code>polling</code> is recommended for rateb.sa.</p>
    <?php } ?>
</div>

<?php endControlLayout(); ?>
