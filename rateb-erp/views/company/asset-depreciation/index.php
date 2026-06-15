<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<string, mixed> $filters */
/** @var array<int, array{value:string|int,label:string}> $assetOptions */
/** @var array<int, array{value:string,label:string}> $statusOptions */
/** @var array<int, array{name:string,label:string,type?:string}> $columns */
$routePrefix = rateb_app_route('asset-depreciation');
$listUrl = rateb_app_url('asset-depreciation');
$exportBase = $exportRoute ?? rateb_app_url('asset-depreciation/export');
$exportQuery = array_filter([
    'asset_id' => (int) ($filters['asset_id'] ?? 0) ?: null,
    'status' => (string) ($filters['status'] ?? ''),
    'date_from' => (string) ($filters['date_from'] ?? ''),
    'date_to' => (string) ($filters['date_to'] ?? ''),
], static fn ($v) => $v !== null && $v !== '');
$exportLink = static function (string $format) use ($exportBase, $exportQuery): string {
    $q = array_merge($exportQuery, ['format' => $format]);
    return $exportBase . '?' . http_build_query($q);
};
$canManage = $canManage ?? rateb_can_manage_entity('asset-depreciation');
$formatCell = static function ($val, array $col): string {
    $type = (string) ($col['type'] ?? '');
    if ($type === 'money') {
        return number_format((float) $val, 2);
    }
    if ($type === 'status') {
        $key = 'depreciation_status_' . (string) $val;
        return function_exists('__') ? __($key) : (string) $val;
    }
    if ($val === null || $val === '') {
        return '—';
    }
    return (string) $val;
};
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? __('asset_depreciation')); ?></span>
        <?php if (!empty($exportEnabled)) { ?>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo Rateb\App\Core\View::escape($exportLink('csv')); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-csv"></i> CSV</a>
            <a href="<?php echo Rateb\App\Core\View::escape($exportLink('excel')); ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="<?php echo Rateb\App\Core\View::escape($exportLink('pdf')); ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fas fa-file-pdf"></i> PDF / <?php echo __('print'); ?></a>
        </div>
        <?php } ?>
    </div>
    <div class="rateb-card-body">
        <div class="rateb-filter-panel">
            <form method="get" action="<?php echo Rateb\App\Core\View::escape($listUrl); ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label rateb-form-label"><?php echo __('assets'); ?></label>
                        <select class="form-select rateb-form-control" name="asset_id">
                            <option value=""><?php echo __('all_assets'); ?></option>
                            <?php foreach ($assetOptions as $opt) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"<?php echo (string) ($filters['asset_id'] ?? '') === (string) $opt['value'] ? ' selected' : ''; ?>>
                                <?php echo Rateb\App\Core\View::escape($opt['label']); ?>
                            </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label rateb-form-label"><?php echo __('status'); ?></label>
                        <select class="form-select rateb-form-control" name="status">
                            <option value=""><?php echo __('all_statuses'); ?></option>
                            <?php foreach ($statusOptions as $opt) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape($opt['value']); ?>"<?php echo (string) ($filters['status'] ?? '') === (string) $opt['value'] ? ' selected' : ''; ?>>
                                <?php echo Rateb\App\Core\View::escape($opt['label']); ?>
                            </option>
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
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><?php echo __('filter'); ?></button>
                        <a href="<?php echo Rateb\App\Core\View::escape($listUrl); ?>" class="btn btn-outline-secondary"><?php echo __('reset'); ?></a>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($canManage) {
            $formFields = \Rateb\App\Services\FormLookupService::assetDepreciationFormFields();
            $lookups = (new \Rateb\App\Services\FormLookupService())->forFields($formFields);
            $bookJson = json_encode($assetBookValues ?? [], JSON_UNESCAPED_UNICODE);
            ?>
        <div class="rateb-card mb-4 border-secondary-subtle">
            <div class="rateb-card-header py-2"><i class="fas fa-plus-circle"></i> <?php echo __('create'); ?> <?php echo __('asset_depreciation'); ?></div>
            <div class="rateb-card-body pb-3">
                <?php if (empty($assetOptions)) { ?>
                <div class="alert alert-warning mb-0"><?php echo __('depreciation_no_assets_hint'); ?></div>
                <?php } else { ?>
                <form method="post" action="<?php echo rateb_app_url('asset-depreciation'); ?>"
                      class="row g-3" data-asset-depreciation-form="1"
                      data-asset-book-values="<?php echo Rateb\App\Core\View::escape($bookJson ?: '{}'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <?php foreach ($formFields as $field) {
                        $value = (string) ($field['default'] ?? '');
                        Rateb\App\Core\View::partial('form-field', [
                            'field' => $field,
                            'value' => $value,
                            'lookups' => $lookups,
                        ]);
                    } ?>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label"><?php echo __('book_value_before'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num" type="text" readonly data-dep-before value="0.00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label"><?php echo __('book_value_after'); ?></label>
                        <input class="form-control rateb-form-control rateb-ltr-num" type="text" readonly data-dep-after value="0.00">
                    </div>
                    <div class="col-12">
                        <div class="rateb-dep-preview alert alert-info py-2 mb-0 d-none" data-dep-preview>
                            <i class="fas fa-calculator"></i> <?php echo __('depreciation_preview_note'); ?>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
                    </div>
                </form>
                <?php } ?>
            </div>
        </div>
        <?php if (!empty($assetJs)) { ?>
        <script src="<?php echo Rateb\App\Core\View::escape($assetJs); ?>"></script>
        <?php }
        } ?>

        <h6 class="rateb-section-heading"><i class="fas fa-table"></i> <?php echo __('depreciation_records'); ?></h6>

        <?php Rateb\App\Core\View::partial('table-search', ['mode' => 'client']); ?>
        <div class="table-responsive rateb-depreciation-table-wrap" data-rateb-table-search-host="1">
            <table class="table table-hover rateb-table rateb-depreciation-table mb-0">
                <thead>
                <tr>
                    <?php foreach ($columns as $col) {
                        $type = (string) ($col['type'] ?? 'text');
                        $thClass = 'rateb-th-' . $type;
                        if (in_array($col['name'] ?? '', ['book_value_before', 'book_value'], true)) {
                            $thClass .= ' rateb-th-book-value';
                        }
                        ?>
                    <th class="<?php echo Rateb\App\Core\View::escape(trim($thClass)); ?>"><?php echo Rateb\App\Core\View::escape(rateb_label((string) ($col['label'] ?? $col['name']))); ?></th>
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
                            $badge = $isDraft ? 'warning' : 'success';
                            ?>
                    <td><span class="badge bg-<?php echo $badge; ?>"><?php echo Rateb\App\Core\View::escape($display); ?></span></td>
                            <?php
                            continue;
                        }
                        ?>
                    <td class="<?php echo trim($class); ?>"><?php echo Rateb\App\Core\View::escape($display); ?></td>
                    <?php } ?>
                    <td class="rateb-actions text-nowrap">
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
                    </td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
