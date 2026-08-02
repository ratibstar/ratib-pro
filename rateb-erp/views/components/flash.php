<?php
$success = Rateb\App\Core\SessionManager::flash('success');
$error = Rateb\App\Core\SessionManager::flash('error');
$warning = Rateb\App\Core\SessionManager::flash('warning');
// Suppress legacy platform-host flash leftovers on agency trees (replaced by in-app notification).
if (is_string($error) && $error !== '') {
    $platformMsg = function_exists('__') ? (string) __('platform_oversight_host_only') : '';
    if ($platformMsg !== '' && (str_contains($error, 'rateb.sa') || $error === $platformMsg)) {
        $error = null;
    }
    if ($error !== null && strtolower(trim($error)) === 'error') {
        $error = function_exists('__') ? (string) __('system_error_generic') : $error;
    }
}
?>
<?php if ($success) { ?>
<div class="alert alert-success rateb-flash alert-dismissible fade show" role="alert">
    <?php echo Rateb\App\Core\View::escape($success); ?>
    <button type="button" class="btn-close" aria-label="Close"></button>
</div>
<?php } ?>
<?php if ($warning) { ?>
<div class="alert alert-warning rateb-flash alert-dismissible fade show" role="alert">
    <?php echo Rateb\App\Core\View::escape($warning); ?>
    <button type="button" class="btn-close" aria-label="Close"></button>
</div>
<?php } ?>
<?php if ($error) { ?>
<div class="alert alert-danger rateb-flash alert-dismissible fade show" role="alert">
    <?php echo Rateb\App\Core\View::escape($error); ?>
    <button type="button" class="btn-close" aria-label="Close"></button>
</div>
<?php } ?>
