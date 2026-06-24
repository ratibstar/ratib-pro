<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array<string, mixed>>|null $fields */
/** @var string $routePrefix */
/** @var string $csrf */
$bulkEnabled = $bulkEnabled ?? true;
$createEnabled = $createEnabled ?? true;
$actionsEnabled = $actionsEnabled ?? true;
$exportEnabled = $exportEnabled ?? true;
$searchEnabled = $searchEnabled ?? true;
$viewEnabled = $viewEnabled ?? false;
$printEnabled = $printEnabled ?? false;
$employeeReceiptEnabled = $employeeReceiptEnabled ?? false;
$customsInvoiceActions = !empty($customsInvoiceActions);
$actionsRoutePrefix = $actionsRoutePrefix ?? ($routePrefix ?? '');
$showActionsCol = $viewEnabled || ($actionsEnabled ?? true);
if (!empty($permissionResource) && function_exists('rateb_can_manage_entity')) {
    $canManage = rateb_can_manage_entity((string) $permissionResource);
    $createEnabled = $createEnabled && $canManage;
    $actionsEnabled = $actionsEnabled && $canManage;
    $bulkEnabled = $bulkEnabled && $canManage;
    $exportEnabled = $exportEnabled && rateb_can_export_entity((string) $permissionResource);
}
$columns = $fields ?? [];
if (empty($columns) && !empty($items)) {
    $columns = [];
    foreach (array_keys($items[0]) as $key) {
        if (in_array($key, ['password', 'payload'], true)) {
            continue;
        }
        $columns[] = ['name' => $key, 'label' => $key];
    }
}
$colspan = count($columns) + ($bulkEnabled ? 1 : 0) + ($showActionsCol ? 1 : 0);
$documentEntityType = (string) ($documentEntityType ?? '');
$fkLabelMaps = [];
if ($columns !== []) {
    $lookupSvc = new \Rateb\App\Services\FormLookupService();
    foreach ($columns as $col) {
        if ((string) ($col['type'] ?? '') === 'fk' && !empty($col['lookup'])) {
            $fkLabelMaps[(string) $col['name']] = $lookupSvc->valueLabelMap((string) $col['lookup']);
        }
    }
}
$isCompanies = ($routePrefix ?? '') === 'admin/companies';
$ratebRowRecordLabel = static function (array $row): string {
    foreach (['batch_no', 'title', 'name', 'item_name', 'request_no', 'order_no', 'contract_no', 'code', 'item_code', 'evaluation_no'] as $key) {
        if (!empty($row[$key])) {
            return (string) $row[$key];
        }
    }
    return '#' . (int) ($row['id'] ?? 0);
};
?>
<div class="rateb-card<?php echo empty($title) ? ' border-0 shadow-none' : ''; ?>">
    <?php if (!empty($title)) { ?>
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title); ?></span>
        <?php if ($createEnabled) { ?>
        <a href="<?php echo rateb_url($routePrefix . '/create'); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('create'); ?>
        </a>
        <?php } ?>
    </div>
    <?php } ?>
    <?php if ($bulkEnabled && !empty($items)) { ?>
    <div class="rateb-bulk-bar d-none" data-rateb-bulk-bar>
        <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0</span>
        <form method="post" action="<?php echo rateb_url($routePrefix . '/bulk-delete'); ?>" class="d-inline" data-rateb-bulk-form="delete" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_delete')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> <?php echo __('bulk_delete'); ?></button>
        </form>
        <?php if ($isCompanies) { ?>
        <form method="post" action="<?php echo rateb_url('admin/companies/bulk-suspend'); ?>" class="d-inline" data-rateb-bulk-form="suspend">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-pause"></i> <?php echo __('bulk_suspend'); ?></button>
        </form>
        <form method="post" action="<?php echo rateb_url('admin/companies/bulk-activate'); ?>" class="d-inline" data-rateb-bulk-form="activate">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-play"></i> <?php echo __('bulk_activate'); ?></button>
        </form>
        <?php } ?>
    </div>
    <?php } ?>
    <div class="rateb-card-body p-0"<?php echo $searchEnabled ? ' data-rateb-server-search="1"' : ''; ?>>
        <?php if ($searchEnabled) {
            Rateb\App\Core\View::partial('table-search', [
                'mode' => 'server',
                'search' => $search ?? '',
                'routePrefix' => $routePrefix ?? '',
            ]);
        } ?>
        <div class="rateb-table-wrap">
            <table class="table rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
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
                    <?php if ($actionsEnabled) { ?>
                    <th class="rateb-th-actions"><?php echo __('actions'); ?></th>
                    <?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="<?php echo $colspan; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) { ?>
                <tr>
                    <?php if ($bulkEnabled) { ?>
                    <td class="rateb-bulk-td">
                        <input type="checkbox" class="form-check-input rateb-row-check" value="<?php echo (int) $row['id']; ?>" data-rateb-row-check>
                    </td>
                    <?php } ?>
                    <?php foreach ($columns as $col) {
                        $val = $row[$col['name']] ?? '';
                        $colType = (string) ($col['type'] ?? '');
                        $colName = (string) ($col['name'] ?? '');
                        if ($colType === 'image') {
                            $imgUrl = trim((string) $val);
                            ?>
                    <td class="rateb-cell-image">
                        <?php if ($imgUrl !== '') { ?>
                        <button type="button" class="btn p-0 border-0 bg-transparent rateb-image-thumb-btn"
                            data-rateb-image-preview="<?php echo Rateb\App\Core\View::escape($imgUrl); ?>"
                            title="<?php echo Rateb\App\Core\View::escape(__('view_image')); ?>">
                            <img src="<?php echo Rateb\App\Core\View::escape($imgUrl); ?>" alt="" class="rounded border"
                                style="width: 48px; height: 48px; object-fit: cover; cursor: zoom-in;">
                        </button>
                        <?php } else { ?>
                        <span class="text-muted">—</span>
                        <?php } ?>
                    </td>
                        <?php } elseif ($colType === 'barcode') {
                            $barcode = trim((string) $val);
                            ?>
                    <td class="rateb-barcode-cell">
                        <?php if ($barcode !== '') {
                            $scanUrl = (new \Rateb\App\Services\DocumentBarcodeService())->publicScanUrl($barcode);
                            ?>
                        <span class="font-monospace small"><?php echo Rateb\App\Core\View::escape($barcode); ?></span>
                        <a href="<?php echo Rateb\App\Core\View::escape($scanUrl); ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-sm btn-outline-info ms-1" title="<?php echo __('scan_quick_view'); ?>">
                            <i class="fas fa-qrcode"></i>
                        </a>
                        <?php } else { ?>
                        <span class="text-muted">—</span>
                        <?php } ?>
                    </td>
                        <?php } elseif ($colType === 'map_link') {
                            $mapUrl = trim((string) $val);
                            ?>
                    <td class="rateb-map-link-cell">
                        <?php if ($mapUrl !== '') { ?>
                        <a href="<?php echo Rateb\App\Core\View::escape($mapUrl); ?>" target="_blank" rel="noopener noreferrer"
                            class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-map-marker-alt"></i> <?php echo __('show_location'); ?>
                        </a>
                        <?php } else { ?>
                        <span class="text-muted">—</span>
                        <?php } ?>
                    </td>
                        <?php } elseif ($colType === 'fk') {
                            $map = $fkLabelMaps[$colName] ?? [];
                            $idKey = (string) (int) $val;
                            $display = $map[$idKey] ?? '';
                            if ($display === '' && (int) $val > 0 && !empty($col['lookup'])) {
                                $display = (new \Rateb\App\Services\FormLookupService())->resolveFkLabel((string) $col['lookup'], $val);
                            }
                            if ($display === '') {
                                $display = $idKey !== '0' ? $idKey : '—';
                            }
                            ?>
                    <td class="rateb-cell-clip" title="<?php echo Rateb\App\Core\View::escape($display); ?>"><?php echo Rateb\App\Core\View::escape($display); ?></td>
                        <?php } elseif ($colType === 'slug' || $colName === 'slug') {
                            Rateb\App\Core\View::partial('table-cell', ['value' => $val, 'col' => array_merge($col, ['type' => 'id', 'name' => 'slug'])]);
                        } elseif ($colType === 'html_preview') {
                            Rateb\App\Core\View::partial('table-cell', ['value' => $val, 'col' => $col]);
                        } elseif ($colType === 'bidi_text') {
                            Rateb\App\Core\View::partial('table-cell', ['value' => $val, 'col' => $col]);
                        } elseif (in_array($colType, ['money', 'number', 'id', 'status'], true)) {
                            Rateb\App\Core\View::partial('table-cell', ['value' => $val, 'col' => $col]);
                        } elseif ($colType === 'clip' || $colType === 'text' || $colType === '') {
                            Rateb\App\Core\View::partial('table-cell', ['value' => $val, 'col' => $col]);
                        } else {
                            Rateb\App\Core\View::partial('table-cell', ['value' => $val, 'col' => $col]);
                        } ?>
                    <?php } ?>
                    <?php if ($showActionsCol) { ?>
                    <td class="rateb-actions-cell text-nowrap">
                        <div class="rateb-actions">
                        <?php if ($customsInvoiceActions) { ?>
                        <a href="<?php echo rateb_url($actionsRoutePrefix . '/' . (int) $row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
                        <a href="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int) ($row['purchase_order_id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo __('purchase_orders'); ?>"><i class="fas fa-eye"></i></a>
                        <?php } else { ?>
                        <?php if ($viewEnabled) { ?>
                        <a href="<?php echo rateb_url($actionsRoutePrefix . '/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-info" title="<?php echo __('view'); ?>"><i class="fas fa-eye"></i></a>
                        <?php } ?>
                        <?php if ($actionsEnabled) { ?>
                        <?php if ($printEnabled) { ?>
                        <a href="<?php echo rateb_url($actionsRoutePrefix . '/' . (int) $row['id'] . '/print'); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo __('print'); ?>" target="_blank" rel="noopener"><i class="fas fa-print"></i></a>
                        <?php } ?>
                        <?php if ($employeeReceiptEnabled && (int) ($row['assigned_employee_id'] ?? 0) > 0) { ?>
                        <a href="<?php echo rateb_url($actionsRoutePrefix . '/' . (int) $row['id'] . '/receipt'); ?>" class="btn btn-sm btn-outline-success" title="<?php echo __('fleet_employee_receipt'); ?>" target="_blank" rel="noopener"><i class="fas fa-file-signature"></i></a>
                        <?php } ?>
                        <?php if ($documentEntityType !== '') {
                            $docCount = (int) ($row['document_count'] ?? 0);
                            $rowLabel = $ratebRowRecordLabel($row);
                        ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-secondary position-relative js-entity-docs-open"
                                title="<?php echo __('view_files'); ?>"
                                data-route-prefix="<?php echo Rateb\App\Core\View::escape(rateb_url($actionsRoutePrefix)); ?>"
                                data-entity-id="<?php echo (int) $row['id']; ?>"
                                data-record-label="<?php echo Rateb\App\Core\View::escape($rowLabel); ?>"
                                data-docs-title="<?php echo Rateb\App\Core\View::escape(__('entity_documents')); ?>"
                                data-doc-count="<?php echo $docCount; ?>">
                            <i class="fas fa-paperclip"></i>
                            <?php if ($docCount > 0) { ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary"><?php echo $docCount; ?></span>
                            <?php } ?>
                        </button>
                        <?php } ?>
                        <a href="<?php echo rateb_url($actionsRoutePrefix . '/' . (int)$row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <form method="post" action="<?php echo rateb_url($actionsRoutePrefix . '/' . (int)$row['id'] . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php if ($isCompanies) { ?>
                        <form method="post" action="<?php echo rateb_url('admin/companies/' . (int)$row['id'] . '/suspend'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-pause"></i></button>
                        </form>
                        <form method="post" action="<?php echo rateb_url('admin/companies/' . (int)$row['id'] . '/activate'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success"><i class="fas fa-play"></i></button>
                        </form>
                        <?php } ?>
                        <?php } ?>
                        <?php } ?>
                        </div>
                    </td>
                    <?php } ?>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('pagination', ['page' => $page ?? 1, 'total' => $total ?? 0, 'limit' => $limit ?? 20, 'routePrefix' => $routePrefix ?? '']); ?>
<?php
$ratebHasImageCol = false;
foreach ($columns as $col) {
    if ((string) ($col['type'] ?? '') === 'image') {
        $ratebHasImageCol = true;
        break;
    }
}
if ($ratebHasImageCol) {
    Rateb\App\Core\View::partial('image-preview-kit');
}
?>
