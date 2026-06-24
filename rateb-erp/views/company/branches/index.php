<?php
/** @var array{count:int,limit:int}|null $branchStats */
$branchStats = $branchStats ?? ['count' => 0, 'limit' => 0];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <span class="badge bg-primary fs-6"><?php echo __('branch_count_limit', [
        'count' => (int) ($branchStats['count'] ?? 0),
        'limit' => (int) ($branchStats['limit'] ?? 0),
    ]); ?></span>
    <?php if (!empty($exportRoute)) {
        Rateb\App\Core\View::partial('export-toolbar', [
            'exportRoute' => $exportRoute,
            'exportEnabled' => $exportEnabled ?? true,
            'inline' => true,
        ]);
    } ?>
</div>
<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
