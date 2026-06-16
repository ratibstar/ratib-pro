<?php
/** @var string|null $exportRoute */
if (empty($exportRoute)) {
    return;
}
$exportEnabled = $exportEnabled ?? true;
if (!$exportEnabled) {
    return;
}
$exportCsv = rateb_url_query((string) $exportRoute, ['format' => 'csv']);
$exportExcel = rateb_url_query((string) $exportRoute, ['format' => 'excel']);
$exportPdf = rateb_url_query((string) $exportRoute, ['format' => 'pdf']);
?>
<div class="d-flex flex-wrap gap-2<?php echo !empty($inline) ? '' : ' mb-3'; ?>">
    <a href="<?php echo Rateb\App\Core\View::escape($exportCsv); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv"></i> CSV</a>
    <a href="<?php echo Rateb\App\Core\View::escape($exportExcel); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> Excel</a>
    <a href="<?php echo Rateb\App\Core\View::escape($exportPdf); ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer"><i class="fas fa-file-pdf"></i> <?php echo __('print_save_pdf'); ?></a>
</div>
