<?php
declare(strict_types=1);

/** @var string $intent */
/** @var string $intentHttps */
/** @var string $apkUrl */
/** @var string $code */
/** @var string $companyName */
/** @var bool $dedicatedCompanyApp */
/** @var string $androidPackage */
/** @var int $companyId */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$dedicated = !empty($dedicatedCompanyApp);
$apkReady = trim((string) ($apkUrl ?? '')) !== '';
$pkg = trim((string) ($androidPackage ?? ''));
if ($pkg === '') {
    $pkg = 'sa.rateb.hr.mobile';
}
$oneBtnLabel = $dedicated
    ? __('mobile_activation_one_button_company')
    : __('mobile_activation_one_button_unified');
?>
<!DOCTYPE html>
<html lang="<?php echo $e(rateb_locale()); ?>" dir="<?php echo rateb_locale() === 'ar' ? 'rtl' : 'ltr'; ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo $e(__('mobile_activation_opening_app')); ?></title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 24px; background: #0f172a; color: #f8fafc; text-align: center; }
        button.btn { display: block; margin: 16px auto; max-width: 340px; padding: 16px 20px; border-radius: 12px; font-weight: 700; border: none; font-size: 1.05rem; cursor: pointer; width: 100%; background: #14b8a6; color: #042f2e; }
        p { line-height: 1.5; color: #cbd5e1; }
        .mono { font-family: ui-monospace, monospace; font-size: 0.85rem; direction: ltr; }
        .warn { color: #fbbf24; font-size: 0.85rem; margin-top: 12px; }
        #action-status { color: #4ade80; font-size: 0.85rem; min-height: 1.2em; margin-top: 8px; }
    </style>
</head>
<body>
    <h1 style="font-size:1.25rem"><?php echo $e(__('mobile_activation_opening_app')); ?></h1>
    <p class="mono"><?php echo $e($companyName); ?> · <?php echo $e($code); ?></p>
    <p class="mono"><?php echo $e($pkg); ?><?php if ($companyId > 0) { ?> · #<?php echo $e((string) $companyId); ?><?php } ?></p>
    <?php if ($dedicated && !$apkReady) { ?>
        <p class="warn"><?php echo $e(__('mobile_apps_apk_not_built_body')); ?></p>
    <?php } ?>
    <p><?php echo $e($dedicated ? __('mobile_activation_handoff_body_dedicated') : __('mobile_activation_handoff_body')); ?></p>
    <button type="button" class="btn" id="rateb-install-open"><?php echo $e($oneBtnLabel); ?></button>
    <p id="action-status" class="mono" aria-live="polite"></p>
    <p class="warn"><?php echo $e(__('mobile_activation_one_button_hint')); ?></p>
    <script>
    (function () {
        var intentRatebhr = <?php echo json_encode($intent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var intentHttps = <?php echo json_encode($intentHttps ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var apkDirect = <?php echo json_encode($apkUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var statusMsg = <?php echo json_encode(__('mobile_activation_one_button_working'), JSON_UNESCAPED_UNICODE); ?>;
        function installOrOpen() {
            var status = document.getElementById('action-status');
            if (status) status.textContent = statusMsg;
            try { window.location.href = intentRatebhr; } catch (e) {}
            window.setTimeout(function () {
                if (intentHttps) {
                    try { window.location.href = intentHttps; } catch (e2) {}
                }
            }, 600);
            window.setTimeout(function () {
                if (apkDirect) {
                    try { window.location.href = apkDirect; } catch (e3) {}
                }
            }, 1400);
        }
        document.getElementById('rateb-install-open')?.addEventListener('click', installOrOpen);
    })();
    </script>
</body>
</html>
