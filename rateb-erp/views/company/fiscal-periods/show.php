<?php
$item = $item ?? [];
$st = (string) ($item['status'] ?? '');
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($item['name'] ?? ''); ?></span>
        <span class="badge bg-<?php echo $st === 'open' ? 'success' : 'secondary'; ?>"><?php echo __($st); ?></span>
    </div>
    <div class="rateb-card-body">
        <p class="mb-1"><strong><?php echo __('date_from'); ?>:</strong> <?php echo Rateb\App\Core\View::formatDate($item['start_date'] ?? ''); ?></p>
        <p class="mb-0"><strong><?php echo __('date_to'); ?>:</strong> <?php echo Rateb\App\Core\View::formatDate($item['end_date'] ?? ''); ?></p>
    </div>
</div>
<div class="d-flex flex-wrap gap-2">
    <a href="<?php echo rateb_app_url('fiscal-periods'); ?>" class="btn btn-outline-secondary"><?php echo __('fiscal_periods'); ?></a>
</div>
