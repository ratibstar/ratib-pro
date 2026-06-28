<?php
/** @var array<int, array<string, mixed>> $tree */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var bool $actionsEnabled */
/** @var bool $bulkEnabled */
/** @var bool $fullTreeMode */
$actionsEnabled = $actionsEnabled ?? false;
$bulkEnabled = $bulkEnabled && !($fullTreeMode ?? false);
$fullTreeMode = $fullTreeMode ?? false;
$treeTitle = $treeTitle ?? __('coa_tree');
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
$render = static function (array $nodes, int $depth = 0, ?int $parentNodeId = null) use (&$render, $editUrl, $deleteUrl, $actionsEnabled, $bulkEnabled, $csrf, $fullTreeMode): void {
    foreach ($nodes as $node) {
        $name = rateb_locale() === 'ar' && !empty($node['name_ar']) ? $node['name_ar'] : ($node['name'] ?? '');
        $type = (string) ($node['account_type'] ?? '');
        $balance = (float) ($node['balance'] ?? 0);
        $isGroup = !empty($node['children']);
        $isHeader = substr((string) ($node['code'] ?? ''), -3) === '000';
        $nodeId = (int) ($node['id'] ?? 0);
        $childAttr = $parentNodeId !== null ? ' data-coa-child-of="' . $parentNodeId . '"' : '';
        ?>
        <tr class="<?php echo $isGroup ? 'rateb-coa-group' : ''; ?><?php echo $isHeader ? ' rateb-coa-header' : ''; ?><?php echo $parentNodeId !== null ? ' rateb-coa-child' : ''; ?>"
            data-depth="<?php echo $depth; ?>"<?php echo $childAttr; ?><?php echo $isGroup && $fullTreeMode ? ' data-coa-node="' . $nodeId . '"' : ''; ?>>
            <td class="rateb-coa-name" style="padding-inline-start: <?php echo (12 + $depth * 24); ?>px">
                <?php if ($fullTreeMode && $isGroup) { ?>
                <button type="button" class="btn btn-link btn-sm p-0 me-1 text-warning rateb-coa-toggle" data-coa-toggle="<?php echo $nodeId; ?>" aria-expanded="true">
                    <i class="fas fa-chevron-down"></i>
                </button>
                <?php } elseif ($depth > 0) { ?>
                <span class="rateb-coa-branch text-muted me-1">└</span>
                <?php } ?>
                <?php if ($isGroup || $isHeader) { ?><i class="fas fa-folder-open text-warning me-1"></i><?php } else { ?><i class="fas fa-file-invoice text-muted me-1"></i><?php } ?>
                <span class="fw-semibold"><?php echo Rateb\App\Core\View::escape($node['code'] ?? ''); ?></span>
                <span class="ms-1"><?php echo Rateb\App\Core\View::escape($name); ?></span>
                <?php if ($isGroup || $isHeader) { ?><span class="badge bg-warning-subtle text-warning ms-1 small"><?php echo __('main_account'); ?></span><?php } ?>
            </td>
            <td><span class="badge bg-secondary-subtle text-secondary"><?php echo __($type); ?></span></td>
            <td class="text-end"><?php echo number_format((float) ($node['total_debit'] ?? 0), 2); ?></td>
            <td class="text-end"><?php echo number_format((float) ($node['total_credit'] ?? 0), 2); ?></td>
            <td class="text-end fw-semibold"><?php echo number_format($balance, 2); ?></td>
            <?php if ($actionsEnabled || $bulkEnabled) { ?>
            <td class="rateb-actions-cell text-end text-nowrap">
                <div class="rateb-actions justify-content-end">
                <?php if ($bulkEnabled && !$isGroup) { ?>
                <input type="checkbox" class="form-check-input rateb-row-check rateb-actions-select" data-rateb-row-check value="<?php echo $nodeId; ?>">
                <?php } ?>
                <?php if ($actionsEnabled && !$fullTreeMode) { ?>
                <a href="<?php echo $editUrl($nodeId); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                <?php if (!$isGroup) { ?>
                <form method="post" action="<?php echo $deleteUrl($nodeId); ?>" class="d-inline"
                      data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_delete')); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                </form>
                <?php } ?>
                <?php } elseif ($actionsEnabled && $fullTreeMode) { ?>
                <a href="<?php echo $editUrl($nodeId); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
                <?php } ?>
                </div>
            </td>
            <?php } ?>
        </tr>
        <?php
        if ($isGroup) {
            $render($node['children'], $depth + 1, $nodeId);
        }
    }
};
$showActionsCol = $actionsEnabled || $bulkEnabled;
$colspan = 5 + ($showActionsCol ? 1 : 0);
?>
<div class="rateb-card"<?php echo $fullTreeMode ? ' data-rateb-coa-full-tree="1"' : ''; ?>>
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-sitemap me-2"></i><?php echo Rateb\App\Core\View::escape($treeTitle); ?></span>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($fullTreeMode && !empty($tree)) { ?>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-coa-expand-all><i class="fas fa-expand-alt"></i> <?php echo __('expand_all'); ?></button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-coa-collapse-all><i class="fas fa-compress-alt"></i> <?php echo __('collapse_all'); ?></button>
            <?php } ?>
            <?php if (($createEnabled ?? false) && !$fullTreeMode) { ?>
            <a href="<?php echo $createUrl; ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> <?php echo __('add_account'); ?>
            </a>
            <?php } ?>
        </div>
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
                    <th><?php echo __('account'); ?></th>
                    <th><?php echo __('account_type'); ?></th>
                    <th class="text-end"><?php echo __('debit'); ?></th>
                    <th class="text-end"><?php echo __('credit'); ?></th>
                    <th class="text-end"><?php echo __('balance'); ?></th>
                    <?php if ($showActionsCol) { ?>
                    <th class="rateb-th-actions text-end">
                        <span class="rateb-actions-head">
                            <?php if ($bulkEnabled) { ?>
                            <input type="checkbox" class="form-check-input" data-rateb-select-all title="<?php echo __('select_all'); ?>">
                            <?php } ?>
                            <?php if ($actionsEnabled) { ?><span><?php echo __('actions'); ?></span><?php } ?>
                        </span>
                    </th>
                    <?php } ?>
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
