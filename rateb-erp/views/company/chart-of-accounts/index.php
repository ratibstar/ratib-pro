<?php
$canManage = $canManage ?? false;
$routePrefix = $routePrefix ?? rateb_app_route('chart-of-accounts');
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i><?php echo __('chart_of_accounts'); ?></h5>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?php echo rateb_app_url('accounting/coa-tree'); ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-sitemap"></i> <?php echo __('coa_full_tree'); ?>
        </a>
        <?php if ($canManage) { ?>
        <a href="<?php echo rateb_app_url('chart-of-accounts/create'); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('add_account'); ?>
        </a>
        <?php } ?>
    </div>
</div>

<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('parent_account'); ?></th>
                    <th><?php echo __('account_type'); ?></th>
                    <th class="text-end"><?php echo __('balance'); ?></th>
                    <th class="text-end"><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) {
                    $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : ($row['name'] ?? '');
                    $parentLabel = '—';
                    if (!empty($row['parent_code'])) {
                        $pName = rateb_locale() === 'ar' && !empty($row['parent_name_ar']) ? $row['parent_name_ar'] : ($row['parent_name'] ?? '');
                        $parentLabel = $row['parent_code'] . ' — ' . $pName;
                    }
                    ?>
                <tr>
                    <td class="fw-semibold"><?php echo Rateb\App\Core\View::escape($row['code']); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                    <td class="small text-muted"><?php echo Rateb\App\Core\View::escape($parentLabel); ?></td>
                    <td><span class="badge bg-secondary-subtle text-secondary"><?php echo __((string) ($row['account_type'] ?? '')); ?></span></td>
                    <td class="text-end"><?php echo number_format((float) ($row['balance'] ?? 0), 2); ?></td>
                    <td class="text-end text-nowrap">
                        <a href="<?php echo rateb_app_url('chart-of-accounts/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-info" title="<?php echo __('view'); ?>"><i class="fas fa-eye"></i></a>
                        <?php if ($canManage) { ?>
                        <a href="<?php echo rateb_app_url('chart-of-accounts/' . (int) $row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
                        <?php } ?>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
