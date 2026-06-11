<?php
/** @var array<int, array<string, mixed>> $lineItems */
$lineItems = $lineItems ?? [];
if ($lineItems === []) {
    $lineItems = [['item_name' => '', 'sku' => '', 'quantity' => 1, 'unit' => 'unit', 'unit_price' => 0]];
}
?>
<div class="col-12">
    <div class="rateb-card mt-2">
        <div class="rateb-card-header d-flex justify-content-between align-items-center">
            <span><?php echo __('line_items'); ?></span>
            <button type="button" class="btn btn-sm btn-outline-primary" data-line-items-add><i class="fas fa-plus"></i> <?php echo __('add_line'); ?></button>
        </div>
        <div class="rateb-card-body p-0">
            <div class="table-responsive">
                <table class="table rateb-table mb-0" data-line-items-table>
                    <thead>
                    <tr>
                        <th><?php echo __('item_name'); ?></th>
                        <th><?php echo __('sku'); ?></th>
                        <th><?php echo __('quantity'); ?></th>
                        <th><?php echo __('unit'); ?></th>
                        <th><?php echo __('unit_price'); ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lineItems as $line) { ?>
                    <tr data-line-items-row>
                        <td><input class="form-control form-control-sm" name="line_item_name[]" value="<?php echo Rateb\App\Core\View::escape($line['item_name'] ?? ''); ?>" required></td>
                        <td><input class="form-control form-control-sm" name="line_sku[]" value="<?php echo Rateb\App\Core\View::escape($line['sku'] ?? ''); ?>"></td>
                        <td><input class="form-control form-control-sm" type="number" step="0.001" min="0" name="line_quantity[]" value="<?php echo Rateb\App\Core\View::escape($line['quantity'] ?? 1); ?>"></td>
                        <td><input class="form-control form-control-sm" name="line_unit[]" value="<?php echo Rateb\App\Core\View::escape($line['unit'] ?? 'unit'); ?>"></td>
                        <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="line_unit_price[]" value="<?php echo Rateb\App\Core\View::escape($line['unit_price'] ?? 0); ?>"></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" data-line-items-remove><i class="fas fa-times"></i></button></td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
