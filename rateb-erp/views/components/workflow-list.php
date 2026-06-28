<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array{name:string,label:string,type?:string}> $columns */
/** @var bool $bulkEnabled */
/** @var bool $actionsEnabled */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var bool $approvalEnabled */
/** @var bool $editEnabled */
/** @var bool $canApprove */
/** @var bool $viewActionsEnabled */
/** @var bool $exportEnabled */
$bulkEnabled = !empty($bulkEnabled);
$actionsEnabled = !empty($actionsEnabled);
$viewActionsEnabled = !empty($viewActionsEnabled);
$editEnabled = !empty($editEnabled);
$approvalEnabled = !empty($approvalEnabled);
$canApprove = !empty($canApprove);
$exportEnabled = $exportEnabled ?? true;
$routePrefix = (string) ($routePrefix ?? '');
$csrf = (string) ($csrf ?? '');
$hasActionLink = false;
foreach ($columns as $c) {
    if (($c['type'] ?? '') === 'action_link') {
        $hasActionLink = true;
        break;
    }
}
$showActionsColumn = ($viewActionsEnabled || $actionsEnabled) && $routePrefix !== '' && !$hasActionLink;
$colspan = count($columns) + ($bulkEnabled ? 1 : 0) + ($showActionsColumn ? 1 : 0);
?>
<?php if ($bulkEnabled && $routePrefix !== '') { ?>
<div class="rateb-bulk-bar d-none" data-rateb-bulk-bar>
    <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0</span>
    <form method="post" action="<?php echo rateb_url($routePrefix . '/bulk-delete'); ?>" class="d-inline" data-rateb-bulk-form="delete" data-rateb-bulk-confirm="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_delete')); ?>">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="button" class="btn btn-danger btn-sm" data-rateb-bulk-delete-btn><i class="fas fa-trash"></i> <?php echo __('bulk_delete'); ?></button>
    </form>
</div>
<?php } ?>
<?php Rateb\App\Core\View::partial('table-search', ['mode' => 'client']); ?>
<div class="rateb-table-wrap" data-rateb-table-search-host="1">
    <table class="table table-hover rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
        <thead>
            <tr>
                <?php if ($bulkEnabled) { ?>
                <th class="rateb-bulk-th">
                    <input type="checkbox" class="form-check-input" data-rateb-select-all title="<?php echo __('select_all'); ?>">
                </th>
                <?php } ?>
                <?php foreach ($columns as $col) { ?>
                <th><?php echo Rateb\App\Core\View::escape(rateb_label((string) ($col['label'] ?? $col['name']))); ?></th>
                <?php } ?>
                <?php if ($showActionsColumn) { ?>
                <th class="rateb-th-actions"><?php echo __('actions'); ?></th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)) { ?>
            <tr><td colspan="<?php echo $colspan; ?>" class="text-muted text-center py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else {
                foreach ($items as $row) { ?>
            <tr>
                <?php if ($bulkEnabled) { ?>
                <td class="rateb-bulk-td">
                    <input type="checkbox" class="form-check-input" name="ids[]" value="<?php echo (int) ($row['id'] ?? 0); ?>" data-rateb-row-check>
                </td>
                <?php } ?>
                <?php foreach ($columns as $col) {
                    $type = (string) ($col['type'] ?? '');
                    if ($type === 'action_link') {
                        $path = str_replace('{id}', (string) ($row['id'] ?? ''), (string) ($col['url'] ?? ''));
                        $href = str_starts_with($path, 'http') ? $path : rateb_url($path);
                        $text = rateb_label((string) ($col['text'] ?? 'view'));
                        ?>
                <td class="rateb-actions-cell text-nowrap">
                    <div class="rateb-actions">
                    <a href="<?php echo Rateb\App\Core\View::escape($href); ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i> <?php echo Rateb\App\Core\View::escape($text); ?>
                    </a>
                    </div>
                </td>
                        <?php
                        continue;
                    }
                    $val = $row[$col['name']] ?? '';
                    if ($type === 'notes') {
                        $col = array_merge($col, ['type' => 'clip']);
                    }
                    Rateb\App\Core\View::partial('table-cell', ['value' => $val, 'col' => $col]);
                } ?>
                <?php if ($showActionsColumn) { ?>
                <td class="rateb-actions-cell text-nowrap">
                    <div class="rateb-actions">
                    <?php
                    $rowId = (int) ($row['id'] ?? 0);
                    $rowApproval = (string) ($row['manager_approval'] ?? '');
                    if (str_starts_with($rowApproval, 'manager_approval_')) {
                        $rowApproval = substr($rowApproval, strlen('manager_approval_'));
                    }
                    $rowApproved = $rowApproval === 'approved';
                    if ($viewActionsEnabled) { ?>
                    <a href="<?php echo rateb_url($routePrefix . '/' . $rowId); ?>" class="btn btn-sm btn-outline-info" title="<?php echo __('view'); ?>"><i class="fas fa-eye"></i></a>
                    <a href="<?php echo rateb_url($routePrefix . '/' . $rowId . '/print'); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo __('print'); ?>" target="_blank" rel="noopener"><i class="fas fa-print"></i></a>
                    <?php if ($exportEnabled) { ?>
                    <a href="<?php echo rateb_url_query(rateb_url($routePrefix . '/' . $rowId . '/download'), ['format' => 'pdf']); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo __('print_save_pdf'); ?>" target="_blank" rel="noopener"><i class="fas fa-file-pdf"></i></a>
                    <?php } }
                    if ($actionsEnabled) {
                    if ($editEnabled) { ?>
                    <a href="<?php echo rateb_url($routePrefix . '/' . $rowId . '/edit'); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
                    <?php }
                    if ($approvalEnabled && $canApprove && $rowApproval === 'pending') { ?>
                    <form method="post" action="<?php echo rateb_url($routePrefix . '/' . (int) ($row['id'] ?? 0) . '/approve'); ?>" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success" title="<?php echo __('approve'); ?>"><i class="fas fa-check"></i></button>
                    </form>
                    <form method="post" action="<?php echo rateb_url($routePrefix . '/' . (int) ($row['id'] ?? 0) . '/reject'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_reject')); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-warning" title="<?php echo __('reject'); ?>"><i class="fas fa-times"></i></button>
                    </form>
                    <?php }
                    if (!$rowApproved) { ?>
                    <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $rowId . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('delete'); ?>"><i class="fas fa-trash"></i></button>
                    </form>
                    <?php } } ?>
                    </div>
                </td>
                <?php } ?>
            </tr>
            <?php }
            } ?>
        </tbody>
    </table>
</div>
