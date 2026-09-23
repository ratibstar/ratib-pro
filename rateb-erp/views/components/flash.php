<?php
$success = Rateb\App\Core\SessionManager::flash('success');
$error = Rateb\App\Core\SessionManager::flash('error');
$errorTitle = Rateb\App\Core\SessionManager::flash('error_title');
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
if ((!is_string($errorTitle) || $errorTitle === '') && is_string($error) && $error !== '') {
    $dupEmail = function_exists('__') ? (string) __('db_duplicate_email') : '';
    if ($dupEmail !== '' && $error === $dupEmail) {
        $errorTitle = function_exists('__') ? (string) __('db_duplicate_email_title') : 'البريد مستخدم';
    }
}
if (!is_string($errorTitle) || $errorTitle === '') {
    $errorTitle = function_exists('__') ? (string) __('db_error_title') : 'تعذّر إكمال العملية';
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
<div class="modal fade rateb-flash-error-modal" id="ratebFlashErrorModal" tabindex="-1" aria-labelledby="ratebFlashErrorModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rateb-flash-error-modal__content">
            <div class="modal-header rateb-flash-error-modal__header">
                <h5 class="modal-title" id="ratebFlashErrorModalTitle"><?php echo Rateb\App\Core\View::escape($errorTitle); ?></h5>
                <button type="button" class="btn-close rateb-flash-error-modal__close" data-bs-dismiss="modal" aria-label="<?php echo Rateb\App\Core\View::escape(__('close')); ?>"></button>
            </div>
            <div class="modal-body rateb-flash-error-modal__body">
                <?php echo Rateb\App\Core\View::escape($error); ?>
            </div>
            <div class="modal-footer rateb-flash-error-modal__footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?php echo Rateb\App\Core\View::escape(__('ok')); ?></button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function showRatebFlashErrorModal() {
        var el = document.getElementById('ratebFlashErrorModal');
        if (!el) return;
        if (window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(el).show();
            return;
        }
        el.classList.add('show');
        el.style.display = 'block';
        el.setAttribute('aria-hidden', 'false');
        if (!document.querySelector('.modal-backdrop.rateb-flash-error-backdrop')) {
            var backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show rateb-flash-error-backdrop';
            document.body.appendChild(backdrop);
        }
        document.body.classList.add('modal-open');
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showRatebFlashErrorModal);
    } else {
        showRatebFlashErrorModal();
    }
})();
</script>
<?php } ?>
