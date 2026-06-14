<?php
/** @var array<int, array<string, mixed>> $tree */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var bool $actionsEnabled */
/** @var bool $bulkEnabled */
$actionsEnabled = $actionsEnabled ?? false;
$bulkEnabled = $bulkEnabled ?? false;
$bulkUrl = strpos($routePrefix, 'admin/ops/') === 0
    ? rateb_app_url(preg_replace('#^admin/ops/#', '', $routePrefix) . '/bulk-delete')
    : rateb_url($routePrefix . '/bulk-delete');
$editUrl = static function (int $id) use ($routePrefix): string {
    if (strpos($routePrefix, 'admin/ops/') === 0) {
        $short = preg_replace('#^admin/ops/#', '', $routePrefix);
        return rateb_app_url($short . '/' . $id . '/edit');
    }
    return rateb_url($routePrefix . '/' . $id . '/edit');
};
$deleteUrl = static function (int $id) use ($routePrefix): string {
    if (strpos($routePrefix, 'admin/ops/') === 0) {
        $short = preg_replace('#^admin/ops/#', '', $routePrefix);
        return rateb_app_url($short . '/' . $id . '/delete');
    }
    return rateb_url($routePrefix . '/' . $id . '/delete');
};
$createUrl = strpos($routePrefix, 'admin/ops/') === 0
    ? rateb_app_url(preg_replace('#^admin/ops/#', '', $routePrefix) . '/create')
    : rateb_url($routePrefix . '/create');
$render = static function (array $nodes, int $depth = 0) use (&$render, $editUrl, $deleteUrl, $actionsEnabled, $bulkEnabled, $csrf): void {
    foreach ($nodes as $node) {
        $name = rateb_locale() === 'ar' && !empty($node['name_ar']) ? $node['name_ar'] : ($node['name'] ?? '');
        $type = (string) ($node['account_type'] ?? '');
        $balance = (float) ($node['balance'] ?? 0);
        $isGroup = !empty($node['children']);
        $isHeader = substr((string) ($node['code'] ?? ''), -3) === '000';
        $nodeId = (int) ($node['id'] ?? 0);
        ?>
        <tr class="<?php echo $isGroup ? 'rateb-coa-group' : ''; ?><?php echo $isHeader ? ' rateb-coa-header' : ''; ?>" data-depth="<?php echo $depth; ?>">
            <?php if ($bulkEnabled) { ?>
            <td class="rateb-bulk-td">
                <?php if (!$isGroup) { ?>
                <input type="checkbox" class="form-check-input" data-rateb-row-check value="<?php echo $nodeId; ?>">
                <?php } ?>
            </td>
            <?php } ?>
            <td class="rateb-coa-name" style="padding-inline-start: <?php echo (12 + $depth * 24); ?>px">
                <?php if ($depth > 0) { ?><span class="rateb-coa-branch text-muted me-1">└</span><?php } ?>
                <?php if ($isGroup || $isHeader) { ?><i class="fas fa-folder-open text-warning me-1"></i><?php } else { ?><i class="fas fa-file-invoice text-muted me-1"></i><?php } ?>
                <span class="fw-semibold"><?php echo Rateb\App\Core\View::escape($node['code'] ?? ''); ?></span>
                <span class="ms-1"><?php echo Rateb\App\Core\View::escape($name); ?></span>
                <?php if ($isGroup || $isHeader) { ?><span class="badge bg-warning-subtle text-warning ms-1 small"><?php echo __('main_account'); ?></span><?php } ?>
            </td>
            <td><span class="badge bg-secondary-subtle text-secondary"><?php echo __($type); ?></span></td>
            <td class="text-end"><?php echo number_format((float) ($node['total_debit'] ?? 0), 2); ?></td>
            <td class="text-end"><?php echo number_format((float) ($node['total_credit'] ?? 0), 2); ?></td>
            <td class="text-end fw-semibold"><?php echo number_format($balance, 2); ?></td>
            <?php if ($actionsEnabled) { ?>
            <td class="text-end text-nowrap">
                <a href="<?php echo $editUrl($nodeId); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                <?php if (!$isGroup) { ?>
                <form method="post" action="<?php echo $deleteUrl($nodeId); ?>" class="d-inline"
                      onsubmit="return confirm('<?php echo __('bulk_confirm_delete'); ?>');">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                </form>
                <?php } ?>
            </td>
            <?php } ?>
        </tr>
        <?php
        if ($isGroup) {
            $render($node['children'], $depth + 1);
        }
    }
};
$colspan = 5 + ($bulkEnabled ? 1 : 0) + ($actionsEnabled ? 1 : 0);
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-sitemap me-2"></i><?php echo __('coa_tree'); ?></span>
        <?php if ($createEnabled ?? false) { ?>
        <a href="<?php echo $createUrl; ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('add_account'); ?>
        </a>
        <?php } ?>
    </div>
    <?php if ($bulkEnabled && !empty($tree)) { ?>
    <div class="rateb-bulk-bar d-none" data-rateb-bulk-bar>
        <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0</span>
        <form method="post" action="<?php echo $bulkUrl; ?>" class="d-inline" data-rateb-bulk-form="deactivate"
              data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_deactivate')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-ban"></i> <?php echo __('bulk_deactivate'); ?></button>
        </form>
    </div>
    <?php } ?>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table rateb-coa-tree mb-0" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
                <thead>
                <tr>
                    <?php if ($bulkEnabled) { ?>
                    <th class="rateb-bulk-th">
                        <input type="checkbox" class="form-check-input" data-rateb-select-all title="<?php echo __('select_all'); ?>">
                    </th>
                    <?php } ?>
                    <th><?php echo __('account'); ?></th>
                    <th><?php echo __('account_type'); ?></th>
                    <th class="text-end"><?php echo __('debit'); ?></th>
                    <th class="text-end"><?php echo __('credit'); ?></th>
                    <th class="text-end"><?php echo __('balance'); ?></th>
                    <?php if ($actionsEnabled) { ?><th></th><?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($tree)) { ?>
                <tr><td colspan="<?php echo $colspan; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    $render($tree);
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
