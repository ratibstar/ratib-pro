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
            <table class="table rateb-table align-middle mb-0">
                <thead>
                <tr>
                    <th style="width:4rem"><?php echo __('id'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th style="width:7rem"><?php echo __('status'); ?></th>
                    <th style="width:8rem" class="text-nowrap"><?php echo __('company_permissions_modules_count'); ?></th>
                    <th><?php echo __('company_permissions_modules_summary'); ?></th>
                    <th style="width:10rem" class="text-nowrap"><?php echo __('actions'); ?></th>
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
                    $modCount = (int) ($row['modules_count'] ?? 0);
                    $modTotal = (int) ($row['modules_total'] ?? 0);
                    $summary = (string) ($row['modules_summary'] ?? '—');
                    $summaryFull = (string) ($row['modules_summary_full'] ?? $summary);
                    ?>
                <tr>
                    <td><?php echo $cid; ?></td>
                    <td class="fw-semibold"><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></td>
                    <td><span class="badge bg-<?php echo $statusBadge; ?>"><?php echo __($status !== '' ? $status : 'pending'); ?></span></td>
                    <td class="text-nowrap">
                        <span class="badge bg-secondary-subtle text-body border" dir="ltr" style="unicode-bidi:isolate">
                            <?php echo $modCount; ?> / <?php echo $modTotal; ?>
                        </span>
                    </td>
                    <td style="max-width:28rem" title="<?php echo Rateb\App\Core\View::escape($summaryFull); ?>">
                        <span class="text-muted small d-inline-block text-truncate w-100" dir="auto" style="max-width:100%">
                            <?php echo Rateb\App\Core\View::escape($summary); ?>
                        </span>
                    </td>
                    <td class="text-nowrap">
                        <?php if ($canManage) { ?>
                        <a href="<?php echo rateb_url($routePrefix . '/' . $cid); ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-sliders-h"></i>
                            <span><?php echo __('company_permissions_manage'); ?></span>
                        </a>
                        <?php } else { ?>
                        <span class="text-muted small">—</span>
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
