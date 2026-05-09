<?php
/**
 * Edit public marketing copy for pages/home.php (stored in control DB).
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
require_once __DIR__ . '/../../includes/control-permissions.php';
requireControlPermission(CONTROL_PERM_SYSTEM_SETTINGS, 'view_control_system_settings');

require_once __DIR__ . '/../../../includes/site-content.php';

$ctrl = $GLOBALS['control_conn'] ?? null;
$tableOk = false;
if ($ctrl instanceof mysqli) {
    try {
        $chk = $ctrl->query("SHOW TABLES LIKE 'ratib_site_content'");
        $tableOk = $chk && $chk->num_rows > 0;
    } catch (Throwable $e) {
        $tableOk = false;
    }
}

$allowedKeys = array_keys(ratib_site_content_defaults_public_home());
$flashOk = false;
$flashErr = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ratib_site_content_save'])) {
    requireControlPermission('edit_control_system_settings');
    $nonceIn = (string) ($_POST['_nonce'] ?? '');
    $nonceOk = isset($_SESSION['ratib_site_content_nonce']) && hash_equals((string) $_SESSION['ratib_site_content_nonce'], $nonceIn);
    if (!$nonceOk) {
        $flashErr = 'Session expired. Refresh and try again.';
    } elseif (!$ctrl instanceof mysqli) {
        $flashErr = 'Database unavailable.';
    } elseif (!$tableOk) {
        $flashErr = 'Table ratib_site_content missing. Run sql/ratib_site_content.sql on the control database.';
    } else {
        $posted = $_POST['content'] ?? null;
        $posted = is_array($posted) ? $posted : [];
        $stmt = $ctrl->prepare(
            'INSERT INTO ratib_site_content (content_key, content_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_at = CURRENT_TIMESTAMP'
        );
        if ($stmt) {
            foreach ($allowedKeys as $key) {
                if (!array_key_exists($key, $posted)) {
                    continue;
                }
                $val = is_string($posted[$key]) ? $posted[$key] : '';
                $val = str_replace(["\r\n", "\r"], "\n", $val);
                $val = trim($val);
                $paramKey = $key;
                $stmt->bind_param('ss', $paramKey, $val);
                $stmt->execute();
            }
            $stmt->close();
            $flashOk = true;
        } else {
            $flashErr = 'Could not prepare save statement.';
        }
    }
}

$_SESSION['ratib_site_content_nonce'] = bin2hex(random_bytes(16));
$nonce = $_SESSION['ratib_site_content_nonce'];

$values = ratib_site_content_defaults_public_home();
foreach ($allowedKeys as $k) {
    $values[$k] = ratib_site_content_get($k, $values[$k]);
}

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Public site content', [], []);

?>
<div class="control-settings-intro mb-3">
    <strong><i class="fas fa-window-maximize me-2"></i>Public home page copy</strong>
    <p class="mb-0 small text-muted">Edits the English marketing homepage (<code>pages/home.php</code>): hero text, platform intro, optional program image paths. Values are stored in <code>ratib_site_content</code> (control database).</p>
</div>

<?php if (!$tableOk): ?>
<div class="alert alert-warning">
    <strong>Setup required.</strong> Create the table by running <code>sql/ratib_site_content.sql</code> against your <strong>control panel</strong> database (<code><?php echo htmlspecialchars(defined('CONTROL_PANEL_DB_NAME') ? CONTROL_PANEL_DB_NAME : '', ENT_QUOTES, 'UTF-8'); ?></code>), then reload this page.
</div>
<?php endif; ?>

<?php if ($flashOk): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">Saved.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
<?php endif; ?>
<?php if ($flashErr !== ''): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($flashErr, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form method="post" action="" class="ratib-site-content-form">
    <input type="hidden" name="_nonce" value="<?php echo htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="ratib_site_content_save" value="1">

    <div class="card mb-3">
        <div class="card-header">Hero</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="f_eyebrow">Eyebrow line</label>
                <input type="text" class="form-control" id="f_eyebrow" name="content[home.hero.eyebrow]" value="<?php echo htmlspecialchars($values['home.hero.eyebrow'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="500">
            </div>
            <div class="mb-0">
                <label class="form-label" for="f_lead">Lead paragraph</label>
                <textarea class="form-control" id="f_lead" name="content[home.hero.lead]" rows="4" maxlength="8000"><?php echo htmlspecialchars($values['home.hero.lead'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Platform section (#platform)</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="f_ptitle">Section title</label>
                <input type="text" class="form-control" id="f_ptitle" name="content[home.platform.title]" value="<?php echo htmlspecialchars($values['home.platform.title'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="500">
            </div>
            <div class="mb-0">
                <label class="form-label" for="f_psub">Subtitle</label>
                <textarea class="form-control" id="f_psub" name="content[home.platform.sub]" rows="3" maxlength="8000"><?php echo htmlspecialchars($values['home.platform.sub'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Program preview images (optional)</div>
        <div class="card-body">
            <p class="small text-muted mb-3">Leave blank to use bundled SVGs. Otherwise use a path under your site root (e.g. <code>assets/images/my-shot.png</code>) or a full <code>https://</code> URL.</p>
            <div class="mb-3">
                <label class="form-label" for="f_img1">Image 1 — Pipeline board</label>
                <input type="text" class="form-control font-monospace small" id="f_img1" name="content[home.program.img1]" value="<?php echo htmlspecialchars($values['home.program.img1'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="1000" placeholder="assets/images/program-preview-pipeline.svg">
            </div>
            <div class="mb-3">
                <label class="form-label" for="f_img2">Image 2 — Workers registry</label>
                <input type="text" class="form-control font-monospace small" id="f_img2" name="content[home.program.img2]" value="<?php echo htmlspecialchars($values['home.program.img2'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="1000">
            </div>
            <div class="mb-0">
                <label class="form-label" for="f_img3">Image 3 — Finance &amp; ledger</label>
                <input type="text" class="form-control font-monospace small" id="f_img3" name="content[home.program.img3]" value="<?php echo htmlspecialchars($values['home.program.img3'], ENT_QUOTES, 'UTF-8'); ?>" maxlength="1000">
            </div>
        </div>
    </div>

    <?php if ($tableOk && hasControlPermission('edit_control_system_settings')): ?>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
    <?php
    $ratibPublicHomeUrl = '/pages/home.php';
    if (function_exists('control_ratib_pro_public_base_url')) {
        $ratibPublicHomeUrl = rtrim((string) control_ratib_pro_public_base_url(), '/') . '/pages/home.php';
    }
    ?>
    <a href="<?php echo htmlspecialchars($ratibPublicHomeUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary ms-2">Open public home</a>
    <?php elseif (!$tableOk): ?>
    <button type="button" class="btn btn-secondary" disabled>Save (create table first)</button>
    <?php else: ?>
    <p class="text-muted small mb-0">You do not have permission to edit.</p>
    <?php endif; ?>
</form>
<?php endControlLayout(); ?>
