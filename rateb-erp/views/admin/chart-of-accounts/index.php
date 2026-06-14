<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'admin']); ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0"><i class="fas fa-list me-2 text-primary"></i><?php echo __('chart_of_accounts'); ?></h5>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?php echo rateb_url('admin/coa-tree'); ?>" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-sitemap"></i> <?php echo __('coa_full_tree'); ?>
        </a>
        <?php if ($createEnabled ?? false) { ?>
        <a href="<?php echo rateb_url('admin/chart-of-accounts/create'); ?>" class="btn btn-primary btn-sm">
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
                    <?php if ($actionsEnabled ?? false) { ?><th></th><?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="<?php echo ($actionsEnabled ?? false) ? 6 : 5; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
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
                    <?php if ($actionsEnabled ?? false) { ?>
                    <td class="text-end">
                        <a href="<?php echo rateb_url('admin/chart-of-accounts/' . (int) $row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                    </td>
                    <?php } ?>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
