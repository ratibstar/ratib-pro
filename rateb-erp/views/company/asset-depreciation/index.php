<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<string, mixed> $filters */
/** @var array<string, mixed> $summary */
/** @var array<int, array{value:string|int,label:string}> $assetOptions */
/** @var array<int, array{value:string|int,label:string}> $costCenterOptions */
/** @var array<int, array{value:string,label:string}> $statusOptions */
/** @var array<int, array{value:string,label:string}> $depreciationTypes */
/** @var array<int, array{name:string,label:string,type?:string,header_label?:string}> $columns */
$listUrl = rateb_app_url('asset-depreciation');
$routePrefix = rateb_app_route('asset-depreciation');
$exportBase = $exportRoute ?? rateb_app_url('asset-depreciation/export');
$exportQuery = array_filter([
    'asset_id' => (int) ($filters['asset_id'] ?? 0) ?: null,
    'status' => (string) ($filters['status'] ?? ''),
    'date_from' => (string) ($filters['date_from'] ?? ''),
    'date_to' => (string) ($filters['date_to'] ?? ''),
], static fn ($v) => $v !== null && $v !== '');
$exportLink = static function (string $format) use ($exportBase, $exportQuery): string {
    return $exportBase . '?' . http_build_query(array_merge($exportQuery, ['format' => $format]));
};
$canManage = $canManage ?? rateb_can_manage_entity('asset-depreciation');
$bookJson = json_encode($assetBookValues ?? [], JSON_UNESCAPED_UNICODE);
$accJson = json_encode($assetAccumulated ?? [], JSON_UNESCAPED_UNICODE);
$summary = $summary ?? ['total_asset_value' => 0, 'total_accumulated' => 0, 'net_asset_value' => 0];
$formatCell = static function ($val, array $col): string {
    $type = (string) ($col['type'] ?? '');
    if ($type === 'money') {
        return number_format((float) $val, 2);
    }
    if ($type === 'status') {
        return function_exists('__') ? __('depreciation_status_' . (string) $val) : (string) $val;
    }
    if ($val === null || $val === '') {
        return '—';
    }
    return (string) $val;
};
?>
<?php if (!empty($assetCss)) { ?>
<link href="<?php echo Rateb\App\Core\View::escape($assetCss); ?>" rel="stylesheet">
<?php } ?>

<div class="rateb-dep-page">
    <div class="rateb-dep-page-header">
        <div>
            <nav class="rateb-dep-breadcrumb" aria-label="breadcrumb">
                <a href="<?php echo rateb_app_url('accounting'); ?>"><?php echo __('dashboard'); ?></a>
                <span class="mx-1">/</span>
                <a href="<?php echo rateb_app_url('accounting'); ?>"><?php echo __('accounting'); ?></a>
                <span class="mx-1">/</span>
                <span><?php echo __('asset_depreciation'); ?></span>
            </nav>
            <h2 class="h4 mb-0"><?php echo __('asset_depreciation'); ?></h2>
        </div>
        <?php if (!empty($exportEnabled)) { ?>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo Rateb\App\Core\View::escape($exportLink('pdf')); ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-file-pdf"></i> PDF</a>
            <a href="<?php echo Rateb\App\Core\View::escape($exportLink('excel')); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="<?php echo Rateb\App\Core\View::escape($exportLink('csv')); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv"></i> CSV</a>
        </div>
        <?php } ?>
    </div>

    <?php if ($canManage) { ?>
    <div class="rateb-dep-card rateb-dep-form-card" id="rateb-dep-form">
        <div class="rateb-dep-card-header">
            <span><i class="fas fa-edit text-primary"></i> <?php echo __('depreciation_data'); ?></span>
        </div>
        <div class="rateb-dep-card-body">
            <?php if (empty($assetOptions)) { ?>
            <div class="alert alert-warning mb-0"><?php echo __('depreciation_no_assets_hint'); ?></div>
            <?php } else { ?>
            <form method="post" action="<?php echo rateb_app_url('asset-depreciation'); ?>"
                  class="rateb-dep-form-grid" data-asset-depreciation-form="1"
                  data-asset-book-values="<?php echo Rateb\App\Core\View::escape($bookJson ?: '{}'); ?>"
                  data-asset-accumulated="<?php echo Rateb\App\Core\View::escape($accJson ?: '{}'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <label class="form-label rateb-form-label"><?php echo __('assets'); ?></label>
                        <select class="form-select rateb-form-control" name="asset_id" required>
                            <option value=""><?php echo __('select'); ?>…</option>
                            <?php foreach ($assetOptions as $opt) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                            <?php } ?>
                        </select>
                        <label class="form-label rateb-form-label mt-3"><?php echo __('depreciation_type'); ?></label>
                        <select class="form-select rateb-form-control" name="depreciation_type">
                            <?php foreach ($depreciationTypes ?? [] as $opt) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape($opt['value']); ?>"<?php echo $opt['value'] === 'monthly' ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                            <?php } ?>
                        </select>
                        <label class="form-label rateb-form-label mt-3"><?php echo __('depreciation_rate'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num" type="number" step="0.01" min="0" name="depreciation_rate" placeholder="10.00">
                        <label class="form-label rateb-form-label mt-3"><?php echo __('cost_centers'); ?></label>
                        <select class="form-select rateb-form-control" name="cost_center_id">
                            <option value=""><?php echo __('select'); ?>…</option>
                            <?php foreach ($costCenterOptions ?? [] as $opt) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                            <?php } ?>
                        </select>
                        <label class="form-label rateb-form-label mt-3"><?php echo __('attach_document'); ?></label>
                        <input class="form-control rateb-form-control" type="file" disabled title="<?php echo __('coming_soon'); ?>">
                        <div class="form-text text-muted small"><?php echo __('coming_soon'); ?></div>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label rateb-form-label"><?php echo __('depreciation_date'); ?></label>
                        <input class="form-control rateb-form-control" type="date" name="period_date" value="<?php echo date('Y-m-d'); ?>" required>
                        <label class="form-label rateb-form-label mt-3"><?php echo __('useful_life_months'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num" type="number" min="0" step="1" name="useful_life_months" placeholder="60">
                        <label class="form-label rateb-form-label mt-3"><?php echo __('residual_value'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num" type="number" step="0.01" min="0" name="residual_value" value="0">
                        <label class="form-label rateb-form-label mt-3"><?php echo __('notes'); ?></label>
                        <textarea class="form-control rateb-form-control" name="notes" rows="3" placeholder="<?php echo __('notes'); ?>…"></textarea>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label rateb-form-label"><?php echo __('depreciation_no'); ?></label>
                        <input class="form-control rateb-form-control rateb-dep-readonly" type="text" readonly value="<?php echo __('depreciation_no_auto'); ?>">
                        <label class="form-label rateb-form-label mt-3"><?php echo __('depreciation_amount'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num" type="number" step="0.01" min="0" name="amount" required>
                        <label class="form-label rateb-form-label mt-3"><?php echo __('book_value_before'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num rateb-dep-readonly" type="text" readonly data-dep-before value="0.00">
                        <label class="form-label rateb-form-label mt-3"><?php echo __('book_value_after'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num rateb-dep-readonly" type="text" readonly data-dep-after value="0.00">
                        <label class="form-label rateb-form-label mt-3"><?php echo __('accumulated_depreciation'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num rateb-dep-readonly" type="text" readonly data-dep-accumulated value="0.00">
                    </div>
                </div>
                <div class="rateb-dep-form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
                    <button type="button" class="btn btn-outline-primary" data-dep-form-reset><i class="fas fa-plus"></i> <?php echo __('new'); ?></button>
                    <a href="<?php echo rateb_app_url('asset-depreciation'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
                </div>
            </form>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

    <div class="rateb-dep-card rateb-dep-filter-card">
        <div class="rateb-dep-card-header rateb-dep-filter-toggle" data-bs-toggle="collapse" data-bs-target="#rateb-dep-filter-body" aria-expanded="true">
            <span><i class="fas fa-filter text-primary"></i> <?php echo __('depreciation_search_filter'); ?></span>
            <i class="fas fa-chevron-down small text-muted"></i>
        </div>
        <div class="collapse show" id="rateb-dep-filter-body">
            <div class="rateb-dep-card-body pt-3">
                <form method="get" action="<?php echo Rateb\App\Core\View::escape($listUrl); ?>">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label rateb-form-label"><?php echo __('assets'); ?></label>
                            <select class="form-select rateb-form-control" name="asset_id">
                                <option value=""><?php echo __('all_assets'); ?></option>
                                <?php foreach ($assetOptions as $opt) { ?>
                                <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"<?php echo (string) ($filters['asset_id'] ?? '') === (string) $opt['value'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label rateb-form-label"><?php echo __('date_from'); ?></label>
                            <input class="form-control rateb-form-control" type="date" name="date_from" value="<?php echo Rateb\App\Core\View::escape((string) ($filters['date_from'] ?? '')); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label rateb-form-label"><?php echo __('date_to'); ?></label>
                            <input class="form-control rateb-form-control" type="date" name="date_to" value="<?php echo Rateb\App\Core\View::escape((string) ($filters['date_to'] ?? '')); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label rateb-form-label"><?php echo __('status'); ?></label>
                            <select class="form-select rateb-form-control" name="status">
                                <option value=""><?php echo __('all_statuses'); ?></option>
                                <?php foreach ($statusOptions as $opt) { ?>
                                <option value="<?php echo Rateb\App\Core\View::escape($opt['value']); ?>"<?php echo (string) ($filters['status'] ?? '') === (string) $opt['value'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search"></i> <?php echo __('search'); ?></button>
                            <a href="<?php echo Rateb\App\Core\View::escape($listUrl); ?>" class="btn btn-outline-secondary"><?php echo __('reset'); ?></a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="rateb-dep-card rateb-dep-print-target">
        <div class="rateb-dep-card-header">
            <span><i class="fas fa-table text-primary"></i> <?php echo __('depreciation_list'); ?></span>
        </div>
        <div class="rateb-dep-card-body">
            <div class="rateb-dep-table-toolbar">
                <?php if ($canManage) { ?>
                <a href="#rateb-dep-form" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> <?php echo __('create'); ?> <?php echo __('asset_depreciation'); ?></a>
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="<?php echo __('coming_soon'); ?>"><i class="fas fa-layer-group"></i> <?php echo __('bulk_depreciation'); ?></button>
                <?php } ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-dep-print><i class="fas fa-print"></i> <?php echo __('print'); ?></button>
            </div>

            <?php Rateb\App\Core\View::partial('table-search', ['mode' => 'client']); ?>
            <div class="table-responsive rateb-depreciation-table-wrap" data-rateb-table-search-host="1">
                <table class="table table-hover rateb-table rateb-depreciation-table mb-0">
                    <colgroup>
                        <col class="rateb-col-dep-no">
                        <col class="rateb-col-dep-asset">
                        <col class="rateb-col-dep-date">
                        <col class="rateb-col-dep-amount">
                        <col class="rateb-col-dep-book">
                        <col class="rateb-col-dep-book">
                        <col class="rateb-col-dep-book">
                        <col class="rateb-col-dep-status">
                        <col class="rateb-col-dep-actions">
                    </colgroup>
                    <thead>
                    <tr>
                        <?php foreach ($columns as $col) {
                            $type = (string) ($col['type'] ?? 'text');
                            $thClass = 'rateb-th-' . $type;
                            if (in_array($col['name'] ?? '', ['book_value_before', 'book_value', 'accumulated_total'], true)) {
                                $thClass .= ' rateb-th-book-value';
                            }
                            $headerKey = (string) ($col['header_label'] ?? $col['label'] ?? $col['name']);
                            $fullKey = (string) ($col['label'] ?? $col['name']);
                            $headerText = rateb_label($headerKey);
                            $fullText = rateb_label($fullKey);
                            $titleAttr = $fullText !== $headerText ? ' title="' . Rateb\App\Core\View::escape($fullText) . '"' : '';
                            ?>
                        <th class="<?php echo Rateb\App\Core\View::escape(trim($thClass)); ?>"<?php echo $titleAttr; ?>><?php echo Rateb\App\Core\View::escape($headerText); ?></th>
                        <?php } ?>
                        <th class="rateb-th-actions"><?php echo __('actions'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($items)) { ?>
                    <tr><td colspan="<?php echo count($columns) + 1; ?>" class="text-muted text-center py-4"><?php echo __('no_records'); ?></td></tr>
                    <?php } else {
                        foreach ($items as $row) {
                            $id = (int) ($row['id'] ?? 0);
                            $isDraft = (string) ($row['status'] ?? '') === 'draft';
                            ?>
                    <tr>
                        <?php foreach ($columns as $col) {
                            $type = (string) ($col['type'] ?? '');
                            $val = $row[$col['name']] ?? '';
                            $display = $formatCell($val, $col);
                            $class = in_array($type, ['money', 'id'], true) ? ' rateb-ltr-num' : '';
                            if ($type === 'money') {
                                $class .= ' rateb-td-money';
                            }
                            if ($type === 'status') {
                                $badge = $isDraft ? 'info' : 'success';
                                ?>
                        <td><span class="badge bg-<?php echo $badge; ?>"><?php echo Rateb\App\Core\View::escape($display); ?></span></td>
                                <?php
                                continue;
                            }
                            $titleAttr = $display !== '' && $display !== '—'
                                ? ' title="' . Rateb\App\Core\View::escape((string) $display) . '"'
                                : '';
                            ?>
                        <td class="rateb-cell-clip<?php echo $class; ?>"<?php echo $titleAttr; ?>><?php echo Rateb\App\Core\View::escape($display); ?></td>
                        <?php } ?>
                        <td class="rateb-actions-cell text-nowrap">
                            <div class="rateb-actions">
                            <a href="<?php echo rateb_app_url('asset-depreciation/' . $id); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo __('view'); ?>"><i class="fas fa-eye"></i></a>
                            <?php if ($canManage && $isDraft) { ?>
                            <a href="<?php echo rateb_app_url('asset-depreciation/' . $id . '/edit'); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
                            <form method="post" action="<?php echo rateb_app_url('asset-depreciation/' . $id . '/approve'); ?>" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success" title="<?php echo __('approve'); ?>"><i class="fas fa-check"></i></button>
                            </form>
                            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('delete'); ?>"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php } elseif ($canManage) { ?>
                            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('delete'); ?>"><i class="fas fa-trash"></i></button>
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

    <div class="rateb-dep-summary-row">
        <div class="rateb-dep-summary-card">
            <div class="rateb-dep-summary-icon"><i class="fas fa-database"></i></div>
            <div>
                <div class="rateb-dep-summary-value rateb-ltr-num"><?php echo number_format((float) ($summary['total_asset_value'] ?? 0), 2); ?></div>
                <div class="rateb-dep-summary-label"><?php echo __('total_asset_value'); ?></div>
            </div>
        </div>
        <div class="rateb-dep-summary-card">
            <div class="rateb-dep-summary-icon"><i class="fas fa-chart-pie"></i></div>
            <div>
                <div class="rateb-dep-summary-value rateb-ltr-num"><?php echo number_format((float) ($summary['total_accumulated'] ?? 0), 2); ?></div>
                <div class="rateb-dep-summary-label"><?php echo __('accumulated_depreciation'); ?></div>
            </div>
        </div>
        <div class="rateb-dep-summary-card">
            <div class="rateb-dep-summary-icon"><i class="fas fa-chart-line"></i></div>
            <div>
                <div class="rateb-dep-summary-value rateb-ltr-num"><?php echo number_format((float) ($summary['net_asset_value'] ?? 0), 2); ?></div>
                <div class="rateb-dep-summary-label"><?php echo __('net_asset_value'); ?></div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($assetJs)) { ?>
<script src="<?php echo Rateb\App\Core\View::escape($assetJs); ?>"></script>
<?php } ?>
