<?php
declare(strict_types=1);

/** @var string $app */
/** @var string $appLabel */
/** @var string $apkUrl */
/** @var string $intentUrl */
/** @var string $schemeUrl */
/** @var string $backUrl */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$apkUrl = trim($apkUrl);
$intentUrl = trim($intentUrl);
$schemeUrl = trim($schemeUrl);
?>
<div class="text-center rateb-act-launch" data-rateb-act-launch="1">
    <p class="mb-2"><i class="fas fa-circle-notch fa-spin"></i> <?php echo $e(__('mobile_activation_opening_app')); ?></p>
    <p class="small text-muted mb-3"><bdi><?php echo $e($appLabel); ?></bdi></p>
    <?php if ($apkUrl !== '') { ?>
        <a class="btn btn-primary w-100 mb-2" id="rateb-act-launch-dl" href="<?php echo $e($apkUrl); ?>"
           data-rateb-full-nav="1" download>
            <i class="fas fa-download"></i> <?php echo $e(__('download')); ?>
        </a>
    <?php } ?>
    <a class="btn btn-outline-secondary w-100" href="<?php echo $e($backUrl); ?>" data-rateb-full-nav="1">
        <?php echo $e(__('back')); ?>
    </a>
    <p class="small text-muted mt-3 mb-0"><?php echo $e(__('mobile_activation_launch_hint')); ?></p>
</div>
<script>
(function () {
    'use strict';
    var android = /Android/i.test(navigator.userAgent || '');
    var intentUrl = <?php echo json_encode($intentUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var schemeUrl = <?php echo json_encode($schemeUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var apkUrl = <?php echo json_encode($apkUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var steps = [];
    if (android && intentUrl) {
        steps.push(intentUrl);
    }
    if (schemeUrl) {
        steps.push(schemeUrl);
    }
    var i = 0;
    function run() {
        if (i >= steps.length) {
            if (apkUrl && !document.hidden) {
                var dl = document.getElementById('rateb-act-launch-dl');
                if (dl) {
                    dl.focus();
                }
            }
            return;
        }
        var url = steps[i++];
        try {
            window.location.assign(url);
        } catch (e) {}
        window.setTimeout(run, 850);
    }
    window.setTimeout(run, 120);
})();
</script>
