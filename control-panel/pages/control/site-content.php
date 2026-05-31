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
require_once __DIR__ . '/../../includes/control/request-url.php';

function ratib_control_site_content_media_preview_url(string $val): string
{
    $val = trim($val);
    if ($val === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $val)) {
        return $val;
    }
    // Must use public site root (not /control-panel/...): media is served from /public/cms-media.php at app root.
    $baseUrl = '';
    if (function_exists('control_ratib_pro_public_base_url')) {
        $baseUrl = rtrim((string) control_ratib_pro_public_base_url(), '/');
    }
    if ($baseUrl === '' && defined('SITE_URL') && (string) SITE_URL !== '') {
        $baseUrl = rtrim((string) SITE_URL, '/');
    }
    if ($baseUrl === '' && defined('RATIB_PRO_URL') && (string) RATIB_PRO_URL !== '') {
        $baseUrl = rtrim((string) RATIB_PRO_URL, '/');
    }
    if ($baseUrl === '') {
        $baseUrl = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
        if ($baseUrl !== '' && str_contains($baseUrl, '/control-panel')) {
            $baseUrl = preg_replace('#/control-panel/?$#', '', $baseUrl) ?? $baseUrl;
        }
    }
    if ($baseUrl === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $baseUrl = $host !== '' ? ($scheme . '://' . $host) : '';
    }
    if ($baseUrl === '') {
        return '';
    }
    if (function_exists('ratib_site_content_media_public_url')) {
        $tok = ratib_site_content_media_public_url($baseUrl, $val);
        if ($tok !== '') {
            return $tok;
        }
    }
    $rel = ltrim(str_replace('\\', '/', $val), '/');

    return rtrim($baseUrl, '/') . '/' . $rel;
}

function ratib_control_site_content_slot_src_is_image(string $src): bool
{
    if (function_exists('ratib_site_content_media_stored_is_image')) {
        return ratib_site_content_media_stored_is_image($src);
    }
    $ext = strtolower((string) pathinfo(basename(str_replace('\\', '/', $src)), PATHINFO_EXTENSION));

    return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true);
}

/**
 * @return 'video'|'image'|''
 */
function ratib_control_site_content_video_slot_upload_kind(array $file): string
{
    $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
        return 'video';
    }
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
        return 'image';
    }

    return '';
}

/**
 * English-labelled upload control (native file inputs follow the browser/OS language).
 *
 * @param string $fieldName e.g. program_slot_upload[]
 */
function ratib_control_site_content_render_slot_file_field(string $fieldName, string $accept): void
{
    echo '<div class="ratib-fake-file-wrap mb-2" data-ratib-fake-file translate="no">';
    echo '<div class="small text-muted mb-1" lang="en">Upload file (optional)</div>';
    echo '<div class="input-group input-group-sm ratib-fake-file-inputgroup">';
    echo '<button type="button" class="btn btn-outline-secondary ratib-fake-file-btn" lang="en">Browse…</button>';
    echo '<span class="form-control ratib-fake-file-label text-truncate" lang="en">No file chosen</span>';
    echo '</div>';
    echo '<input type="file" class="ratib-real-file visually-hidden" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '" accept="' . htmlspecialchars($accept, ENT_QUOTES, 'UTF-8') . '" tabindex="-1" aria-label="Upload file">';
    echo '</div>';
}

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
            $previewUrl = ratib_control_site_content_media_preview_url((string) $val);
            if ($previewUrl !== '') {
                if ($type === 'media_video') {
                    echo '<video controls preload="metadata" style="max-width:260px;max-height:146px;border-radius:10px;background:#060b19"><source src="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '"></video>';
                } else {
                    echo '<img src="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '" alt="Preview" style="max-width:220px;max-height:140px;object-fit:cover;border-radius:10px;border:1px solid rgba(255,255,255,.15);">';
                }
            }
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

    $targetDir = function_exists('ratib_site_content_media_storage_dir')
        ? ratib_site_content_media_storage_dir()
        : (dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ratib_cms_media');
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['ok' => false, 'path' => '', 'error' => 'Cannot create media directory: ' . $targetDir];
    }
    if (!is_writable($targetDir)) {
        return ['ok' => false, 'path' => '', 'error' => 'Media directory is not writable by PHP: ' . $targetDir];
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

    $token = function_exists('ratib_site_content_media_token_from_filename')
        ? ratib_site_content_media_token_from_filename($finalName)
        : ('scmedia:' . $finalName);

    return ['ok' => true, 'path' => $token, 'error' => ''];
}

/**
 * Stable filename per CMS key so re-upload replaces the same asset.
 *
 * @return array{ok:bool,path:string,error:string}
 */
function ratib_control_site_content_store_media_for_key(array $file, string $contentKey): array
{
    if (!isset($file['tmp_name'], $file['name'], $file['error'])) {
        return ['ok' => false, 'path' => '', 'error' => 'Invalid upload payload.'];
    }
    $err = (int) $file['error'];
    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'path' => '', 'error' => 'Upload failed (error ' . $err . ').'];
    }
    $ext = strtolower((string) pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $allow = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    if ($ext === '' || !in_array($ext, $allow, true)) {
        return ['ok' => false, 'path' => '', 'error' => 'Unsupported image type.'];
    }
    $finalName = function_exists('ratib_site_content_media_filename_for_key')
        ? ratib_site_content_media_filename_for_key($contentKey, $ext)
        : ('cms-' . preg_replace('/[^a-z0-9]+/i', '-', $contentKey) . '.' . $ext);

    return ratib_control_site_content_store_media_named($file, $finalName);
}

/**
 * @return array{ok:bool,path:string,error:string}
 */
function ratib_control_site_content_store_media_named(array $file, string $finalName): array
{
    $finalName = basename(str_replace('\\', '/', $finalName));
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $finalName)) {
        return ['ok' => false, 'path' => '', 'error' => 'Invalid target filename.'];
    }
    $tmp = (string) $file['tmp_name'];
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'path' => '', 'error' => 'Upload temp file missing.'];
    }
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    if ($size <= 0 || $size > 12 * 1024 * 1024) {
        return ['ok' => false, 'path' => '', 'error' => 'Image must be under 12 MB.'];
    }
    $targetDir = function_exists('ratib_site_content_media_storage_dir')
        ? ratib_site_content_media_storage_dir()
        : (dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ratib_cms_media');
    if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        return ['ok' => false, 'path' => '', 'error' => 'Cannot create media directory.'];
    }
    if (!is_writable($targetDir)) {
        return ['ok' => false, 'path' => '', 'error' => 'Media directory is not writable.'];
    }
    $abs = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $finalName;
    if (is_file($abs)) {
        @unlink($abs);
    }
    if (!@move_uploaded_file($tmp, $abs)) {
        return ['ok' => false, 'path' => '', 'error' => 'Failed moving uploaded file.'];
    }
    @chmod($abs, 0644);
    $token = function_exists('ratib_site_content_media_token_from_filename')
        ? ratib_site_content_media_token_from_filename($finalName)
        : ('scmedia:' . $finalName);

    return ['ok' => true, 'path' => $token, 'error' => ''];
}

/**
 * @param array<string, mixed> $posted
 * @param array<string, mixed> $files
 * @param array<string, mixed> $post
 * @param list<string> $allowedKeys
 */
function ratib_control_site_content_apply_media_fields_post(array &$posted, array $files, array $post, array $allowedKeys): string
{
    $allowed = array_flip($allowedKeys);
    $uploads = $files['media_upload'] ?? null;
    if (is_array($uploads) && isset($uploads['name']) && is_array($uploads['name'])) {
        foreach ($uploads['name'] as $key => $origName) {
            if (!is_string($key) || !isset($allowed[$key])) {
                continue;
            }
            $err = isset($uploads['error'][$key]) ? (int) $uploads['error'][$key] : UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = [
                'name' => $uploads['name'][$key] ?? '',
                'type' => $uploads['type'][$key] ?? '',
                'tmp_name' => $uploads['tmp_name'][$key] ?? '',
                'error' => $err,
                'size' => isset($uploads['size'][$key]) ? (int) $uploads['size'][$key] : 0,
            ];
            $prev = trim((string) ($posted[$key] ?? ''));
            if ($prev !== '') {
                ratib_control_site_content_try_delete_media_file($prev);
            }
            $up = ratib_control_site_content_store_media_for_key($file, $key);
            if (!$up['ok']) {
                return 'Image upload failed (' . $key . '): ' . $up['error'];
            }
            $posted[$key] = $up['path'];
        }
    }
    $deletes = $post['media_delete'] ?? null;
    if (is_array($deletes)) {
        foreach ($deletes as $key => $flag) {
            if (!is_string($key) || !isset($allowed[$key]) || !$flag) {
                continue;
            }
            $prev = trim((string) ($posted[$key] ?? ''));
            if ($prev !== '') {
                ratib_control_site_content_try_delete_media_file($prev);
            }
            $posted[$key] = '';
        }
    }

    return '';
}

function ratib_control_site_content_try_delete_media_file(string $storedPath): void
{
    $storedPath = trim($storedPath);
    if ($storedPath === '') {
        return;
    }

    $mediaDir = function_exists('ratib_site_content_media_storage_dir')
        ? ratib_site_content_media_storage_dir()
        : (dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ratib_cms_media');

    $name = function_exists('ratib_site_content_media_filename_from_token')
        ? ratib_site_content_media_filename_from_token($storedPath)
        : '';
    if ($name !== '') {
        $abs = rtrim($mediaDir, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (is_file($abs)) {
            @unlink($abs);
        }

        return;
    }

    if (preg_match('#^https?://#i', $storedPath)) {
        return;
    }

    $rel = str_replace('\\', '/', ltrim($storedPath, '/'));
    if ($rel === '' || strpos($rel, '..') !== false) {
        return;
    }

    $projectRoot = dirname(__DIR__, 3);
    $abs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $realRoot = realpath($projectRoot);
    $realAbs = @realpath($abs);
    if ($realRoot !== false && $realAbs !== false && strpos($realAbs, $realRoot) === 0 && is_file($realAbs)) {
        @unlink($realAbs);
    }
}

/**
 * Remove legacy flat keys from DB after saves use slots_json only — otherwise merge overlays resurrect “deleted” media.
 */
function ratib_control_site_content_purge_legacy_home_media_rows(mysqli $ctrl): bool
{
    if (!function_exists('ratib_site_content_home_legacy_media_db_keys')
        || !function_exists('ratib_site_content_key_allowed')) {
        return false;
    }
    $keys = ratib_site_content_home_legacy_media_db_keys();
    foreach (array_chunk($keys, 80) as $chunk) {
        $parts = [];
        foreach ($chunk as $k) {
            if (!ratib_site_content_key_allowed((string) $k)) {
                continue;
            }
            $parts[] = "'" . $ctrl->real_escape_string((string) $k) . "'";
        }
        if ($parts === []) {
            continue;
        }
        $sql = 'DELETE FROM ratib_site_content WHERE content_key IN (' . implode(',', $parts) . ')';
        if (!$ctrl->query($sql)) {
            error_log('ratib_control_site_content_purge_legacy_home_media_rows: ' . $ctrl->error);

            return false;
        }
    }

    return true;
}

/**
 * @param array<string, mixed> $posted
 * @param array<string, string> $priorValues
 *
 * @return string Error message or ''
 */
function ratib_control_site_content_apply_program_slots_post(array &$posted, array $files, array $post): string
{
    if (!function_exists('ratib_site_content_home_program_slots_from_flat')) {
        return '';
    }
    $captions = isset($post['program_slot_caption']) && is_array($post['program_slot_caption']) ? $post['program_slot_caption'] : [];
    $alts = isset($post['program_slot_alt']) && is_array($post['program_slot_alt']) ? $post['program_slot_alt'] : [];
    $srcsIn = isset($post['program_slot_src']) && is_array($post['program_slot_src']) ? $post['program_slot_src'] : [];
    $prevs = isset($post['program_slot_prev_src']) && is_array($post['program_slot_prev_src']) ? $post['program_slot_prev_src'] : [];
    $dels = isset($post['program_slot_delete_media']) && is_array($post['program_slot_delete_media']) ? $post['program_slot_delete_media'] : [];

    $n = max(count($captions), count($alts), count($srcsIn), count($prevs), count($dels));
    if (isset($files['program_slot_upload']['name']) && is_array($files['program_slot_upload']['name'])) {
        $n = max($n, count($files['program_slot_upload']['name']));
    }

    $items = [];
    for ($i = 0; $i < $n; $i++) {
        $cap = trim((string) ($captions[$i] ?? ''));
        $alt = trim((string) ($alts[$i] ?? ''));
        $src = trim((string) ($srcsIn[$i] ?? ''));
        $prev = trim((string) ($prevs[$i] ?? ''));

        if (isset($dels[$i]) && (string) $dels[$i] === '1') {
            $toDel = $prev !== '' ? $prev : $src;
            ratib_control_site_content_try_delete_media_file($toDel);
            $src = '';
        }

        $fe = isset($files['program_slot_upload']['error'][$i]) ? (int) $files['program_slot_upload']['error'][$i] : UPLOAD_ERR_NO_FILE;
        if ($fe === UPLOAD_ERR_OK) {
            $f = [
                'name' => $files['program_slot_upload']['name'][$i] ?? '',
                'type' => $files['program_slot_upload']['type'][$i] ?? '',
                'tmp_name' => $files['program_slot_upload']['tmp_name'][$i] ?? '',
                'error' => $fe,
                'size' => isset($files['program_slot_upload']['size'][$i]) ? (int) $files['program_slot_upload']['size'][$i] : 0,
            ];
            $up = ratib_control_site_content_store_media($f, 'image');
            if (!$up['ok']) {
                return 'Program image upload failed (row ' . (string) ($i + 1) . '): ' . $up['error'];
            }
            if ($prev !== '' && $prev !== $up['path']) {
                ratib_control_site_content_try_delete_media_file($prev);
            }
            $src = $up['path'];
        }

        if ($cap === '' && $alt === '' && $src === '') {
            continue;
        }
        $items[] = ['caption' => $cap, 'alt' => $alt, 'src' => $src];
    }

    $posted['home.program.slots_json'] = json_encode($items, JSON_UNESCAPED_UNICODE);

    return '';
}

/**
 * @param array<string, mixed> $posted
 *
 * @return string Error message or ''
 */
function ratib_control_site_content_apply_video_slots_post(array &$posted, array $files, array $post): string
{
    $srcsIn = isset($post['video_slot_src']) && is_array($post['video_slot_src']) ? $post['video_slot_src'] : [];
    $prevs = isset($post['video_slot_prev_src']) && is_array($post['video_slot_prev_src']) ? $post['video_slot_prev_src'] : [];
    $dels = isset($post['video_slot_delete_media']) && is_array($post['video_slot_delete_media']) ? $post['video_slot_delete_media'] : [];

    $n = max(count($srcsIn), count($prevs), count($dels));
    if (isset($files['video_slot_upload']['name']) && is_array($files['video_slot_upload']['name'])) {
        $n = max($n, count($files['video_slot_upload']['name']));
    }

    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $src = trim((string) ($srcsIn[$i] ?? ''));
        $prev = trim((string) ($prevs[$i] ?? ''));

        if (isset($dels[$i]) && (string) $dels[$i] === '1') {
            $toDel = $prev !== '' ? $prev : $src;
            ratib_control_site_content_try_delete_media_file($toDel);
            $src = '';
        }

        $fe = isset($files['video_slot_upload']['error'][$i]) ? (int) $files['video_slot_upload']['error'][$i] : UPLOAD_ERR_NO_FILE;
        if ($fe === UPLOAD_ERR_OK) {
            $f = [
                'name' => $files['video_slot_upload']['name'][$i] ?? '',
                'type' => $files['video_slot_upload']['type'][$i] ?? '',
                'tmp_name' => $files['video_slot_upload']['tmp_name'][$i] ?? '',
                'error' => $fe,
                'size' => isset($files['video_slot_upload']['size'][$i]) ? (int) $files['video_slot_upload']['size'][$i] : 0,
            ];
            $kind = ratib_control_site_content_video_slot_upload_kind($f);
            if ($kind === '') {
                $badExt = strtolower((string) pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));

                return 'Video/image upload failed (row ' . (string) ($i + 1) . '): Unsupported file type: ' . $badExt;
            }
            $up = ratib_control_site_content_store_media($f, $kind);
            if (!$up['ok']) {
                return ucfirst($kind) . ' upload failed (row ' . (string) ($i + 1) . '): ' . $up['error'];
            }
            if ($prev !== '' && $prev !== $up['path']) {
                ratib_control_site_content_try_delete_media_file($prev);
            }
            $src = $up['path'];
        }

        if ($src !== '') {
            $rows[] = ['src' => $src];
        }
    }

    $posted['home.video.slots_json'] = json_encode($rows, JSON_UNESCAPED_UNICODE);

    return '';
}

/**
 * Editor UI: show every slot returned by the resolver (unlimited). Empty starter row if there is nothing yet.
 *
 * @param list<array{caption:string, alt:string, src:string}> $rows
 *
 * @return list<array{caption:string, alt:string, src:string}>
 */
function ratib_control_site_content_program_rows_for_editor(array $rows): array
{
    if ($rows === []) {
        return [['caption' => '', 'alt' => '', 'src' => '']];
    }

    return $rows;
}

/**
 * @param list<string> $srcs
 *
 * @return list<string>
 */
function ratib_control_site_content_video_srcs_for_editor(array $srcs): array
{
    $fil = [];
    foreach ($srcs as $s) {
        if (trim((string) $s) !== '') {
            $fil[] = (string) $s;
        }
    }

    return $fil === [] ? [''] : $fil;
}

/**
 * @param array<string, string> $values
 */
function ratib_control_site_content_render_program_slots_editor(array $values): void
{
    if (!function_exists('ratib_site_content_home_program_slots_from_flat')) {
        return;
    }
    $rows = ratib_control_site_content_program_rows_for_editor(
        ratib_site_content_home_program_slots_from_flat($values)
    );
    echo '<div class="ratib-cms-slots ratib-cms-slots--program border rounded p-3 mb-2 bg-dark bg-opacity-25" translate="no">';
    echo '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">';
    echo '<p class="small text-muted mb-0 flex-grow-1" lang="en">Program preview images (unlimited). Each row is one card on the public homepage. Use <strong>Add row</strong> / <strong>Remove row</strong>. Saving stores the full list in <code>home.program.slots_json</code>.</p>';
    echo '<button type="button" class="btn btn-sm btn-outline-light flex-shrink-0" data-ratib-slot-add="program" lang="en"><i class="fas fa-plus" aria-hidden="true"></i> Add row</button>';
    echo '</div>';
    echo '<div id="ratib-program-slots-rows">';
    foreach ($rows as $idx => $row) {
        ratib_control_site_content_render_program_slot_row($idx, $row);
    }
    echo '</div>';
    echo '<button type="button" class="btn btn-sm btn-outline-light mt-2" id="ratib-program-slot-add" data-ratib-slot-add="program" lang="en"><i class="fas fa-plus me-1" aria-hidden="true"></i>Add row</button>';
    echo '</div>';
}

/**
 * @param array{caption?:string, alt?:string, src?:string} $row
 */
function ratib_control_site_content_render_program_slot_row(int $idx, array $row): void
{
    $cap = htmlspecialchars((string) ($row['caption'] ?? ''), ENT_QUOTES, 'UTF-8');
    $alt = htmlspecialchars((string) ($row['alt'] ?? ''), ENT_QUOTES, 'UTF-8');
    $src = htmlspecialchars((string) ($row['src'] ?? ''), ENT_QUOTES, 'UTF-8');
    $prev = htmlspecialchars((string) ($row['src'] ?? ''), ENT_QUOTES, 'UTF-8');
    echo '<div class="ratib-cms-slot-row border-bottom border-secondary pb-3 mb-3" data-slot-row="program">';
    echo '<div class="small text-muted mb-1">Image #' . (string) ($idx + 1) . '</div>';
    echo '<div class="mb-2"><label class="form-label">Caption</label><input type="text" class="form-control form-control-sm" name="program_slot_caption[]" value="' . $cap . '" maxlength="65000"></div>';
    echo '<div class="mb-2"><label class="form-label">Alt text</label><input type="text" class="form-control form-control-sm" name="program_slot_alt[]" value="' . $alt . '" maxlength="65000"></div>';
    echo '<div class="mb-2"><label class="form-label">Image URL / path / token</label><input type="text" class="form-control form-control-sm font-monospace" name="program_slot_src[]" value="' . $src . '" maxlength="65000"></div>';
    echo '<input type="hidden" name="program_slot_prev_src[]" value="' . $prev . '">';
    ratib_control_site_content_render_slot_file_field('program_slot_upload[]', 'image/jpeg,image/png,image/webp,image/gif,image/svg+xml');
    echo '<input type="hidden" name="program_slot_delete_media[]" value="0" class="ratib-slot-del-hidden">';
    if (trim((string) ($row['src'] ?? '')) !== '') {
        $previewUrl = ratib_control_site_content_media_preview_url((string) ($row['src'] ?? ''));
        if ($previewUrl !== '') {
            echo '<div class="mb-2"><img src="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="max-width:180px;max-height:100px;object-fit:cover;border-radius:8px;border:1px solid rgba(255,255,255,.15);"></div>';
        }
        echo '<label class="form-check-label small"><input class="form-check-input ratib-slot-del-cb me-1" type="checkbox" value="1"> Delete uploaded file for this row</label>';
    }
    echo '<div class="mt-2"><button type="button" class="btn btn-sm btn-outline-danger" data-ratib-slot-remove="program" lang="en"><i class="fas fa-minus me-1" aria-hidden="true"></i>Remove row</button></div>';
    echo '</div>';
}

/**
 * @param array<string, string> $values
 */
function ratib_control_site_content_render_video_slots_editor(array $values): void
{
    if (!function_exists('ratib_site_content_home_video_src_strings_from_flat')) {
        return;
    }
    $srcs = ratib_control_site_content_video_srcs_for_editor(
        ratib_site_content_home_video_src_strings_from_flat($values)
    );
    echo '<div class="ratib-cms-slots ratib-cms-slots--video border rounded p-3 mb-2 bg-dark bg-opacity-25" translate="no">';
    echo '<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">';
    echo '<p class="small text-muted mb-0 flex-grow-1" lang="en">Videos and images on the public homepage. MP4 / WebM / MOV or JPG / PNG / WebP. Empty placeholders are hidden — use <strong>Add row</strong> for another clip. <strong>Remove row</strong> removes that slot when you Save.</p>';
    echo '<button type="button" class="btn btn-sm btn-outline-light flex-shrink-0" data-ratib-slot-add="video" lang="en"><i class="fas fa-plus" aria-hidden="true"></i> Add row</button>';
    echo '</div>';
    echo '<div id="ratib-video-slots-rows">';
    foreach ($srcs as $idx => $sv) {
        ratib_control_site_content_render_video_slot_row($idx, (string) $sv);
    }
    echo '</div>';
    echo '<button type="button" class="btn btn-sm btn-outline-light mt-2" id="ratib-video-slot-add" data-ratib-slot-add="video" lang="en"><i class="fas fa-plus me-1" aria-hidden="true"></i>Add row</button>';
    echo '</div>';
}

function ratib_control_site_content_render_video_slot_row(int $idx, string $src): void
{
    $es = htmlspecialchars($src, ENT_QUOTES, 'UTF-8');
    echo '<div class="ratib-cms-slot-row border-bottom border-secondary pb-3 mb-3" data-slot-row="video">';
    echo '<div class="small text-muted mb-1">Video / image #' . (string) ($idx + 1) . '</div>';
    echo '<div class="mb-2"><label class="form-label">Media URL / path / token</label><input type="text" class="form-control form-control-sm font-monospace" name="video_slot_src[]" value="' . $es . '" maxlength="65000"></div>';
    echo '<input type="hidden" name="video_slot_prev_src[]" value="' . $es . '">';
    ratib_control_site_content_render_slot_file_field('video_slot_upload[]', 'video/mp4,video/webm,video/quicktime,image/jpeg,image/png,image/webp,image/gif');
    echo '<input type="hidden" name="video_slot_delete_media[]" value="0" class="ratib-slot-del-hidden">';
    if (trim($src) !== '') {
        $previewUrl = ratib_control_site_content_media_preview_url($src);
        if ($previewUrl !== '') {
            if (ratib_control_site_content_slot_src_is_image($src)) {
                echo '<div class="mb-2"><img src="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '" alt="" style="max-width:260px;max-height:146px;object-fit:cover;border-radius:10px;border:1px solid rgba(255,255,255,.15);"></div>';
            } else {
                echo '<div class="mb-2"><video controls preload="metadata" style="max-width:260px;max-height:146px;border-radius:10px;background:#060b19"><source src="' . htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') . '"></video></div>';
            }
        }
        echo '<label class="form-check-label small"><input class="form-check-input ratib-slot-del-cb me-1" type="checkbox" value="1"> Delete uploaded file for this row</label>';
    }
    echo '<div class="mt-2"><button type="button" class="btn btn-sm btn-outline-danger" data-ratib-slot-remove="video" lang="en"><i class="fas fa-minus me-1" aria-hidden="true"></i>Remove row</button></div>';
    echo '</div>';
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
if (function_exists('ratib_site_content_home_merge_legacy_media_into_values')) {
    $values = ratib_site_content_home_merge_legacy_media_into_values($values);
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
        $slotErr = ratib_control_site_content_apply_program_slots_post($posted, $_FILES, $_POST);
        if ($slotErr === '') {
            $slotErr = ratib_control_site_content_apply_video_slots_post($posted, $_FILES, $_POST);
        }
        if ($slotErr !== '') {
            $flashErr = $slotErr;
        }
        if ($flashErr === '') {
            $mediaErr = ratib_control_site_content_apply_media_fields_post($posted, $_FILES, $_POST, $allowedKeys);
            if ($mediaErr !== '') {
                $flashErr = $mediaErr;
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
                ratib_control_site_content_purge_legacy_home_media_rows($ctrl);
                $flashOk = true;
                $pageRevision = ratib_control_site_content_revision($ctrl);
                foreach (array_keys($defaults) as $k) {
                    $values[$k] = ratib_site_content_get($k, $defaults[$k]);
                }
                if (function_exists('ratib_site_content_home_merge_legacy_media_into_values')) {
                    $values = ratib_site_content_home_merge_legacy_media_into_values($values);
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
$ratibPublicRoot = function_exists('control_ratib_pro_public_base_url')
    ? control_ratib_pro_public_base_url()
    : preg_replace('#/control-panel$#', '', control_request_origin_base());
$ratibPublicRoot = rtrim((string) $ratibPublicRoot, '/');
$editorCss = ($ratibPublicRoot !== '' ? $ratibPublicRoot : '') . '/css/control/site-content-home-editor.css';

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout('Public site content — full site', [$editorCss], []);

?>
<div class="ratib-site-content-editor ratib-site-content-editor--dark" lang="en">
    <div class="ratib-site-content-intro mb-3">
        <strong><i class="fas fa-globe me-2"></i>Public site content — entire marketing site</strong>
        <p class="mb-2 small text-muted">Single CMS for the entire public site: <strong>marketing home</strong>, <strong>company profile</strong> (government screenshots, diagrams, platform screens), <strong>architecture</strong>, <strong>security</strong>, and <strong>procurement</strong>. For any image field, use <strong>Choose file</strong> then <strong>Save all</strong> — uploads go to the server automatically (no FTP). Text and paths are stored in <code>ratib_site_content</code>.</p>
        <?php if (function_exists('ratib_site_content_public_page_links')) {
            foreach (ratib_site_content_public_page_links() as $plink) { ?>
        <a class="btn btn-sm btn-outline-light me-1 mb-1" href="<?php echo htmlspecialchars($plink['path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($plink['label'], ENT_QUOTES, 'UTF-8'); ?></a>
        <?php }
        } ?>
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
    if (!empty($group['intro'])) {
        echo '<p class="small text-muted mb-3">' . (string) $group['intro'] . '</p>';
    }
    foreach ($group['fields'] ?? [] as $field) {
        if (!isset($field['key'])) {
            continue;
        }
        ratib_control_site_content_render_field($field, $values);
    }
    $renderSlots = isset($group['render_slots']) ? (string) $group['render_slots'] : '';
    if ($renderSlots === 'program') {
        ratib_control_site_content_render_program_slots_editor($values);
    } elseif ($renderSlots === 'video') {
        ratib_control_site_content_render_video_slots_editor($values);
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
if (function_exists('ratib_site_content_public_page_links')) {
    foreach (ratib_site_content_public_page_links() as $ratibPreviewLink) {
        $ratibPreviewUrl = (string) ($ratibPreviewLink['path'] ?? '');
        if ($ratibPreviewUrl === '') {
            continue;
        }
        if ($pageRevision !== '') {
            $ratibPreviewUrl .= (strpos($ratibPreviewUrl, '?') !== false ? '&' : '?') . 'cms_rev=' . rawurlencode($pageRevision);
        }
        ?>
            <a href="<?php echo htmlspecialchars($ratibPreviewUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary ms-2 mb-1"><?php echo htmlspecialchars((string) ($ratibPreviewLink['label'] ?? 'Preview'), ENT_QUOTES, 'UTF-8'); ?></a>
<?php
    }
} else {
    $ratibPublicHomeUrl = '/pages/home.php';
    if (function_exists('control_ratib_pro_public_base_url')) {
        $ratibPublicHomeUrl = rtrim((string) control_ratib_pro_public_base_url(), '/') . '/pages/home.php';
    }
    if ($pageRevision !== '') {
        $ratibPublicHomeUrl .= (strpos($ratibPublicHomeUrl, '?') !== false ? '&' : '?') . 'cms_rev=' . rawurlencode($pageRevision);
    }
    ?>
            <a href="<?php echo htmlspecialchars($ratibPublicHomeUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-secondary ms-2">Open public home</a>
<?php
}
?>
<?php elseif (!$tableOk): ?>
            <button type="button" class="btn btn-secondary" disabled>Save (create table first)</button>
<?php else: ?>
            <p class="text-muted small mb-0">You do not have permission to edit.</p>
<?php endif; ?>
        </div>
    </form>
</div>
<script>
(function () {
    function syncDeleteHidden(row) {
        if (!row) return;
        var hidden = row.querySelector('.ratib-slot-del-hidden');
        var cb = row.querySelector('.ratib-slot-del-cb');
        if (hidden && cb) hidden.value = cb.checked ? '1' : '0';
    }
    function bindRow(row) {
        if (!row) return;
        var cb = row.querySelector('.ratib-slot-del-cb');
        if (cb && !cb._ratibDelBound) {
            cb._ratibDelBound = true;
            cb.addEventListener('change', function () { syncDeleteHidden(row); });
        }
        syncDeleteHidden(row);
    }
    function resetFakeFile(row) {
        var real = row.querySelector('.ratib-real-file');
        if (real) real.value = '';
        var lab = row.querySelector('.ratib-fake-file-label');
        if (lab) lab.textContent = 'No file chosen';
    }
    function clearProgramRow(row) {
        row.querySelectorAll('input[type="text"], textarea').forEach(function (el) { el.value = ''; });
        resetFakeFile(row);
        var prev = row.querySelector('input[name="program_slot_prev_src[]"]');
        if (prev) prev.value = '';
        var hidden = row.querySelector('.ratib-slot-del-hidden');
        if (hidden) hidden.value = '0';
        var cb = row.querySelector('.ratib-slot-del-cb');
        if (cb) cb.checked = false;
        row.querySelectorAll('.mb-2 img, .mb-2 video, label.form-check-label').forEach(function (n) { n.remove(); });
    }
    function clearVideoRow(row) {
        row.querySelectorAll('input[type="text"]').forEach(function (el) { el.value = ''; });
        resetFakeFile(row);
        var prev = row.querySelector('input[name="video_slot_prev_src[]"]');
        if (prev) prev.value = '';
        var hidden = row.querySelector('.ratib-slot-del-hidden');
        if (hidden) hidden.value = '0';
        var cb = row.querySelector('.ratib-slot-del-cb');
        if (cb) cb.checked = false;
        row.querySelectorAll('.mb-2 img, .mb-2 video, label.form-check-label').forEach(function (n) { n.remove(); });
    }
    document.querySelectorAll('.ratib-cms-slot-row[data-slot-row]').forEach(bindRow);
    document.addEventListener('change', function (ev) {
        var t = ev.target;
        if (!t || !t.classList || !t.classList.contains('ratib-real-file')) return;
        var wrap = t.closest('[data-ratib-fake-file]');
        var label = wrap && wrap.querySelector('.ratib-fake-file-label');
        var f = t.files && t.files[0];
        if (label) label.textContent = f ? f.name : 'No file chosen';
    });
    document.addEventListener('click', function (ev) {
        var fakeBtn = ev.target && ev.target.closest ? ev.target.closest('.ratib-fake-file-btn') : null;
        if (fakeBtn) {
            ev.preventDefault();
            var wrap = fakeBtn.closest('[data-ratib-fake-file]');
            var real = wrap && wrap.querySelector('.ratib-real-file');
            if (real) real.click();
            return;
        }
        var addBtn = ev.target && ev.target.closest ? ev.target.closest('[data-ratib-slot-add]') : null;
        if (addBtn) {
            var kind = addBtn.getAttribute('data-ratib-slot-add') || '';
            var wrap = kind === 'program' ? document.getElementById('ratib-program-slots-rows') : document.getElementById('ratib-video-slots-rows');
            if (!wrap) return;
            var protoSel = kind === 'program' ? '.ratib-cms-slot-row[data-slot-row="program"]' : '.ratib-cms-slot-row[data-slot-row="video"]';
            var proto = wrap.querySelector(protoSel);
            if (!proto) return;
            var clone = proto.cloneNode(true);
            if (kind === 'program') clearProgramRow(clone); else clearVideoRow(clone);
            var n = wrap.querySelectorAll(protoSel).length;
            var label = clone.querySelector('.small.text-muted.mb-1');
            if (label) label.textContent = (kind === 'program' ? 'Image #' : 'Video #') + (n + 1);
            wrap.appendChild(clone);
            bindRow(clone);
            var inp = clone.querySelector('input[type="text"], textarea');
            if (inp && inp.focus) inp.focus();
            return;
        }
        var rm = ev.target && ev.target.closest ? ev.target.closest('[data-ratib-slot-remove]') : null;
        if (rm) {
            var row = rm.closest('.ratib-cms-slot-row');
            var container = row ? row.parentElement : null;
            if (!row || !container) return;
            var kind = rm.getAttribute('data-ratib-slot-remove') || '';
            var protoSel = kind === 'program' ? '.ratib-cms-slot-row[data-slot-row="program"]' : '.ratib-cms-slot-row[data-slot-row="video"]';
            if (container.querySelectorAll(protoSel).length <= 1) {
                if (kind === 'program') clearProgramRow(row); else clearVideoRow(row);
                bindRow(row);
                return;
            }
            row.remove();
            return;
        }
    });
    document.querySelectorAll('.ratib-site-content-form').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('.ratib-cms-slot-row[data-slot-row]').forEach(syncDeleteHidden);
        });
    });
})();
</script>
<?php endControlLayout(); ?>

