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
$statusToggleEnabled = $statusToggleEnabled ?? false;
$customsInvoiceActions = !empty($customsInvoiceActions);
$actionsRoutePrefix = $actionsRoutePrefix ?? ($routePrefix ?? '');
if (!empty($permissionResource) && function_exists('rateb_can_manage_entity')) {
    $canManage = rateb_can_manage_entity((string) $permissionResource);
    $createEnabled = $createEnabled && $canManage;
    $actionsEnabled = $actionsEnabled && $canManage;
    $bulkEnabled = $bulkEnabled && $canManage;
    $exportEnabled = $exportEnabled && rateb_can_export_entity((string) $permissionResource);
}
$columns = $fields ?? [];
if (function_exists('rateb_enrich_index_columns')) {
    $columns = rateb_enrich_index_columns($columns);
}
$showActionsCol = $viewEnabled || ($actionsEnabled ?? true) || $bulkEnabled;
if (empty($columns) && !empty($items)) {
    $columns = [];
    foreach (array_keys($items[0]) as $key) {
        if (in_array($key, ['password', 'payload'], true)) {
            continue;
        }
        $columns[] = ['name' => $key, 'label' => $key];
    }
}
$colspan = count($columns) + ($showActionsCol ? 1 : 0);
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
$tableToolsEnabled = $tableToolsEnabled ?? true;
$exportRoute = trim((string) ($exportRoute ?? rateb_url(($routePrefix ?? '') . '/export')));
$tableTitle = trim((string) ($tableTitle ?? ($title ?? '')));
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
        <div class="d-flex flex-wrap gap-2">
        <?php if ($isCompanies && function_exists('rateb_platform_branch_manage_enabled') && rateb_platform_branch_manage_enabled()) { ?>
        <a href="<?php echo Rateb\App\Core\View::escape(function_exists('rateb_platform_company_branches_url') ? rateb_platform_company_branches_url() : rateb_control_panel_branch_manage_url()); ?>" class="btn btn-outline-primary btn-sm" title="<?php echo Rateb\App\Core\View::escape(__('manage_branches_cp')); ?>">
            <i class="fas fa-code-branch"></i> <?php echo __('manage_branches_cp'); ?>
        </a>
        <?php } ?>
        <?php if ($createEnabled) { ?>
        <a href="<?php echo rateb_url($routePrefix . '/create'); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('create'); ?>
        </a>
        <?php } ?>
        </div>
    </div>
    <?php } ?>
    <?php if ($bulkEnabled && !empty($items)) { ?>
    <div class="rateb-bulk-bar d-none" data-rateb-bulk-bar>
        <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0</span>
        <form method="post" action="<?php echo rateb_url($routePrefix . '/bulk-delete'); ?>" class="d-inline" data-rateb-bulk-form="delete" data-rateb-bulk-confirm="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_delete')); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="button" class="btn btn-danger btn-sm" data-rateb-bulk-delete-btn><i class="fas fa-trash"></i> <?php echo __('bulk_delete'); ?></button>
        </form>
        <?php if ($isCompanies) { ?>
        <form method="post" action="<?php echo rateb_url('admin/companies/bulk-suspend'); ?>" class="d-inline" data-rateb-bulk-form="suspend">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-pause"></i> <?php echo __('bulk_suspend'); ?></button>
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
                'formAction' => $listBaseUrl ?? '',
                'searchKey' => $searchKey ?? 'q',
                'searchClearUrl' => $searchClearUrl ?? '',
                'preserve' => $searchPreserve ?? ['company_id', 'status', 'date_from', 'date_to', 'from', 'to'],
            ]);
        } ?>
        <?php if ($tableToolsEnabled && $columns !== []) {
            Rateb\App\Core\View::partial('table-toolbar', [
                'exportRoute' => $exportRoute,
                'exportEnabled' => $exportEnabled ?? true,
                'tableTitle' => $tableTitle,
            ]);
        } ?>
        <div class="rateb-table-wrap" data-rateb-table-root="1"<?php echo ($exportEnabled ?? true) && $exportRoute !== '' ? ' data-export-route="' . Rateb\App\Core\View::escape($exportRoute) . '"' : ''; ?>>
            <table class="table rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
                <thead>
                <tr>
                    <?php foreach ($columns as $col) { ?>
                    <th><?php echo Rateb\App\Core\View::escape(rateb_label((string) ($col['label'] ?? $col['name']))); ?></th>
                    <?php } ?>
                    <?php if ($showActionsCol) { ?>
                    <th class="rateb-th-actions">
                        <span class="rateb-actions-head">
                            <?php if ($bulkEnabled && !empty($items)) { ?>
                            <input type="checkbox" class="form-check-input" data-rateb-select-all title="<?php echo Rateb\App\Core\View::escape(__('select_all')); ?>">
                            <?php } ?>
                            <span><?php echo __('actions'); ?></span>
                        </span>
                    </th>
                    <?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                <tr><td colspan="<?php echo $colspan; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($items as $row) {
                    $companyRowId = $isCompanies ? (int) ($row['id'] ?? 0) : 0;
                    ?>
                <tr<?php echo $companyRowId > 0 ? ' data-company-id="' . $companyRowId . '"' : ''; ?>>
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
                            $mapUrl = rateb_external_url(trim((string) $val));
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
                        } elseif (in_array($colType, ['money', 'number', 'id', 'status', 'date', 'datetime', 'time', 'month', 'week'], true)) {
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
                        <?php if ($bulkEnabled) { ?>
                        <input type="checkbox" class="form-check-input rateb-row-check rateb-actions-select" value="<?php echo (int) $row['id']; ?>" data-rateb-row-check title="<?php echo Rateb\App\Core\View::escape(__('select')); ?>">
                        <?php } ?>
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
                        <?php if (!empty($statusToggleEnabled)) {
                            $rowActive = (string) ($row['status'] ?? '') === 'active';
                            ?>
                        <form method="post" action="<?php echo rateb_url($actionsRoutePrefix . '/' . (int) $row['id'] . '/toggle-status'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-<?php echo $rowActive ? 'warning' : 'success'; ?>"
                                title="<?php echo Rateb\App\Core\View::escape($rowActive ? __('deactivate_branch') : __('activate_branch')); ?>">
                                <i class="fas fa-<?php echo $rowActive ? 'pause' : 'play'; ?>"></i>
                            </button>
                        </form>
                        <?php } ?>
                        <a href="<?php echo rateb_url($actionsRoutePrefix . '/' . (int)$row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i></a>
                        <form method="post" action="<?php echo rateb_url($actionsRoutePrefix . '/' . (int)$row['id'] . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php if ($isCompanies) {
                            $companyStatus = (string) ($row['status'] ?? '');
                            if (function_exists('rateb_platform_branch_manage_enabled') && rateb_platform_branch_manage_enabled() && $companyStatus === 'active') {
                                $branchCompanyId = $companyRowId > 0 ? $companyRowId : (int) ($row['id'] ?? 0);
                                $branchCompanyName = trim((string) ($row['name'] ?? ''));
                                $branchCpUrl = function_exists('rateb_platform_company_branches_url')
                                    ? rateb_platform_company_branches_url($branchCompanyId)
                                    : rateb_control_panel_branch_manage_url($branchCompanyId);
                                $branchTitle = __('manage_branches_cp') . ($branchCompanyName !== '' ? ' — ' . $branchCompanyName : '') . ' #' . $branchCompanyId;
                                ?>
                        <a href="<?php echo Rateb\App\Core\View::escape($branchCpUrl); ?>" class="btn btn-sm btn-outline-success" title="<?php echo Rateb\App\Core\View::escape($branchTitle); ?>" aria-label="<?php echo Rateb\App\Core\View::escape($branchTitle); ?>"><i class="fas fa-code-branch"></i><span class="visually-hidden">#<?php echo $branchCompanyId; ?></span></a>
                        <?php }
                            if ($companyStatus === 'active') { ?>
                        <form method="post" action="<?php echo rateb_url('admin/companies/' . (int)$row['id'] . '/suspend'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-warning" title="<?php echo Rateb\App\Core\View::escape(__('bulk_suspend')); ?>"><i class="fas fa-pause"></i></button>
                        </form>
                        <?php }
                            if ($companyStatus === 'pending') { ?>
                        <a href="<?php echo rateb_url('admin/oversight/companies-approvals'); ?>" class="btn btn-sm btn-outline-success" title="<?php echo Rateb\App\Core\View::escape(__('companies_approvals_oversight')); ?>"><i class="fas fa-check-double"></i></a>
                        <?php }
                        } ?>
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
<?php Rateb\App\Core\View::partial('pagination', [
    'page' => $page ?? 1,
    'total' => $total ?? 0,
    'limit' => $limit ?? rateb_list_per_page(),
    'routePrefix' => $routePrefix ?? '',
    'baseUrl' => $listBaseUrl ?? '',
    'pageKey' => $pageKey ?? 'page',
    'perPageKey' => $perPageKey ?? 'per_page',
    'perPageOptions' => $perPageOptions ?? null,
    'preserveQuery' => $preserveQuery ?? [],
]); ?>
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
