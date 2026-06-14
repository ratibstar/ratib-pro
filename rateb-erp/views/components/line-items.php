<?php
/** @var array<int, array<string, mixed>> $lineItems */
/** @var bool $showDeliveryCols */
/** @var bool $defaultVat15 */
$lineItems = $lineItems ?? [];
$showDeliveryCols = !empty($showDeliveryCols);
$inventoryItems = $inventoryItems ?? [];
$defaultVat15 = !empty($defaultVat15);
$unitOptions = \Rateb\App\Helpers\LineItems::unitOptions();
$unitFactors = \Rateb\App\Helpers\LineItems::unitFactors();
$taxPresets = \Rateb\App\Helpers\LineItems::taxPresets();
$colSpan = 7;
if ($lineItems === []) {
    $lineItems = [[
        'inventory_id' => '', 'item_name' => '', 'description' => '', 'sku' => '', 'quantity' => 1,
        'unit' => 'each', 'unit_price' => 0,
        'tax_name' => $defaultVat15 ? 'VAT 15%' : 'Local Sales 0%',
        'tax_rate' => $defaultVat15 ? 15 : 0,
        'excluding_tax' => 1,
    ]];
}
$req = static function (string $label): string {
    return $label . ' <span class="text-danger">*</span>';
};
?>
<div class="col-12">
    <div class="rateb-card mt-2">
        <div class="rateb-card-header d-flex justify-content-between align-items-center">
            <span><?php echo __('line_items'); ?></span>
            <button type="button" class="btn btn-sm btn-outline-primary" data-line-items-add><i class="fas fa-plus"></i> <?php echo __('add_line'); ?></button>
        </div>
        <div class="rateb-card-body p-0">
            <div class="table-responsive">
                <table class="table rateb-table mb-0" data-line-items-table<?php echo $defaultVat15 ? ' data-default-vat="15"' : ''; ?>>
                    <thead>
                    <tr>
                        <th><?php echo __('inventory'); ?></th>
                        <th><?php echo $req(__('item_name')); ?></th>
                        <th><?php echo __('sku'); ?></th>
                        <th><?php echo $req(__('quantity')); ?></th>
                        <th><?php echo __('unit_of_measure'); ?></th>
                        <th><?php echo $req(__('unit_price')); ?></th>
                        <th class="text-end"><?php echo __('line_subtotal'); ?></th>
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
                        $taxName = (string) ($line['tax_name'] ?? ($defaultVat15 ? 'VAT 15%' : 'Local Sales 0%'));
                        $invId = (int) ($line['inventory_id'] ?? 0);
                        $qtyEach = \Rateb\App\Helpers\LineItems::qtyInEach($qty, $unit);
                        ?>
                    <tr data-line-items-row>
                        <td>
                            <select class="form-select form-select-sm" name="line_inventory_id[]" data-line-inventory>
                                <option value="">—</option>
                                <?php foreach ($inventoryItems as $inv) {
                                    $invLabel = trim((string) ($inv['sku'] ?? '')) !== ''
                                        ? ($inv['sku'] . ' — ' . ($inv['item_name'] ?? ''))
                                        : (string) ($inv['item_name'] ?? '');
                                    ?>
                                <option value="<?php echo (int) $inv['id']; ?>"
                                        data-name="<?php echo Rateb\App\Core\View::escape($inv['item_name'] ?? ''); ?>"
                                        data-sku="<?php echo Rateb\App\Core\View::escape($inv['sku'] ?? ''); ?>"
                                        data-unit="<?php echo Rateb\App\Core\View::escape($inv['unit'] ?? 'each'); ?>"
                                        data-price="<?php echo Rateb\App\Core\View::escape($inv['unit_cost'] ?? 0); ?>"
                                    <?php echo $invId === (int) $inv['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($invLabel); ?></option>
                                <?php } ?>
                            </select>
                        </td>
                        <td><input class="form-control form-control-sm" name="line_item_name[]" value="<?php echo Rateb\App\Core\View::escape($line['item_name'] ?? ''); ?>" required data-line-calc></td>
                        <td><input class="form-control form-control-sm" name="line_sku[]" value="<?php echo Rateb\App\Core\View::escape($line['sku'] ?? ''); ?>"></td>
                        <td>
                            <input class="form-control form-control-sm" type="number" step="0.001" min="0.001" name="line_quantity[]" value="<?php echo Rateb\App\Core\View::escape($line['quantity'] ?? 1); ?>" required data-line-calc>
                            <small class="text-muted" data-unit-hint data-hint-template="<?php echo Rateb\App\Core\View::escape(__('unit_factor_hint', ['qty' => ':qty'])); ?>"><?php echo __('unit_factor_hint', ['qty' => number_format($qtyEach, 2)]); ?></small>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="line_unit[]" data-line-unit>
                                <?php foreach ($unitOptions as $opt) {
                                    $factor = $unitFactors[$opt] ?? 1;
                                    ?>
                                <option value="<?php echo Rateb\App\Core\View::escape($opt); ?>" data-factor="<?php echo (int) $factor; ?>"<?php echo $unit === $opt ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__('unit_' . $opt)); ?></option>
                                <?php } ?>
                            </select>
                        </td>
                        <td><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="line_unit_price[]" value="<?php echo Rateb\App\Core\View::escape($line['unit_price'] ?? 0); ?>" required data-line-calc></td>
                        <td class="text-end"><span data-line-subtotal><?php echo number_format($totals['subtotal'], 2); ?></span></td>
                        <td>
                            <select class="form-select form-select-sm" name="line_tax_name[]" data-line-tax-preset>
                                <?php foreach ($taxPresets as $preset) {
                                    $presetRate = strpos($preset, '15%') !== false ? 15 : (strpos($preset, '5%') !== false ? 5 : 0);
                                    ?>
                                <option value="<?php echo Rateb\App\Core\View::escape($preset); ?>" data-tax-rate="<?php echo $presetRate; ?>"<?php echo $taxName === $preset ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($preset); ?></option>
                                <?php } ?>
                            </select>
                            <input type="hidden" name="line_tax_rate[]" value="<?php echo Rateb\App\Core\View::escape($line['tax_rate'] ?? ($defaultVat15 ? 15 : 0)); ?>" data-line-tax-rate>
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
                        <td colspan="<?php echo $colSpan; ?>" class="text-end fw-semibold"><?php echo __('subtotal'); ?></td>
                        <td class="text-end" data-procurement-subtotal>0.00</td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td colspan="<?php echo $colSpan; ?>" class="text-end fw-semibold"><?php echo __('tax_amount'); ?> (<?php echo __('vat_15'); ?>)</td>
                        <td class="text-end" data-procurement-tax>0.00</td>
                        <td colspan="3"></td>
                    </tr>
                    <tr class="table-primary">
                        <td colspan="<?php echo $colSpan; ?>" class="text-end fw-bold"><?php echo __('total'); ?></td>
                        <td class="text-end fw-bold" data-procurement-grand-total>0.00</td>
                        <td colspan="3"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
