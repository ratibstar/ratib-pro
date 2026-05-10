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

/**
 * @param array<string, string> $values
 */
function ratib_control_site_content_render_field(array $field, array $values): void
{
    $key = $field['key'];
    $val = $values[$key] ?? '';
    $label = $field['label'];
    $type = $field['type'] ?? 'text';
    $rows = isset($field['rows']) ? (int) $field['rows'] : 2;
    $extraClass = isset($field['class']) ? (' ' . $field['class']) : '';
    $id = 'f_' . preg_replace('/[^a-zA-Z0-9]+/', '_', $key);
    $nameKey = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');

    echo '<div class="mb-3">';
    echo '<label class="form-label" for="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . $label . '</label>';
    if ($type === 'textarea') {
        echo '<textarea class="form-control' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" name="content[' . $nameKey . ']" rows="' . $rows . '" maxlength="65000">' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '</textarea>';
    } elseif ($type === 'media_image' || $type === 'media_video') {
        $accept = $type === 'media_video'
            ? 'video/mp4,video/webm,video/quicktime'
            : 'image/jpeg,image/png,image/webp,image/gif,image/svg+xml';
        $hint = $type === 'media_video'
            ? 'Upload video or keep URL/path. Supported: mp4, webm, mov.'
            : 'Upload image or keep URL/path. Supported: jpg, png, webp, gif, svg.';
        echo '<input type="text" class="form-control' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" name="content[' . $nameKey . ']" value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '" maxlength="65000">';
        echo '<div class="mt-2 d-flex flex-column gap-2">';
        echo '<input type="file" class="form-control form-control-sm" name="media_upload[' . $nameKey . ']" accept="' . htmlspecialchars($accept, ENT_QUOTES, 'UTF-8') . '">';
        echo '<small class="text-muted">' . htmlspecialchars($hint, ENT_QUOTES, 'UTF-8') . '</small>';
        if (trim((string) $val) !== '') {
            echo '<small class="text-muted">Current: <code>' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '</code></small>';
            echo '<label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="media_delete[' . $nameKey . ']" value="1">Delete current file/path</label>';
        }
        echo '</div>';
    } else {
        echo '<input type="text" class="form-control' . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" name="content[' . $nameKey . ']" value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '" maxlength="65000">';
    }
    echo '</div>';
}

/**
 * @return array{ok:bool,path:string,error:string}
 */
function ratib_control_site_content_store_media(array $file, string $kind): array
{
    if (!isset($file['tmp_name'], $file['name'], $file['error'])) {
        return ['ok' => false, 'path' => '', 'error' => 'Invalid upload payload.'];
    }
    $err = (int) $file['error'];
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'error' => 'Upload failed (error ' . $err . ').'];
    }
    $tmp = (string) $file['tmp_name'];
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'path' => '', 'error' => 'Upload temp file missing.'];
    }
    $name = (string) $file['name'];
    $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    $allow = $kind === 'video'
        ? ['mp4', 'webm', 'mov']
        : ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    if ($ext === '' || !in_array($ext, $allow, true)) {
        return ['ok' => false, 'path' => '', 'error' => 'Unsupported file type: ' . $ext];
    }
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    $max = $kind === 'video' ? 80 * 1024 * 1024 : 12 * 1024 * 1024;
    if ($size <= 0 || $size > $max) {
        return ['ok' => false, 'path' => '', 'error' => 'File size must be between 1 byte and ' . (string) $max . ' bytes.'];
    }

    $root = dirname(__DIR__, 3);
    $targetDir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ratib_cms_media';
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['ok' => false, 'path' => '', 'error' => 'Cannot create uploads/ratib_cms_media directory.'];
    }
    if (!is_writable($targetDir)) {
        return ['ok' => false, 'path' => '', 'error' => 'uploads/ratib_cms_media is not writable by PHP.'];
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string) pathinfo($name, PATHINFO_FILENAME));
    $safeBase = trim((string) $safeBase, '-');
    if ($safeBase === '') {
        $safeBase = $kind === 'video' ? 'video' : 'image';
    }
    $finalName = 'home-' . $kind . '-' . date('Ymd-His') . '-' . substr(md5(uniqid('', true)), 0, 8) . '-' . $safeBase . '.' . $ext;
    $abs = $targetDir . DIRECTORY_SEPARATOR . $finalName;
    if (!@move_uploaded_file($tmp, $abs)) {
        return ['ok' => false, 'path' => '', 'error' => 'Failed moving uploaded file.'];
    }
    @chmod($abs, 0644);

    return ['ok' => true, 'path' => 'uploads/ratib_cms_media/' . $finalName, 'error' => ''];
}

function ratib_control_site_content_try_delete_media_file(string $storedPath): void
{
    $storedPath = ltrim(str_replace('\\', '/', trim($storedPath)), '/');
    if ($storedPath === '' || strpos($storedPath, 'uploads/ratib_cms_media/') !== 0) {
        return;
    }
    $root = dirname(__DIR__, 3);
    $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);
    if (is_file($abs)) {
        @unlink($abs);
    }
}

/**
 * Monotonic revision for ratib_site_content rows (unix seconds as string).
 * Used to block stale-tab overwrites when multiple CMS tabs are open.
 */
function ratib_control_site_content_revision(?mysqli $ctrl): string
{
    if (!$ctrl instanceof mysqli) {
        return '';
    }
    try {
        $res = $ctrl->query("SELECT COALESCE(UNIX_TIMESTAMP(MAX(updated_at)), 0) AS rev FROM ratib_site_content");
        if ($res && ($row = $res->fetch_assoc())) {
            return (string) ($row['rev'] ?? '0');
        }
    } catch (Throwable $e) {
        // Ignore revision read failures; save path still has its own error handling.
    }

    return '';
}

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

$allowedKeys = array_keys(ratib_site_content_defaults_home());
$defaults = ratib_site_content_defaults_home();
$values = $defaults;
foreach (array_keys($defaults) as $k) {
    $values[$k] = ratib_site_content_get($k, $defaults[$k]);
}

$flashOk = false;
$flashErr = '';
$flashCacheWarn = '';
$pageRevision = ratib_control_site_content_revision($ctrl);
$ctrlDbFingerprint = function_exists('ratib_site_content_db_fingerprint')
    ? ratib_site_content_db_fingerprint($ctrl instanceof mysqli ? $ctrl : null)
    : '';

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
    } elseif ($pageRevision !== '' && (string) ($_POST['_rev'] ?? '') !== $pageRevision) {
        $flashErr = 'This editor tab is outdated (content changed in another tab/session). Refresh the page, then apply your edits again.';
    } else {
        $posted = $_POST['content'] ?? null;
        $posted = is_array($posted) ? $posted : [];
        $mediaMap = [
            'home.video.file' => 'video',
            'home.program.img1' => 'image',
            'home.program.img2' => 'image',
            'home.program.img3' => 'image',
        ];
        $deleteReq = $_POST['media_delete'] ?? [];
        $deleteReq = is_array($deleteReq) ? $deleteReq : [];
        foreach ($mediaMap as $mKey => $mKind) {
            $oldVal = trim((string) ($values[$mKey] ?? ''));
            if (isset($deleteReq[$mKey]) && (string) $deleteReq[$mKey] === '1') {
                $posted[$mKey] = '';
                ratib_control_site_content_try_delete_media_file($oldVal);
            }
            if (
                isset($_FILES['media_upload'])
                && isset($_FILES['media_upload']['error'][$mKey])
                && (int) $_FILES['media_upload']['error'][$mKey] !== UPLOAD_ERR_NO_FILE
            ) {
                $f = [
                    'name' => $_FILES['media_upload']['name'][$mKey] ?? '',
                    'type' => $_FILES['media_upload']['type'][$mKey] ?? '',
                    'tmp_name' => $_FILES['media_upload']['tmp_name'][$mKey] ?? '',
                    'error' => $_FILES['media_upload']['error'][$mKey] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['media_upload']['size'][$mKey] ?? 0,
                ];
                $up = ratib_control_site_content_store_media($f, $mKind);
                if (!$up['ok']) {
                    $flashErr = 'Media upload failed for ' . $mKey . ': ' . $up['error'];
                    break;
                }
                $posted[$mKey] = $up['path'];
                if ($oldVal !== '' && $oldVal !== $up['path']) {
                    ratib_control_site_content_try_delete_media_file($oldVal);
                }
            }
        }
        if ($flashErr !== '') {
            foreach (array_keys($defaults) as $k) {
                if (array_key_exists($k, $posted)) {
                    $values[$k] = is_string($posted[$k]) ? $posted[$k] : '';
                }
            }
            goto ratib_site_content_post_done;
        }
        $stmt = $ctrl->prepare(
            'INSERT INTO ratib_site_content (content_key, content_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_at = CURRENT_TIMESTAMP'
        );
        if ($stmt) {
            // mysqli_stmt::bind_param binds by reference — use fresh scalars each iteration (classic PHP pitfall).
            $saveOk = true;
            $saveErrMsg = '';
            foreach ($allowedKeys as $key) {
                if (array_key_exists($key, $posted)) {
                    $val = is_string($posted[$key]) ? $posted[$key] : '';
                    $val = str_replace(["\r\n", "\r"], "\n", $val);
                    $val = trim($val);
                } else {
                    $val = $values[$key];
                }
                $bindKey = $key;
                $bindVal = $val;
                $stmt->bind_param('ss', $bindKey, $bindVal);
                if (!$stmt->execute()) {
                    $saveOk = false;
                    $saveErrMsg = $stmt->error !== '' ? $stmt->error : ('MySQL error ' . (string) $stmt->errno);
                    error_log('ratib_site_content_save: execute failed for key ' . $key . ': ' . $saveErrMsg);
                    break;
                }
            }
            $stmt->close();
            if ($saveOk) {
                $flashOk = true;
                $pageRevision = ratib_control_site_content_revision($ctrl);
                foreach (array_keys($defaults) as $k) {
                    $values[$k] = ratib_site_content_get($k, $defaults[$k]);
                }
                if (function_exists('ratib_site_content_export_public_cache')) {
                    if (!ratib_site_content_export_public_cache()) {
                        $flashCacheWarn = 'Saved field rows, but the homepage snapshot could not be stored (no writable disk path and DB snapshot row failed). Check MySQL permissions for <code>ratib_site_content</code>, or fix filesystem permissions / set <code>RATIB_SITE_CONTENT_CACHE_FILE</code> — see <code>includes/site-content.php</code>.';
                    }
                }
            } else {
                $flashErr = 'Save failed: ' . htmlspecialchars($saveErrMsg, ENT_QUOTES, 'UTF-8');
            }
        } else {
            $flashErr = 'Could not prepare save statement.';
        }
    }
}
ratib_site_content_post_done:

$_SESSION['ratib_site_content_nonce'] = bin2hex(random_bytes(16));
$nonce = $_SESSION['ratib_site_content_nonce'];

// Must use site-root URL (not asset()/BASE_URL): css lives next to /css/control/system.css at project root.
// asset('css/...') becomes /control-panel/css/... which browsers resolve relative to /pages/control/ → doubled control-panel path + 404.
require_once __DIR__ . '/../../includes/control/request-url.php';
$ratibPublicRoot = function_exists('control_ratib_pro_public_base_url')
    ? control_ratib_pro_public_base_url()
    : preg_replace('#/control-panel$#', '', control_request_origin_base());
$ratibPublicRoot = rtrim((string) $ratibPublicRoot, '/');
$editorCss = ($ratibPublicRoot !== '' ? $ratibPublicRoot : '') . '/css/control/site-content-home-editor.css';

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Public site content', [$editorCss], []);

?>
<div class="ratib-site-content-editor ratib-site-content-editor--dark">
    <div class="ratib-site-content-intro mb-3">
        <strong><i class="fas fa-globe me-2"></i>Full public homepage copy</strong>
        <p class="mb-0 small text-muted">Edit English marketing text for <code>pages/home.php</code> (hero through footer). Values are stored as keys in <code>ratib_site_content</code> on the control database. Expand a section below—use <strong>Save</strong> at the bottom.</p>
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
<?php if ($flashCacheWarn !== ''): ?>
    <div class="alert alert-warning"><?php echo $flashCacheWarn; ?></div>
<?php endif; ?>
<?php if ($ctrlDbFingerprint !== ''): ?>
    <div class="small text-muted mb-2">DB fingerprint: <code><?php echo htmlspecialchars($ctrlDbFingerprint, ENT_QUOTES, 'UTF-8'); ?></code></div>
<?php endif; ?>
<?php if ($flashOk && $pageRevision !== ''): ?>
    <script>
    (function () {
        try {
            localStorage.setItem('ratib_cms_rev', <?php echo json_encode((string) $pageRevision); ?>);
            localStorage.setItem('ratib_cms_rev_ts', String(Date.now()));
        } catch (e) {}
    })();
    </script>
<?php endif; ?>

    <form method="post" action="" class="ratib-site-content-form" enctype="multipart/form-data">
        <input type="hidden" name="_nonce" value="<?php echo htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="_rev" value="<?php echo htmlspecialchars($pageRevision, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="ratib_site_content_save" value="1">

<?php
$groups = ratib_site_content_home_editor_groups();
foreach ($groups as $gx => $group) {
    $gid = htmlspecialchars((string) ($group['id'] ?? ''), ENT_QUOTES, 'UTF-8');
    $gtitle = htmlspecialchars((string) ($group['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $openFirst = ($gx === 0) ? ' open' : '';
    ?>
        <details class="ratib-site-content-details" id="sec-<?php echo $gid; ?>"<?php echo $openFirst; ?>>
            <summary><?php echo $gtitle; ?></summary>
            <div class="ratib-site-content-details__body">
    <?php
    foreach ($group['fields'] ?? [] as $field) {
        if (!isset($field['key'])) {
            continue;
        }
        ratib_control_site_content_render_field($field, $values);
    }
    if (!empty($group['repeat']) && is_array($group['repeat'])) {
        $r = $group['repeat'];
        $from = (int) ($r['from'] ?? 1);
        $to = (int) ($r['to'] ?? 1);
        $prefix = (string) ($r['prefix'] ?? '');
        for ($i = $from; $i <= $to; $i++) {
            foreach ($r['fields'] ?? [] as $sf) {
                $suffix = (string) ($sf['suffix'] ?? '');
                $key = $prefix . '.' . $i . $suffix;
                $label = sprintf((string) ($sf['label'] ?? ''), $i);
                $row = [
                    'key' => $key,
                    'label' => $label,
                    'type' => $sf['type'] ?? 'text',
                    'rows' => $sf['rows'] ?? 2,
                    'class' => $sf['class'] ?? '',
                ];
                ratib_control_site_content_render_field($row, $values);
            }
        }
    }
    ?>
            </div>
        </details>
<?php
}
?>

        <div class="ratib-site-content-actions">
<?php if ($tableOk && hasControlPermission('edit_control_system_settings')): ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save all</button>
<?php
    $ratibPublicHomeUrl = '/pages/home.php';
if (function_exists('control_ratib_pro_public_base_url')) {
    $ratibPublicHomeUrl = rtrim((string) control_ratib_pro_public_base_url(), '/') . '/pages/home.php';
}
if ($pageRevision !== '') {
    $ratibPublicHomeUrl .= (strpos($ratibPublicHomeUrl, '?') !== false ? '&' : '?') . 'cms_rev=' . rawurlencode($pageRevision);
}
?>
            <a href="<?php echo htmlspecialchars($ratibPublicHomeUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary ms-2">Open public home</a>
<?php elseif (!$tableOk): ?>
            <button type="button" class="btn btn-secondary" disabled>Save (create table first)</button>
<?php else: ?>
            <p class="text-muted small mb-0">You do not have permission to edit.</p>
<?php endif; ?>
        </div>
    </form>
</div>
<?php endControlLayout(); ?>

