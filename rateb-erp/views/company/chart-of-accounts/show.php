<?php
$item = $item ?? [];
$name = rateb_locale() === 'ar' && !empty($item['name_ar']) ? $item['name_ar'] : ($item['name'] ?? '');
$parentLabel = '—';
if (!empty($item['parent_code'])) {
    $pName = rateb_locale() === 'ar' && !empty($item['parent_name_ar']) ? $item['parent_name_ar'] : ($item['parent_name'] ?? '');
    $parentLabel = $pName . ' (' . $item['parent_code'] . ')';
}
$type = (string) ($item['account_type'] ?? '');
$level = (int) ($item['account_level'] ?? 0);
if ($level < 1 && !empty($item['parent_code'])) {
    $level = max(1, strlen((string) ($item['code'] ?? '')) > 5 ? 3 : (strlen((string) ($item['code'] ?? '')) > 3 ? 2 : 1));
}
$statusLabel = ((int) ($item['is_active'] ?? 0) === 1) ? __('active') : __('inactive');
$cf = (string) ($item['cash_flow_class'] ?? 'unclassified');
$cfLabel = __('cash_flow_' . $cf);
if ($cfLabel === 'cash_flow_' . $cf) {
    $cfLabel = __('cash_flow_unclassified');
}
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo __('account_details'); ?></span>
        <span class="badge bg-secondary-subtle text-secondary"><?php echo __($type); ?></span>
    </div>
    <div class="rateb-card-body">
        <dl class="row mb-0 rateb-coa-detail">
            <dt class="col-sm-4 col-md-3 text-muted"><?php echo __('account_code'); ?></dt>
            <dd class="col-sm-8 col-md-9 fw-semibold"><?php echo Rateb\App\Core\View::escape($item['code'] ?? ''); ?></dd>

            <dt class="col-sm-4 col-md-3 text-muted"><?php echo __('account_name'); ?></dt>
            <dd class="col-sm-8 col-md-9"><?php echo Rateb\App\Core\View::escape($name); ?></dd>

            <dt class="col-sm-4 col-md-3 text-muted"><?php echo __('main_parent_account'); ?></dt>
            <dd class="col-sm-8 col-md-9"><?php echo Rateb\App\Core\View::escape($parentLabel); ?></dd>

            <dt class="col-sm-4 col-md-3 text-muted"><?php echo __('account_type'); ?></dt>
            <dd class="col-sm-8 col-md-9"><?php echo Rateb\App\Core\View::escape(__($type)); ?></dd>

            <dt class="col-sm-4 col-md-3 text-muted"><?php echo __('account_level'); ?></dt>
            <dd class="col-sm-8 col-md-9"><?php echo (int) $level; ?></dd>

            <dt class="col-sm-4 col-md-3 text-muted"><?php echo __('status'); ?></dt>
            <dd class="col-sm-8 col-md-9">
                <span class="badge <?php echo ((int) ($item['is_active'] ?? 0) === 1) ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'; ?>">
                    <?php echo Rateb\App\Core\View::escape($statusLabel); ?>
                </span>
            </dd>

            <dt class="col-sm-4 col-md-3 text-muted"><?php echo __('cash_flow_classification'); ?></dt>
            <dd class="col-sm-8 col-md-9"><?php echo Rateb\App\Core\View::escape($cfLabel); ?></dd>

            <dt class="col-sm-4 col-md-3 text-muted"><?php echo __('balance'); ?></dt>
            <dd class="col-sm-8 col-md-9 fw-semibold"><?php echo number_format((float) ($balance ?? 0), 2); ?></dd>
        </dl>
    </div>
    <div class="rateb-card-footer text-center text-muted small py-2">
        <?php echo __('developed_by_rateb_tech'); ?>
    </div>
</div>
<div class="d-flex flex-wrap gap-2">
    <a href="<?php echo rateb_app_url('chart-of-accounts'); ?>" class="btn btn-outline-secondary"><?php echo __('chart_of_accounts'); ?></a>
    <a href="<?php echo rateb_app_url('accounting/coa-tree'); ?>" class="btn btn-outline-secondary"><?php echo __('coa_full_tree'); ?></a>
    <?php if ($canManage ?? false) { ?>
    <a href="<?php echo rateb_app_url('chart-of-accounts/' . (int) $item['id'] . '/edit'); ?>" class="btn btn-outline-primary"><i class="fas fa-edit"></i> <?php echo __('edit'); ?></a>
    <?php } ?>
</div>
