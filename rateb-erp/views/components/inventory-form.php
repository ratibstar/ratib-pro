<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var array<string, list<array{value: string|int, label: string}>> $lookups */
/** @var array<string, mixed>|null $attachment */
/** @var string $warehouseItemsUrl */
/** @var string $assetJs */
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
$lookups = $lookups ?? [];
$movementTypes = $lookups['inventory_movement_types'] ?? [];
$currentQty = $isEdit ? (float) ($item['quantity'] ?? 0) : 0;
$itemCode = $isEdit ? (string) ($item['item_code'] ?? '') : '';
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <?php
        $opsCompanyId = (int) ($opsCompanyId ?? 0);
        $warehouseOptionsCount = (int) ($warehouseOptionsCount ?? count($lookups['warehouses'] ?? []));
        $categoryOptionsCount = (int) ($categoryOptionsCount ?? count($lookups['product_categories'] ?? []));
        if (function_exists('rateb_is_super_admin') && rateb_is_super_admin() && $opsCompanyId < 1) { ?>
        <div class="alert alert-warning"><?php echo __('select_company_ops'); ?></div>
        <?php } elseif ($warehouseOptionsCount < 1) { ?>
        <div class="alert alert-warning">
            <?php echo __('inventory_no_warehouses'); ?>
            <?php if (!empty($warehousesCreateUrl)) { ?>
            <a class="alert-link ms-1" href="<?php echo Rateb\App\Core\View::escape($warehousesCreateUrl); ?>"><?php echo __('warehouses'); ?></a>
            <?php } ?>
        </div>
        <?php } elseif ($categoryOptionsCount < 1) { ?>
        <div class="alert alert-info py-2">
            <?php echo __('inventory_no_categories'); ?>
            <?php if (!empty($categoriesCreateUrl)) { ?>
            <a class="alert-link ms-1" href="<?php echo Rateb\App\Core\View::escape($categoriesCreateUrl); ?>"><?php echo __('product_categories'); ?></a>
            <?php } ?>
        </div>
        <?php } ?>
        <form method="post" action="<?php echo $action; ?>" enctype="multipart/form-data" data-inventory-form data-rateb-offline-writable="1"
              data-warehouse-items-url="<?php echo Rateb\App\Core\View::escape($warehouseItemsUrl ?? ''); ?>"
              data-is-edit="<?php echo $isEdit ? '1' : '0'; ?>"
              data-current-qty="<?php echo Rateb\App\Core\View::escape((string) $currentQty); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php if ($isEdit && (int) (($item ?? [])['company_id'] ?? 0) > 0) { ?>
            <input type="hidden" name="company_id" value="<?php echo (int) $item['company_id']; ?>">
            <?php } ?>

            <h6 class="text-warning mb-3"><i class="fas fa-exchange-alt"></i> <?php echo __('movement_info'); ?></h6>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label rateb-form-label" for="f_item_code"><?php echo __('reference_no'); ?></label>
                    <input class="form-control rateb-form-control" type="text" id="f_item_code" readonly
                           value="<?php echo $itemCode !== '' ? Rateb\App\Core\View::escape($itemCode) : __('auto_generated'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label rateb-form-label" for="f_movement_type"><?php echo __('movement_type'); ?></label>
                    <select class="form-select rateb-form-control" id="f_movement_type" name="movement_type" data-inventory-movement-type>
                        <?php if ($isEdit) { ?>
                        <option value=""><?php echo __('no_movement'); ?></option>
                        <?php } ?>
                        <?php foreach ($movementTypes as $opt) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"
                            <?php echo (!$isEdit && (string) $opt['value'] === 'in') ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape((string) $opt['label']); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <?php if ($isEdit) { ?>
                <div class="col-md-4">
                    <label class="form-label rateb-form-label"><?php echo __('created_at'); ?></label>
                    <input class="form-control rateb-form-control" type="text" readonly
                           value="<?php echo Rateb\App\Core\View::escape((string) ($item['created_at'] ?? '')); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label rateb-form-label"><?php echo __('current_quantity'); ?></label>
                    <input class="form-control rateb-form-control" type="text" readonly data-inventory-current-qty
                           value="<?php echo Rateb\App\Core\View::escape((string) $currentQty); ?>">
                </div>
                <?php } ?>
            </div>

            <h6 class="text-warning mb-3"><i class="fas fa-boxes"></i> <?php echo __('item_details'); ?></h6>
            <div class="row g-3">
                <?php if (!$isEdit) { ?>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_existing_inventory_id"><?php echo __('existing_item'); ?></label>
                    <select class="form-select rateb-form-control" id="f_existing_inventory_id" name="existing_inventory_id" data-inventory-existing>
                        <option value=""><?php echo __('new_item'); ?></option>
                    </select>
                    <small class="text-muted"><?php echo __('warehouse_items_hint'); ?></small>
                </div>
                <?php } ?>

                <?php foreach ($fields as $field) {
                    $name = (string) $field['name'];
                    if ($name === 'notes') {
                        continue;
                    }
                    $value = $item[$name] ?? ($field['default'] ?? '');
                    if ($name === 'item_name' && !$isEdit) {
                        ?>
                <div class="col-md-6" data-inventory-new-item>
                    <?php Rateb\App\Core\View::partial('form-field', [
                        'field' => $field,
                        'value' => $value,
                        'lookups' => $lookups,
                    ]); ?>
                </div>
                        <?php
                        continue;
                    }
                    if ($name === 'quantity') {
                        ?>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_quantity">
                        <span data-inventory-qty-label data-movement-label="<?php echo __('movement_quantity'); ?>"><?php echo __('quantity'); ?></span>
                        <span class="text-danger">*</span>
                    </label>
                    <input class="form-control rateb-form-control" type="number" step="0.001" min="0"
                           id="f_quantity" name="quantity" value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>"
                           data-inventory-quantity required>
                </div>
                        <?php
                        continue;
                    }
                    Rateb\App\Core\View::partial('form-field', [
                        'field' => $field,
                        'value' => $value,
                        'lookups' => $lookups,
                    ]);
                } ?>

                <div class="col-12">
                    <div class="alert alert-warning d-none py-2 small mb-0" data-inventory-reorder-alert role="alert">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo __('reorder_alert'); ?>
                    </div>
                    <div class="alert alert-danger d-none py-2 small mb-0 mt-2" data-inventory-max-alert role="alert">
                        <i class="fas fa-ban"></i> <?php echo __('max_stock_alert'); ?>
                    </div>
                </div>
            </div>

            <h6 class="text-warning mb-3 mt-4"><i class="fas fa-calculator"></i> <?php echo __('cost_and_valuation'); ?></h6>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_line_total"><?php echo __('line_total_value'); ?></label>
                    <input class="form-control rateb-form-control" type="text" id="f_line_total" readonly
                           data-inventory-line-total value="0.00">
                </div>
            </div>

            <div class="row g-3">
                <?php
                $notesField = ['name' => 'notes', 'label' => 'notes', 'type' => 'textarea', 'col' => 'col-12', 'rows' => 3];
                Rateb\App\Core\View::partial('form-field', [
                    'field' => $notesField,
                    'value' => $item['notes'] ?? '',
                    'lookups' => $lookups,
                ]);
                ?>
                <?php if (!empty($attachment) && is_array($attachment)) {
                    Rateb\App\Core\View::partial('entity-attachment-field', $attachment);
                } ?>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
<?php if (!empty($assetJs)) { ?>
<script src="<?php echo Rateb\App\Core\View::escape($assetJs); ?>" defer></script>
<?php } ?>
<?php if ($isEdit && !empty($item['id'])) {
    $docBarcode = (new \Rateb\App\Services\DocumentBarcodeService())->labelData('inventory', (int) $item['id']);
    Rateb\App\Core\View::partial('document-barcode-label', ['docBarcode' => $docBarcode]);
} ?>
