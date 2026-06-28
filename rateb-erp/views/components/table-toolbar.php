<?php
/** @var string|null $exportRoute */
/** @var bool|null $exportEnabled */
$tableTitle = trim((string) ($tableTitle ?? ''));
$exportRoute = trim((string) ($exportRoute ?? ''));
$exportEnabled = !empty($exportEnabled);
$showServerExport = $exportEnabled && $exportRoute !== '';
?>
<div class="rateb-table-toolbar d-flex flex-wrap align-items-center justify-content-between gap-2 px-2 py-2 border-bottom">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-sm btn-outline-secondary" data-rateb-table-print title="<?php echo Rateb\App\Core\View::escape(__('print')); ?>">
            <i class="fas fa-print"></i> <?php echo __('print'); ?>
        </button>
        <button type="button" class="btn btn-sm btn-outline-success" data-rateb-table-csv title="CSV">
            <i class="fas fa-file-csv"></i> CSV
        </button>
        <?php if ($showServerExport) {
            Rateb\App\Core\View::partial('export-toolbar', [
                'exportRoute' => $exportRoute,
                'exportEnabled' => true,
                'inline' => true,
            ]);
        } ?>
    </div>
    <?php if ($tableTitle !== '') { ?>
    <span class="small text-muted"><?php echo Rateb\App\Core\View::escape($tableTitle); ?></span>
    <?php } ?>
</div>
