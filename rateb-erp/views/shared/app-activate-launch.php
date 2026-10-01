<?php
declare(strict_types=1);

/** @var string $appLabel */
/** @var string $apkUrl */
/** @var string $intentUrl */
/** @var string $schemeUrl */
/** @var string $backUrl */
/** @var string $activationCode */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$apkUrl = trim($apkUrl);
?>
<div class="text-center rateb-act-launch">
    <p class="mb-1"><i class="fas fa-circle-notch fa-spin"></i> <?php echo $e(__('mobile_activation_opening_app')); ?></p>
    <p class="small text-muted mb-3"><bdi><?php echo $e($appLabel); ?></bdi></p>
    <?php if ($apkUrl !== '') { ?>
        <a class="btn btn-primary w-100 mb-2" href="<?php echo $e($apkUrl); ?>" data-rateb-full-nav="1">
            <i class="fas fa-download"></i> <?php echo $e(__('mobile_activation_download_apk_short')); ?>
        </a>
    <?php } ?>
    <a class="btn btn-outline-secondary w-100" href="<?php echo $e($backUrl); ?>" data-rateb-full-nav="1">
        <?php echo $e(__('back')); ?>
    </a>
    <p class="small text-muted mt-3 mb-1"><?php echo $e(__('mobile_activation_launch_hint')); ?></p>
    <p class="small mb-1"><?php echo $e(__('mobile_activation_launch_manual_code')); ?></p>
    <p class="font-monospace fs-5 mb-0" dir="ltr"><?php echo $e($activationCode); ?></p>
</div>
<script>
(function () {
    'use strict';
    var steps = [];
    var intentUrl = <?php echo json_encode(trim($intentUrl), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var schemeUrl = <?php echo json_encode(trim($schemeUrl), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    if (intentUrl && /Android/i.test(navigator.userAgent || '')) {
        steps.push(intentUrl);
    } else if (schemeUrl) {
        steps.push(schemeUrl);
    }
    steps.forEach(function (url, i) {
        window.setTimeout(function () {
            if (!document.hidden) {
                window.location.assign(url);
            }
        }, 150 + i * 900);
    });
})();
</script>
