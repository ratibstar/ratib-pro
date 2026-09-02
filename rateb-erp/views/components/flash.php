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
// Ops edit/create/show already authorized: never show orphan soft-nav «access_denied».
if (is_string($error) && $error !== '') {
    $deniedMsg = function_exists('__') ? (string) __('access_denied') : '';
    $isDenied = ($deniedMsg !== '' && $error === $deniedMsg)
        || str_contains($error, 'ليس لديك صلاحية')
        || stripos($error, 'do not have permission') !== false;
    if ($isDenied && preg_match('#/admin/ops/.+/(edit|create|show)(/|\?|$)#i', (string) ($_SERVER['REQUEST_URI'] ?? ''))) {
        // True denials redirect away before this view; reaching the form = stale flash.
        $error = null;
    }
}
// Checkout already explains the locked module — never show a leftover plan banner
// (often naming a *different* module after prefetch/warm of /admin/hr etc.).
if (is_string($error) && $error !== '') {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $isPlanFlash = str_contains($error, 'غير مشمولة')
        || stripos($error, 'not included in your current plan') !== false;
    if ($isPlanFlash && preg_match('#/admin/billing/modules/#i', $uri)) {
        $error = null;
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
