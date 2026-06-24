<?php
$item = $item ?? [];
$name = rateb_locale() === 'ar' && !empty($item['name_ar']) ? $item['name_ar'] : ($item['name'] ?? '');
$parentLabel = '—';
if (!empty($item['parent_code'])) {
    $pName = rateb_locale() === 'ar' && !empty($item['parent_name_ar']) ? $item['parent_name_ar'] : ($item['parent_name'] ?? '');
    $parentLabel = $item['parent_code'] . ' — ' . $pName;
}
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($item['code'] ?? ''); ?></span>
        <span class="badge bg-secondary-subtle text-secondary"><?php echo __((string) ($item['account_type'] ?? '')); ?></span>
    </div>
    <div class="rateb-card-body">
        <p class="mb-1"><strong><?php echo __('name'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($name); ?></p>
        <p class="mb-1"><strong><?php echo __('parent_account'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($parentLabel); ?></p>
        <p class="mb-0"><strong><?php echo __('balance'); ?>:</strong> <?php echo number_format((float) ($balance ?? 0), 2); ?></p>
    </div>
</div>
<div class="d-flex flex-wrap gap-2">
    <a href="<?php echo rateb_app_url('chart-of-accounts'); ?>" class="btn btn-outline-secondary"><?php echo __('chart_of_accounts'); ?></a>
    <?php if ($canManage ?? false) { ?>
    <a href="<?php echo rateb_app_url('chart-of-accounts/' . (int) $item['id'] . '/edit'); ?>" class="btn btn-outline-primary"><i class="fas fa-edit"></i> <?php echo __('edit'); ?></a>
    <?php } ?>
</div>
