<?php
/** @var string|null $exportRoute */
if (empty($exportRoute)) {
    return;
}
$exportEnabled = $exportEnabled ?? true;
if (!$exportEnabled) {
    return;
}
?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="<?php echo Rateb\App\Core\View::escape($exportRoute . '?format=csv'); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv"></i> CSV</a>
    <a href="<?php echo Rateb\App\Core\View::escape($exportRoute . '?format=excel'); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> Excel</a>
    <a href="<?php echo Rateb\App\Core\View::escape($exportRoute . '?format=pdf'); ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-file-pdf"></i> PDF / Print</a>
</div>
