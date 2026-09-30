<?php
declare(strict_types=1);

/** @var string $intent */
/** @var string $apkUrl */
/** @var string $code */
/** @var string $companyName */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
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
        a.btn { display: block; margin: 12px auto; max-width: 320px; padding: 14px 18px; border-radius: 12px; text-decoration: none; font-weight: 700; }
        a.primary { background: #14b8a6; color: #042f2e; }
        a.secondary { background: #1e293b; color: #e2e8f0; border: 1px solid #334155; }
        p { line-height: 1.5; color: #cbd5e1; }
        .mono { font-family: ui-monospace, monospace; font-size: 0.85rem; direction: ltr; }
    </style>
</head>
<body>
    <h1 style="font-size:1.25rem"><?php echo $e(__('mobile_activation_opening_app')); ?></h1>
    <p><?php echo $e(__('mobile_activation_handoff_body')); ?></p>
    <p class="mono"><?php echo $e($companyName); ?> · <?php echo $e($code); ?></p>
    <p class="mono">sa.rateb.hr.mobile · unified ~41 MB</p>
    <p class="mono" style="color:#f87171">NOT sa.rateb.hr.mobile.c51 (~22 MB · Al-Arfaj)</p>
    <a class="btn primary" id="rateb-open-hr" href="<?php echo $e($intent); ?>"><?php echo $e(__('mobile_activation_open_unified_hr')); ?></a>
    <?php if ($apkUrl !== '') { ?>
    <a class="btn secondary" href="<?php echo $e($apkUrl); ?>"><?php echo $e(__('mobile_activation_download_unified_hr')); ?> (sa.rateb.hr.mobile)</a>
    <?php } ?>
    <p style="font-size:0.8rem;margin-top:20px"><?php echo $e(__('mobile_activation_delete_branded_sibling')); ?></p>
    <p style="font-size:0.85rem;color:#fbbf24;margin-top:12px"><?php echo $e(__('mobile_activation_handoff_install_first')); ?></p>
    <script>
    (function () {
        var intent = <?php echo json_encode($intent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        function go() { try { window.location.href = intent; } catch (e) {} }
        document.getElementById('rateb-open-hr')?.addEventListener('click', function (ev) {
            ev.preventDefault();
            go();
        });
    })();
    </script>
</body>
</html>
