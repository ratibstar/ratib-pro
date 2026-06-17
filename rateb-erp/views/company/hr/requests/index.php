<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array<string, mixed>> $fields */
$canManage = $actionsEnabled ?? ($canManage ?? true);
$lookups = (new \Rateb\App\Services\FormLookupService())->forFields($fields ?? []);
$employeeMap = [];
foreach ($lookups['employees'] ?? [] as $opt) {
    $employeeMap[(string) $opt['value']] = (string) $opt['label'];
}
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'employee-requests']);
?>
<?php if ($canManage) { ?>
<div class="mb-3">
    <a href="<?php echo rateb_url($routePrefix . '/create'); ?>" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> <?php echo __('create'); ?>
    </a>
</div>
<?php } ?>
<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('request_no'); ?></th>
                    <th><?php echo __('hr_employees'); ?></th>
                    <th><?php echo __('request_type'); ?></th>
                    <th><?php echo __('request_date'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('processed_at'); ?></th>
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
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['request_no'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($employeeMap[(string) ($row['employee_id'] ?? '')] ?? '—'); ?></td>
                    <td><?php echo __((string) ($row['request_type'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['request_date'] ?? '')); ?></td>
                    <td><?php echo __($status); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['processed_at'] ?? '—')); ?></td>
                    <td class="rateb-actions-cell text-nowrap">
                        <div class="rateb-actions">
                        <?php if ($canManage && $status === 'pending') { ?>
                        <form method="post" action="<?php echo rateb_url($routePrefix . '/' . (int) $row['id'] . '/approve'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success" title="<?php echo __('approve'); ?>"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="post" action="<?php echo rateb_url($routePrefix . '/' . (int) $row['id'] . '/reject'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning" title="<?php echo __('reject'); ?>"><i class="fas fa-times"></i></button>
                        </form>
                        <?php } ?>
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
