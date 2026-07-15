<?php
/** @var array<int, array<string, mixed>> $items */
/** @var string $routePrefix */
/** @var bool $canManage */
/** @var string $search */
$canManage = !empty($canManage);
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <span><?php echo Rateb\App\Core\View::escape($title ?? __('company_permissions')); ?></span>
            <p class="form-text mb-0 mt-1"><?php echo __('company_permissions_help'); ?></p>
        </div>
        <a href="<?php echo rateb_url('admin/companies'); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-building"></i> <?php echo __('companies'); ?>
        </a>
    </div>
    <div class="rateb-card-body">
        <form method="get" action="<?php echo rateb_url($routePrefix); ?>" class="row g-2 mb-3">
            <div class="col-md-6 col-lg-4">
                <input type="search" name="q" class="form-control" value="<?php echo Rateb\App\Core\View::escape($search ?? ''); ?>"
                       placeholder="<?php echo Rateb\App\Core\View::escape(__('search')); ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-primary"><?php echo __('search'); ?></button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('id'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('company_permissions_modules_count'); ?></th>
                    <th><?php echo __('company_permissions_modules_summary'); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($items === []) { ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) {
                    $cid = (int) ($row['id'] ?? 0);
                    $status = (string) ($row['status'] ?? '');
                    $statusBadge = match ($status) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        default => 'info',
                    };
                    ?>
                <tr>
                    <td><?php echo $cid; ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></td>
                    <td><span class="badge bg-<?php echo $statusBadge; ?>"><?php echo __($status !== '' ? $status : 'pending'); ?></span></td>
                    <td>
                        <?php echo (int) ($row['modules_count'] ?? 0); ?>
                        /
                        <?php echo (int) ($row['modules_total'] ?? 0); ?>
                    </td>
                    <td class="text-muted small"><?php echo Rateb\App\Core\View::escape((string) ($row['modules_summary'] ?? '—')); ?></td>
                    <td class="text-nowrap">
                        <?php if ($canManage) { ?>
                        <a href="<?php echo rateb_url($routePrefix . '/' . $cid); ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-sliders"></i> <?php echo __('company_permissions_manage'); ?>
                        </a>
                        <?php } ?>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('pagination', [
    'page' => $page ?? 1,
    'total' => $total ?? 0,
    'limit' => $limit ?? rateb_list_per_page(),
    'routePrefix' => $routePrefix ?? '',
    'preserveQuery' => array_filter(['q' => $search ?? '']),
]); ?>
