<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<?php
/** @var array<string, mixed> $item */
$id = (int) ($item['id'] ?? 0);
$isDraft = (string) ($item['status'] ?? '') === 'draft';
$statusKey = 'depreciation_status_' . (string) ($item['status'] ?? 'draft');
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <a href="<?php echo rateb_app_url('asset-depreciation'); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
    </div>
    <div class="rateb-card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4"><?php echo __('depreciation_no'); ?></dt>
            <dd class="col-sm-8 rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($item['depreciation_no'] ?? '')); ?></dd>
            <dt class="col-sm-4"><?php echo __('assets'); ?></dt>
            <dd class="col-sm-8"><?php echo Rateb\App\Core\View::escape((string) ($item['asset_name'] ?? '')); ?></dd>
            <dt class="col-sm-4"><?php echo __('depreciation_date'); ?></dt>
            <dd class="col-sm-8"><?php echo Rateb\App\Core\View::formatDate((string) ($item['period_date'] ?? '')); ?></dd>
            <dt class="col-sm-4"><?php echo __('depreciation_amount'); ?></dt>
            <dd class="col-sm-8 rateb-ltr-num"><?php echo number_format((float) ($item['amount'] ?? 0), 2); ?></dd>
            <dt class="col-sm-4"><?php echo __('book_value_before'); ?></dt>
            <dd class="col-sm-8 rateb-ltr-num"><?php echo number_format((float) ($item['book_value_before'] ?? 0), 2); ?></dd>
            <dt class="col-sm-4"><?php echo __('book_value_after'); ?></dt>
            <dd class="col-sm-8 rateb-ltr-num"><?php echo number_format((float) ($item['book_value'] ?? 0), 2); ?></dd>
            <dt class="col-sm-4"><?php echo __('status'); ?></dt>
            <dd class="col-sm-8">
                <span class="badge bg-<?php echo $isDraft ? 'warning' : 'success'; ?>"><?php echo __($statusKey); ?></span>
            </dd>
        </dl>
        <?php if (($canManage ?? false) && $isDraft) { ?>
        <div class="mt-4 d-flex flex-wrap gap-2">
            <a href="<?php echo rateb_app_url('asset-depreciation/' . $id . '/edit'); ?>" class="btn btn-primary"><i class="fas fa-edit"></i> <?php echo __('edit'); ?></a>
            <form method="post" action="<?php echo rateb_app_url('asset-depreciation/' . $id . '/approve'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> <?php echo __('approve'); ?></button>
            </form>
        </div>
        <?php } ?>
    </div>
</div>
