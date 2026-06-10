<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array<string, mixed>>|null $fields */
/** @var string $routePrefix */
/** @var string $csrf */
$columns = $fields ?? [];
if (empty($columns) && !empty($items)) {
    $columns = [];
    foreach (array_keys($items[0]) as $key) {
        if (in_array($key, ['password', 'payload'], true)) {
            continue;
        }
        $columns[] = ['name' => $key, 'label' => ucfirst(str_replace('_', ' ', $key))];
    }
}
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <a href="<?php echo rateb_url($routePrefix . '/create'); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('create'); ?>
        </a>
    </div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <?php foreach ($columns as $col) { ?>
                    <th><?php echo Rateb\App\Core\View::escape($col['label'] ?? $col['name']); ?></th>
                    <?php } ?>
                    <th><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="<?php echo count($columns) + 1; ?>" class="text-center text-muted py-4">—</td></tr>
                <?php } else { foreach ($items as $row) { ?>
                <tr>
                    <?php foreach ($columns as $col) {
                        $val = $row[$col['name']] ?? '';
                        ?>
                    <td><?php echo Rateb\App\Core\View::escape($val); ?></td>
                    <?php } ?>
                    <td class="rateb-actions">
                        <a href="<?php echo rateb_url($routePrefix . '/' . (int)$row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <form method="post" action="<?php echo rateb_url($routePrefix . '/' . (int)$row['id'] . '/delete'); ?>" class="d-inline" onsubmit="return confirm('Delete?');">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php if (($routePrefix ?? '') === 'admin/companies') { ?>
                        <form method="post" action="<?php echo rateb_url('admin/companies/' . (int)$row['id'] . '/suspend'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-pause"></i></button>
                        </form>
                        <form method="post" action="<?php echo rateb_url('admin/companies/' . (int)$row['id'] . '/activate'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-play"></i></button>
                        </form>
                        <?php } ?>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('pagination', ['page' => $page ?? 1, 'total' => $total ?? 0, 'limit' => $limit ?? 20, 'routePrefix' => $routePrefix ?? '']); ?>
