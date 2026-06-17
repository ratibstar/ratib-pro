<?php
/** @var array<int, array<string, mixed>> $items */
$canManage = $canManage ?? true;
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'payroll-list']);
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo __('hr_payroll'); ?></span>
        <?php if ($canManage) { ?>
        <a href="<?php echo rateb_url($routePrefix . '/create'); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('create_payroll_period'); ?>
        </a>
        <?php } ?>
    </div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('period_year'); ?></th>
                    <th><?php echo __('period_month'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="4" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    foreach ($items as $row) { ?>
                <tr>
                    <td><?php echo (int) ($row['period_year'] ?? 0); ?></td>
                    <td><?php echo (int) ($row['period_month'] ?? 0); ?></td>
                    <td><?php echo __((string) ($row['status'] ?? 'draft')); ?></td>
                    <td>
                        <a href="<?php echo rateb_url($routePrefix . '/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-eye"></i> <?php echo __('view'); ?>
                        </a>
                    </td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('pagination', ['page' => $page ?? 1, 'total' => $total ?? 0, 'limit' => $limit ?? 20, 'routePrefix' => $routePrefix ?? '']); ?>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
