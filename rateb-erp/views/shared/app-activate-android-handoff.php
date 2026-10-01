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
$pkg = trim((string) ($androidPackage ?? ''));
if ($pkg === '') {
    $pkg = 'sa.rateb.hr.mobile';
}
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
        a.btn, button.btn { display: block; margin: 12px auto; max-width: 320px; padding: 14px 18px; border-radius: 12px; text-decoration: none; font-weight: 700; border: none; font-size: 1rem; cursor: pointer; width: 100%; }
        a.primary, button.primary { background: #14b8a6; color: #042f2e; }
        a.secondary { background: #1e293b; color: #e2e8f0; border: 1px solid #334155; }
        button.ghost { background: transparent; color: #94a3b8; border: 1px dashed #475569; }
        p { line-height: 1.5; color: #cbd5e1; }
        .mono { font-family: ui-monospace, monospace; font-size: 0.85rem; direction: ltr; }
        #copy-status { color: #4ade80; font-size: 0.85rem; min-height: 1.2em; }
    </style>
</head>
<body>
    <h1 style="font-size:1.25rem"><?php echo $e(__('mobile_activation_opening_app')); ?></h1>
    <?php if ($dedicated) { ?>
        <p><?php echo $e(__('mobile_activation_handoff_body_dedicated')); ?></p>
        <p class="mono"><?php echo $e($companyName); ?> · <?php echo $e($code); ?></p>
        <p class="mono"><?php echo $e($pkg); ?><?php if ($companyId > 0) { ?> · #<?php echo $e((string) $companyId); ?><?php } ?></p>
        <?php if ($apkUrl !== '') { ?>
        <a class="btn secondary" href="<?php echo $e($apkUrl); ?>"><?php echo $e(__('mobile_activation_download_company_hr')); ?></a>
        <?php } ?>
        <p style="font-size:0.85rem;color:#fbbf24;margin-top:12px"><?php echo $e(__('mobile_activation_handoff_install_first_dedicated')); ?></p>
        <button type="button" class="btn primary" id="rateb-open-hr"><?php echo $e(__('mobile_activation_open_company_hr')); ?></button>
        <p style="font-size:0.8rem;margin-top:20px"><?php echo $e(__('mobile_activation_handoff_dedicated_hint')); ?></p>
    <?php } else { ?>
        <p><?php echo $e(__('mobile_activation_handoff_body')); ?></p>
        <p class="mono"><?php echo $e($companyName); ?> · <?php echo $e($code); ?></p>
        <p class="mono">sa.rateb.hr.mobile · unified HR</p>
        <?php if ($apkUrl !== '') { ?>
        <a class="btn secondary" href="<?php echo $e($apkUrl); ?>"><?php echo $e(__('mobile_activation_download_unified_hr')); ?> (sa.rateb.hr.mobile)</a>
        <?php } ?>
        <p style="font-size:0.85rem;color:#fbbf24;margin-top:12px"><?php echo $e(__('mobile_activation_handoff_install_first')); ?></p>
        <button type="button" class="btn primary" id="rateb-open-hr"><?php echo $e(__('mobile_activation_open_unified_hr')); ?></button>
        <p style="font-size:0.8rem;margin-top:20px"><?php echo $e(__('mobile_activation_delete_branded_sibling')); ?></p>
    <?php } ?>
    <button type="button" class="btn ghost" id="rateb-copy-code"><?php echo $e(__('mobile_activation_copy_code')); ?></button>
    <p id="copy-status" class="mono" aria-live="polite"></p>
    <script>
    (function () {
        var intentRatebhr = <?php echo json_encode($intent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var intentHttps = <?php echo json_encode($intentHttps ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        var code = <?php echo json_encode($code, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        function openApp() {
            try { window.location.href = intentRatebhr; } catch (e) {}
            window.setTimeout(function () {
                if (intentHttps) { try { window.location.href = intentHttps; } catch (e2) {} }
            }, 700);
        }
        document.getElementById('rateb-open-hr')?.addEventListener('click', function () { openApp(); });
        document.getElementById('rateb-copy-code')?.addEventListener('click', function () {
            var msg = document.getElementById('copy-status');
            var text = code;
            function ok() { if (msg) msg.textContent = <?php echo json_encode(__('mobile_activation_copy_done'), JSON_UNESCAPED_UNICODE); ?> + ' ' + text; }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(ok).catch(function () { if (msg) msg.textContent = text; });
            } else if (msg) { msg.textContent = text; }
        });
    })();
    </script>
</body>
</html>
