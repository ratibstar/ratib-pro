<?php
/** @var array<int, array<string, mixed>> $metrics */
$metrics = $metrics ?? [];
if ($metrics === []) {
    return;
}
?>
<div class="cm cm--page-stats mb-3" data-cm-module-stats="v1" aria-label="<?php echo __('key_metrics'); ?>">
    <?php Rateb\App\Core\View::partial('dashboard/metrics-strip', ['metrics' => $metrics]); ?>
</div>
