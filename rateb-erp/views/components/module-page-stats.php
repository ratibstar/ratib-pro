<?php
/** @var array<int, array<string, mixed>> $metrics */
/** @var bool $async */
/** @var string $metricsRoute */
/** @var string $metricsUrl */
$metrics = $metrics ?? [];
$async = !empty($async);
$metricsRoute = trim((string) ($metricsRoute ?? ''), '/');
$metricsUrl = (string) ($metricsUrl ?? '');
if (!$async && $metrics === []) {
    return;
}
?>
<div class="cm cm--page-stats mb-3<?php echo $async ? ' is-loading' : ''; ?>"
     data-cm-module-stats="v1"
     <?php if ($async) { ?>
     data-module-metrics-async="1"
     data-module-metrics-url="<?php echo Rateb\App\Core\View::escape($metricsUrl); ?>"
     data-metrics-label="<?php echo Rateb\App\Core\View::escape(__('key_metrics')); ?>"
     <?php } ?>
     aria-label="<?php echo __('key_metrics'); ?>">
    <?php if ($async) { ?>
    <div class="cm-strip cm-strip--skeleton" aria-hidden="true">
        <?php for ($i = 0; $i < 5; $i++) { ?>
        <article class="cm-strip__item cm-strip__item--skeleton">
            <span class="cm-strip__lbl cm-skel-line"></span>
            <span class="cm-strip__val cm-skel-line cm-skel-line--wide"></span>
        </article>
        <?php } ?>
    </div>
    <?php } else { ?>
    <?php Rateb\App\Core\View::partial('dashboard/metrics-strip', ['metrics' => $metrics]); ?>
    <?php } ?>
</div>
