<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array<string, mixed>> $fields */
$canManage = $actionsEnabled ?? ($canManage ?? true);
$lookups = (new \Rateb\App\Services\FormLookupService())->forFields($fields ?? []);
$employeeMap = [];
$typeMap = [];
foreach ($lookups['employees'] ?? [] as $opt) {
    $employeeMap[(string) $opt['value']] = (string) $opt['label'];
}
foreach ($lookups['leave_types'] ?? [] as $opt) {
    $typeMap[(string) $opt['value']] = (string) $opt['label'];
}
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'leave-requests']);
?>
<?php if ($canManage) { ?>
<div class="mb-3">
    <a href="<?php echo rateb_url($routePrefix . '/create'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> <?php echo __('hr_leave_submit'); ?>
    </a>
</div>
<?php } ?>
<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('hr_employees'); ?></th>
                    <th><?php echo __('leave_type'); ?></th>
                    <th><?php echo __('start_date'); ?></th>
                    <th><?php echo __('end_date'); ?></th>
                    <th><?php echo __('days'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    foreach ($items as $row) {
                        $status = (string) ($row['status'] ?? 'pending');
                        ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($employeeMap[(string) ($row['employee_id'] ?? '')] ?? ('#' . (int) ($row['employee_id'] ?? 0))); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($typeMap[(string) ($row['leave_type_id'] ?? '')] ?? '—'); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['start_date'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['end_date'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['days'] ?? '')); ?></td>
                    <td><?php echo __($status); ?></td>
                    <td class="rateb-actions-cell text-nowrap">
                        <div class="rateb-actions">
                        <?php Rateb\App\Core\View::partial('hr-pending-oversight', ['status' => $status]); ?>
                        <?php if ($canManage) { ?>
                        <a href="<?php echo rateb_url($routePrefix . '/' . (int) $row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <form method="post" action="<?php echo rateb_url($routePrefix . '/' . (int) $row['id'] . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php } ?>
                        </div>
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
