<?php
/** @var array<int, array<string, mixed>> $lineItems */
/** @var bool $showDeliveryCols */
$lineItems = $lineItems ?? [];
$showDeliveryCols = !empty($showDeliveryCols);
$unitOptions = \Rateb\App\Helpers\LineItems::unitOptions();
$taxPresets = \Rateb\App\Helpers\LineItems::taxPresets();
if ($lineItems === []) {
    $lineItems = [[
        'item_name' => '', 'description' => '', 'sku' => '', 'quantity' => 1,
        'delivered_qty' => 0, 'invoiced_qty' => 0, 'unit' => 'each',
        'unit_price' => 0, 'tax_name' => 'Local Sales 0%', 'tax_rate' => 0, 'excluding_tax' => 1,
    ]];
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
                        <th><?php echo __('description'); ?></th>
                        <th><?php echo __('sku'); ?></th>
                        <th><?php echo __('quantity'); ?></th>
                        <?php if ($showDeliveryCols) { ?>
                        <th><?php echo __('delivered'); ?></th>
                        <th><?php echo __('invoiced'); ?></th>
                        <?php } ?>
                        <th><?php echo __('unit_of_measure'); ?></th>
                        <th><?php echo __('unit_price'); ?></th>
                        <th><?php echo __('taxes'); ?></th>
                        <th class="text-center"><?php echo __('excluding_tax'); ?></th>
                        <th class="text-end"><?php echo __('line_total'); ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lineItems as $line) {
                        $qty = (float) ($line['quantity'] ?? 1);
                        $price = (float) ($line['unit_price'] ?? 0);
                        $taxRate = (float) ($line['tax_rate'] ?? 0);
                        $excluding = !isset($line['excluding_tax']) || (int) $line['excluding_tax'] === 1;
                        $totals = \Rateb\App\Helpers\LineItems::lineTotals($qty, $price, $taxRate, $excluding);
                        $unit = (string) ($line['unit'] ?? 'each');
                        $taxName = (string) ($line['tax_name'] ?? 'Local Sales 0%');
                        ?>
                    <tr data-line-items-row>
                        <td><input class="form-control form-control-sm" name="line_item_name[]" value="<?php echo Rateb\App\Core\View::escape($line['item_name'] ?? ''); ?>" required data-line-calc></td>
                        <td><input class="form-control form-control-sm" name="line_description[]" value="<?php echo Rateb\App\Core\View::escape($line['description'] ?? ''); ?>"></td>
                        <td><input class="form-control form-control-sm" name="line_sku[]" value="<?php echo Rateb\App\Core\View::escape($line['sku'] ?? ''); ?>"></td>
                        <td><input class="form-control form-control-sm" type="number" step="0.001" min="0" name="line_quantity[]" value="<?php echo Rateb\App\Core\View::escape($line['quantity'] ?? 1); ?>" data-line-calc></td>
                        <?php if ($showDeliveryCols) { ?>
                        <td><input class="form-control form-control-sm" type="number" step="0.001" min="0" name="line_delivered_qty[]" value="<?php echo Rateb\App\Core\View::escape($line['delivered_qty'] ?? 0); ?>"></td>
                        <td><input class="form-control form-control-sm" type="number" step="0.001" min="0" name="line_invoiced_qty[]" value="<?php echo Rateb\App\Core\View::escape($line['invoiced_qty'] ?? 0); ?>"></td>
                        <?php } ?>
                        <td>
                            <select class="form-select form-select-sm" name="line_unit[]">
                                <?php foreach ($unitOptions as $opt) { ?>
                                <option value="<?php echo Rateb\App\Core\View::escape($opt); ?>"<?php echo $unit === $opt ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__('unit_' . $opt)); ?></option>
                                <?php } ?>
                            </select>
                        </td>
                        <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="line_unit_price[]" value="<?php echo Rateb\App\Core\View::escape($line['unit_price'] ?? 0); ?>" data-line-calc></td>
                        <td>
                            <select class="form-select form-select-sm" name="line_tax_name[]" data-line-tax-preset>
                                <?php foreach ($taxPresets as $preset) {
                                    $presetRate = str_contains($preset, '15%') ? 15 : (str_contains($preset, '5%') ? 5 : 0);
                                    ?>
                                <option value="<?php echo Rateb\App\Core\View::escape($preset); ?>" data-tax-rate="<?php echo $presetRate; ?>"<?php echo $taxName === $preset ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($preset); ?></option>
                                <?php } ?>
                            </select>
                            <input type="hidden" name="line_tax_rate[]" value="<?php echo Rateb\App\Core\View::escape($line['tax_rate'] ?? 0); ?>" data-line-tax-rate>
                        </td>
                        <td class="text-center">
                            <select class="form-select form-select-sm" name="line_excluding_tax[]" data-line-calc>
                                <option value="1"<?php echo $excluding ? ' selected' : ''; ?>><?php echo __('yes'); ?></option>
                                <option value="0"<?php echo !$excluding ? ' selected' : ''; ?>><?php echo __('no'); ?></option>
                            </select>
                        </td>
                        <td class="text-end"><span data-line-total><?php echo number_format($totals['total'], 2); ?></span></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" data-line-items-remove><i class="fas fa-times"></i></button></td>
                    </tr>
                    <?php } ?>
                    </tbody>
                    <tfoot>
                    <tr>
                        <td colspan="<?php echo $showDeliveryCols ? 10 : 8; ?>" class="text-end fw-semibold"><?php echo __('subtotal'); ?></td>
                        <td class="text-end" data-procurement-subtotal>0.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="<?php echo $showDeliveryCols ? 10 : 8; ?>" class="text-end fw-semibold"><?php echo __('tax_amount'); ?></td>
                        <td class="text-end" data-procurement-tax>0.00</td>
                        <td></td>
                    </tr>
                    <tr class="table-primary">
                        <td colspan="<?php echo $showDeliveryCols ? 10 : 8; ?>" class="text-end fw-bold"><?php echo __('total'); ?></td>
                        <td class="text-end fw-bold" data-procurement-grand-total>0.00</td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
