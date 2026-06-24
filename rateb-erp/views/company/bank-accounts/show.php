<?php
$item = $item ?? [];
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($item['name'] ?? ''); ?></span>
        <?php if (!empty($item['is_default'])) { ?>
        <span class="badge bg-primary"><?php echo __('default'); ?></span>
        <?php } ?>
    </div>
    <div class="rateb-card-body">
        <p class="mb-1"><strong><?php echo __('bank_name'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) ($item['bank_name'] ?? '—')); ?></p>
        <p class="mb-1"><strong><?php echo __('account_number'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) ($item['account_number'] ?? '—')); ?></p>
        <p class="mb-1"><strong><?php echo __('code'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) ($item['account_code'] ?? '—')); ?></p>
        <p class="mb-0"><strong><?php echo __('book_balance'); ?>:</strong> <?php echo number_format((float) ($item['book_balance'] ?? 0), 2); ?></p>
    </div>
</div>
<div class="d-flex flex-wrap gap-2">
    <a href="<?php echo rateb_app_url('bank-accounts'); ?>" class="btn btn-outline-secondary"><?php echo __('bank_accounts'); ?></a>
    <?php if ($canManage ?? false) { ?>
    <a href="<?php echo rateb_app_url('bank-accounts/' . (int) $item['id'] . '/edit'); ?>" class="btn btn-outline-primary"><i class="fas fa-edit"></i> <?php echo __('edit'); ?></a>
    <?php } ?>
</div>
