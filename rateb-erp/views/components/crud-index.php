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
$isInventoryList = (bool) preg_match('#(^|/)inventory$#', trim((string) $actionsRoutePrefix, '/'));
$activePosShifts = (isset($activePosShifts) && is_array($activePosShifts)) ? $activePosShifts : [];
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
        <?php if ($isCompanies) { ?>
        <a href="<?php echo rateb_url('admin/company-permissions'); ?>" class="btn btn-info btn-sm">
            <i class="fas fa-toggle-on"></i> <?php echo __('company_permissions'); ?>
        </a>
        <?php } ?>
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
            <table class="table rateb-table mb-0<?php echo $isCompanies ? ' rateb-companies-table' : ''; ?>" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
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
                        if ($colName === 'quantity') {
                            $qtyValue = is_numeric($val) ? number_format((float) $val, 3, '.', '') : (string) $val;
                            ?>
                    <td data-col-name="quantity" data-qty-value="<?php echo Rateb\App\Core\View::escape((string) $qtyValue); ?>"><?php echo Rateb\App\Core\View::escape((string) $qtyValue); ?></td>
                        <?php } elseif ($colType === 'image') {
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
                        <a href="<?php echo rateb_url($actionsRoutePrefix . '/' . (int) $row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary" data-rateb-edit-link="1" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
                        <a href="<?php echo rateb_url(rateb_app_route('purchase-orders') . '/' . (int) ($row['purchase_order_id'] ?? 0)); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo __('purchase_orders'); ?>"><i class="fas fa-eye"></i></a>
                        <?php } else { ?>
                        <?php if ($viewEnabled) { ?>
                        <a href="<?php echo rateb_url($actionsRoutePrefix . '/' . (int) $row['id']); ?>" class="btn btn-sm btn-outline-info" title="<?php echo __('view'); ?>"><i class="fas fa-eye"></i></a>
                        <?php } ?>
                        <?php if ($actionsEnabled) { ?>
                        <?php if ($isCompanies) {
                            $companyStatus = (string) ($row['status'] ?? '');
                            $cid = (int) ($row['id'] ?? 0);
                            $canCompanyPerms = rateb_is_super_admin()
                                || rateb_can('company_plans.manage')
                                || rateb_can('companies.manage');
                            $branchCompanyId = $companyRowId > 0 ? $companyRowId : $cid;
                            $branchCompanyName = trim((string) ($row['name'] ?? ''));
                            $branchCpUrl = function_exists('rateb_platform_company_branches_url')
                                ? rateb_platform_company_branches_url($branchCompanyId)
                                : rateb_control_panel_branch_manage_url($branchCompanyId);
                            $showBranches = function_exists('rateb_platform_branch_manage_enabled')
                                && rateb_platform_branch_manage_enabled()
                                && $companyStatus === 'active';
                            ?>
                        <?php if ($canCompanyPerms) { ?>
                        <a href="<?php echo rateb_url('admin/company-permissions/' . $cid); ?>" class="btn btn-sm btn-info" title="<?php echo Rateb\App\Core\View::escape(__('company_permissions')); ?>">
                            <i class="fas fa-toggle-on"></i>
                            <span class="rateb-btn-label"><?php echo __('company_permissions'); ?></span>
                        </a>
                        <?php } ?>
                        <a href="<?php echo rateb_url($actionsRoutePrefix . '/' . $cid . '/edit'); ?>" class="btn btn-sm btn-outline-primary" data-rateb-edit-link="1" title="<?php echo __('edit'); ?>">
                            <i class="fas fa-edit"></i>
                            <span class="rateb-btn-label"><?php echo __('edit'); ?></span>
                        </a>
                        <div class="dropdown d-inline-block">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="<?php echo __('actions'); ?>">
                                <?php echo __('more'); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($documentEntityType !== '') {
                                    $docCount = (int) ($row['document_count'] ?? 0);
                                    $rowLabel = $ratebRowRecordLabel($row);
                                    ?>
                                <li>
                                    <button type="button" class="dropdown-item js-entity-docs-open"
                                        data-route-prefix="<?php echo Rateb\App\Core\View::escape(rateb_url($actionsRoutePrefix)); ?>"
                                        data-entity-id="<?php echo $cid; ?>"
                                        data-record-label="<?php echo Rateb\App\Core\View::escape($rowLabel); ?>"
                                        data-docs-title="<?php echo Rateb\App\Core\View::escape(__('entity_documents')); ?>"
                                        data-doc-count="<?php echo $docCount; ?>">
                                        <i class="fas fa-paperclip me-1"></i><?php echo __('view_files'); ?><?php echo $docCount > 0 ? ' (' . $docCount . ')' : ''; ?>
                                    </button>
                                </li>
                                <?php } ?>
                                <?php if ($showBranches) { ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo Rateb\App\Core\View::escape($branchCpUrl); ?>">
                                        <i class="fas fa-code-branch me-1"></i><?php echo __('manage_branches_cp'); ?>
                                    </a>
                                </li>
                                <?php } ?>
                                <?php if ($companyStatus === 'active') { ?>
                                <li>
                                    <form method="post" action="<?php echo rateb_url('admin/companies/' . $cid . '/suspend'); ?>">
                                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                        <button type="submit" class="dropdown-item text-warning">
                                            <i class="fas fa-pause me-1"></i><?php echo __('bulk_suspend'); ?>
                                        </button>
                                    </form>
                                </li>
                                <?php } elseif ($companyStatus === 'pending') { ?>
                                <li>
                                    <a class="dropdown-item text-success" href="<?php echo rateb_url('admin/oversight/companies-approvals'); ?>">
                                        <i class="fas fa-check-double me-1"></i><?php echo __('companies_approvals_oversight'); ?>
                                    </a>
                                </li>
                                <?php } ?>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="post" action="<?php echo rateb_url($actionsRoutePrefix . '/' . $cid . '/delete'); ?>" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="fas fa-trash me-1"></i><?php echo __('delete'); ?>
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                        <?php } else { ?>
                        <?php if ($isInventoryList && (float) ($row['quantity'] ?? 0) > 0) { ?>
                        <form method="post"
                              action="<?php echo rateb_url($actionsRoutePrefix . '/' . (int) $row['id'] . '/transfer-to-pos-warehouse'); ?>"
                              class="d-inline js-pos-transfer-form"
                              data-item-name="<?php echo Rateb\App\Core\View::escape((string) ($row['item_name'] ?? $row['item_code'] ?? ('#' . (int) ($row['id'] ?? 0)))); ?>">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <input type="hidden" name="transfer_qty" value="1" class="js-pos-transfer-qty">
                            <input type="hidden" name="shift_id" value="" class="js-pos-transfer-shift-id">
                            <input type="hidden" name="target_warehouse_id" value="" class="js-pos-transfer-warehouse-id">
                            <input type="hidden" name="target_branch_id" value="" class="js-pos-transfer-branch-id">
                            <button type="button" class="btn btn-sm btn-outline-success js-pos-transfer-open" title="<?php echo Rateb\App\Core\View::escape(__('pos_transfer_to_terminal_wh')); ?>">
                                <i class="fas fa-right-left"></i>
                            </button>
                        </form>
                        <?php } ?>
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
                        <a href="<?php echo rateb_url($actionsRoutePrefix . '/' . (int)$row['id'] . '/edit'); ?>" class="btn btn-sm btn-outline-primary" data-rateb-edit-link="1"><i class="fas fa-edit"></i></a>
                        <form method="post" action="<?php echo rateb_url($actionsRoutePrefix . '/' . (int)$row['id'] . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
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
if ($isInventoryList) { ?>
<div class="modal fade" id="ratebPosTransferModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rateb-pos-transfer-modal">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo Rateb\App\Core\View::escape(__('pos_transfer_to_terminal_wh')); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Rateb\App\Core\View::escape(__('close')); ?>"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 text-muted small" id="ratebPosTransferItem"></div>
                <div class="alert d-none" id="ratebPosTransferInlineMsg" role="alert"></div>
                <div class="mb-3">
                    <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('quantity')); ?></label>
                    <input type="text" inputmode="decimal" class="form-control form-control-lg" id="ratebPosTransferQty" value="1" placeholder="1">
                </div>
                <div>
                    <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('pos_shifts')); ?></label>
                    <select class="form-select" id="ratebPosTransferShift">
                        <option value=""><?php echo Rateb\App\Core\View::escape(__('select')); ?> (<?php echo Rateb\App\Core\View::escape(__('optional')); ?>)</option>
                        <?php foreach ($activePosShifts as $shift) { ?>
                        <option value="<?php echo (int) ($shift['id'] ?? 0); ?>"
                                data-warehouse-id="<?php echo (int) ($shift['warehouse_id'] ?? 0); ?>"
                                data-branch-id="<?php echo (int) ($shift['branch_id'] ?? 0); ?>">
                            <?php echo Rateb\App\Core\View::escape((string) ($shift['label'] ?? '')); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal"><?php echo Rateb\App\Core\View::escape(__('cancel')); ?></button>
                <button type="button" class="btn btn-primary" id="ratebPosTransferSubmit"><?php echo Rateb\App\Core\View::escape(__('save')); ?></button>
            </div>
        </div>
    </div>
</div>
<div class="position-fixed top-0 start-50 translate-middle-x p-3" style="z-index:1080">
    <div id="ratebPosTransferToast" class="toast align-items-center text-bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="ratebPosTransferToastBody"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="<?php echo Rateb\App\Core\View::escape(__('close')); ?>"></button>
        </div>
    </div>
</div>
<style>
.rateb-pos-transfer-modal .modal-body{padding-top:1rem}
.rateb-pos-transfer-modal .form-control,
.rateb-pos-transfer-modal .form-select{min-height:44px}
.rateb-pos-transfer-modal .form-control{
    color:#e8eefc;
    background:#081327;
    border-color:#1e3a5f;
}
.rateb-pos-transfer-modal .form-control:focus{
    color:#fff;
    background:#0b1a33;
    border-color:#2f5f97;
    box-shadow:0 0 0 .2rem rgba(47,95,151,.2);
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('ratebPosTransferModal');
    var qtyInput = document.getElementById('ratebPosTransferQty');
    var shiftSelect = document.getElementById('ratebPosTransferShift');
    var submitBtn = document.getElementById('ratebPosTransferSubmit');
    var itemLabel = document.getElementById('ratebPosTransferItem');
    var inlineMsg = document.getElementById('ratebPosTransferInlineMsg');
    var toastEl = document.getElementById('ratebPosTransferToast');
    var toastBody = document.getElementById('ratebPosTransferToastBody');
    var currentForm = null;
    if (modalEl && modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
    if (toastEl && toastEl.parentElement && toastEl.parentElement.parentElement !== document.body) {
        document.body.appendChild(toastEl.parentElement);
    }
    if (modalEl) {
        modalEl.style.zIndex = '2000';
        modalEl.style.pointerEvents = 'auto';
    }
    var modal = (window.bootstrap && modalEl) ? new window.bootstrap.Modal(modalEl, { backdrop: true, keyboard: true }) : null;
    var toast = (window.bootstrap && toastEl) ? new window.bootstrap.Toast(toastEl, { delay: 2600 }) : null;

    function showToast(msg, ok) {
        if (!toastEl || !toastBody) {
            return;
        }
        toastBody.textContent = msg || '';
        toastEl.classList.remove('text-bg-dark', 'text-bg-success', 'text-bg-danger');
        toastEl.classList.add(ok ? 'text-bg-success' : 'text-bg-danger');
        if (toast) {
            toast.show();
        }
    }
    function showInline(msg, ok) {
        if (!inlineMsg) { return; }
        inlineMsg.textContent = msg || '';
        inlineMsg.classList.remove('d-none', 'alert-success', 'alert-danger');
        inlineMsg.classList.add(ok ? 'alert-success' : 'alert-danger');
    }
    function clearInline() {
        if (!inlineMsg) { return; }
        inlineMsg.classList.add('d-none');
        inlineMsg.textContent = '';
    }
    function normalizeDigits(v) {
        var s = String(v || '');
        var ar = '٠١٢٣٤٥٦٧٨٩';
        var fa = '۰۱۲۳۴۵۶۷۸۹';
        s = s.replace(/[٠-٩]/g, function (ch) { return String(ar.indexOf(ch)); });
        s = s.replace(/[۰-۹]/g, function (ch) { return String(fa.indexOf(ch)); });
        return s.replace(',', '.').trim();
    }

    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.js-pos-transfer-open') : null;
        if (!btn) {
            return;
        }
        var form = btn.closest('form.js-pos-transfer-form');
        if (!form) {
            return;
        }
        currentForm = form;
        if (itemLabel) {
            itemLabel.textContent = form.getAttribute('data-item-name') || '';
        }
        clearInline();
        if (qtyInput) {
            qtyInput.value = '1';
            qtyInput.focus();
            qtyInput.select();
        }
        if (shiftSelect) {
            shiftSelect.value = '';
            if (shiftSelect.options.length > 1) {
                shiftSelect.selectedIndex = 1;
            }
        }
        if (submitBtn) {
            submitBtn.disabled = !(shiftSelect && shiftSelect.options.length > 1);
        }
        if (!(shiftSelect && shiftSelect.options.length > 1)) {
            showInline('<?php echo Rateb\App\Core\View::escape(__('pos_transfer_no_open_shift')); ?>', false);
        }
        if (modal) {
            modal.show();
        }
    });

    if (!submitBtn) {
        return;
    }
    submitBtn.addEventListener('click', function () {
        if (!currentForm) {
            return;
        }
        if (shiftSelect && shiftSelect.selectedIndex < 1) {
            showInline('<?php echo Rateb\App\Core\View::escape(__('pos_transfer_shift_required')); ?>', false);
            return;
        }
        submitBtn.disabled = true;
        var rawQty = normalizeDigits((qtyInput && qtyInput.value) || '1');
        if (qtyInput && rawQty === '') {
            qtyInput.value = '1';
            rawQty = '1';
        }
        var qty = parseFloat(rawQty);
        if (!isFinite(qty) || qty <= 0) {
            showInline('<?php echo Rateb\App\Core\View::escape(__('quantity_required')); ?>', false);
            submitBtn.disabled = false;
            return;
        }
        var shiftId = (shiftSelect && shiftSelect.value) ? String(shiftSelect.value) : '';
        var selectedOption = (shiftSelect && shiftSelect.selectedOptions && shiftSelect.selectedOptions[0]) ? shiftSelect.selectedOptions[0] : null;
        var targetWarehouseId = selectedOption ? String(selectedOption.getAttribute('data-warehouse-id') || '') : '';
        var targetBranchId = selectedOption ? String(selectedOption.getAttribute('data-branch-id') || '') : '';
        var qtyField = currentForm.querySelector('.js-pos-transfer-qty');
        var shiftField = currentForm.querySelector('.js-pos-transfer-shift-id');
        var whField = currentForm.querySelector('.js-pos-transfer-warehouse-id');
        var brField = currentForm.querySelector('.js-pos-transfer-branch-id');
        if (qtyField) {
            qtyField.value = String(qty);
        }
        if (shiftField) {
            shiftField.value = shiftId;
        }
        if (whField) {
            whField.value = targetWarehouseId;
        }
        if (brField) {
            brField.value = targetBranchId;
        }
        var fd = new FormData(currentForm);
        var body = new URLSearchParams();
        fd.forEach(function (v, k) { body.append(k, String(v)); });

        var csrfField = currentForm.querySelector('input[name="_csrf"]');
        var csrfToken = csrfField ? String(csrfField.value || '') : '';
        fetch(currentForm.action, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken,
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json'
            },
            body: body.toString()
        }).then(function (r) {
            return r.text().then(function (txt) {
                var parsed = null;
                try { parsed = JSON.parse(txt); } catch (e) { parsed = null; }
                return { status: r.status, okHttp: r.ok, data: parsed, raw: txt || '' };
            });
        }).then(function (data) {
            var payload = (data && data.data && typeof data.data === 'object') ? data.data : {};
            var status = Number((data && data.status) || 0);
            var ok = !!payload.ok;
            var msg = payload.message ? String(payload.message) : (ok ? '<?php echo Rateb\App\Core\View::escape(__('pos_transfer_done')); ?>' : '<?php echo Rateb\App\Core\View::escape(__('invalid_request')); ?>');
            if (!ok) {
                var raw = String((data && data.raw) || '').replace(/\s+/g, ' ').trim();
                var rawShort = raw.slice(0, 180);
                var csrfState = csrfToken ? 'csrf:sent' : 'csrf:missing';
                msg = msg + ' [debug status=' + status + ' ' + csrfState + ']';
                if (rawShort) {
                    msg = msg + ' ' + rawShort;
                }
            }
            showInline(msg, ok);
            showToast(msg, ok);
            if (ok) {
                if (typeof payload.source_quantity !== 'undefined') {
                    var row = currentForm.closest('tr');
                    var qtyCell = row ? row.querySelector('td[data-col-name="quantity"]') : null;
                    var q = Number(payload.source_quantity);
                    if (qtyCell && isFinite(q)) {
                        qtyCell.textContent = q.toFixed(3);
                        qtyCell.setAttribute('data-qty-value', q.toFixed(3));
                    }
                }
                if (payload.stats && typeof payload.stats === 'object') {
                    var strip = document.querySelector('[data-cm-module-stats] .cm-strip');
                    if (strip) {
                        var keys = ['inventory', 'inventory_value', 'warehouses', 'low_stock', 'expiring_soon'];
                        keys.forEach(function (k) {
                            var item = strip.querySelector('.cm-strip__item[data-stat-key="' + k + '"] .cm-strip__val');
                            if (!item || typeof payload.stats[k] === 'undefined') {
                                return;
                            }
                            if (k === 'inventory_value') {
                                if (payload.stats.inventory_value_fmt) {
                                    item.innerHTML = String(payload.stats.inventory_value_fmt);
                                }
                            } else {
                                item.textContent = String(payload.stats[k]);
                            }
                        });
                    }
                }
                try {
                    localStorage.setItem('rateb_pos_catalog_refresh', String(Date.now()));
                    if (window.BroadcastChannel) {
                        var ch = new BroadcastChannel('rateb_pos_catalog_channel');
                        ch.postMessage({ type: 'refresh', ts: Date.now() });
                        ch.close();
                    }
                } catch (err) {}
                if (modal) {
                    modal.hide();
                }
            } else if (status === 419 || msg.indexOf('<?php echo Rateb\App\Core\View::escape(__('invalid_request')); ?>') !== -1) {
                // Fallback for occasional AJAX CSRF/session mismatch: submit regular form path.
                setTimeout(function () { currentForm.submit(); }, 100);
            }
        }).catch(function () {
            showInline('<?php echo Rateb\App\Core\View::escape(__('invalid_request')); ?>', false);
            showToast('<?php echo Rateb\App\Core\View::escape(__('invalid_request')); ?>', false);
            // Hard fallback: submit regular form if AJAX path fails.
            setTimeout(function () { currentForm.submit(); }, 150);
        }).finally(function () {
            submitBtn.disabled = false;
        });
    });
});
</script>
<?php }
?>
