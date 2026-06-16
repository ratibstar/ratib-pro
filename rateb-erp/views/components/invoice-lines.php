<?php
/** @var array<int, array<string, mixed>> $lineItems */
/** @var bool $defaultVat15 */
/** @var string|null $sectionTitle */
$lineItems = $lineItems ?? [];
$defaultVat15 = !empty($defaultVat15);
$sectionTitle = $sectionTitle ?? __('invoice_lines_section');
$taxPresets = \Rateb\App\Helpers\LineItems::taxPresets();
if ($lineItems === []) {
    $lineItems = [[
        'item_name' => '',
        'description' => '',
        'quantity' => 1,
        'unit_price' => 0,
        'tax_name' => $defaultVat15 ? 'VAT 15%' : 'Local Sales 0%',
        'tax_rate' => $defaultVat15 ? 15 : 0,
        'excluding_tax' => 1,
    ]];
}
?>
<div class="rateb-invoice-lines-wrap">
    <h6 class="rateb-invoice-section-title mb-3"><i class="fas fa-list"></i> <?php echo Rateb\App\Core\View::escape($sectionTitle); ?></h6>
    <div class="rateb-card">
        <div class="rateb-card-header d-flex justify-content-between align-items-center">
            <span class="small text-muted"><?php echo __('line_items'); ?></span>
            <button type="button" class="btn btn-sm btn-outline-primary" data-line-items-add><i class="fas fa-plus"></i> <?php echo __('add_line'); ?></button>
        </div>
        <div class="rateb-card-body p-0">
            <div class="table-responsive">
                <table class="table rateb-line-items-table mb-0" data-line-items-table data-invoice-lines-table<?php echo $defaultVat15 ? ' data-default-vat="15"' : ''; ?>>
                    <thead>
                    <tr>
                        <th><?php echo __('description'); ?> <span class="text-danger">*</span></th>
                        <th><?php echo __('quantity'); ?></th>
                        <th><?php echo __('unit_price'); ?></th>
                        <th><?php echo __('taxes'); ?></th>
                        <th class="text-end"><?php echo __('line_total'); ?></th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($lineItems as $line) {
                        $qty = (float) ($line['quantity'] ?? 1);
                        $price = (float) ($line['unit_price'] ?? 0);
                        $taxRate = (float) ($line['tax_rate'] ?? ($defaultVat15 ? 15 : 0));
                        $excluding = !isset($line['excluding_tax']) || (int) $line['excluding_tax'] === 1;
                        $totals = \Rateb\App\Helpers\LineItems::lineTotals($qty, $price, $taxRate, $excluding);
                        $taxName = (string) ($line['tax_name'] ?? ($defaultVat15 ? 'VAT 15%' : 'Local Sales 0%'));
                        ?>
                    <tr data-line-items-row>
                        <td>
                            <input class="form-control form-control-sm" name="line_item_name[]" value="<?php echo Rateb\App\Core\View::escape((string) ($line['item_name'] ?? '')); ?>" data-line-calc required>
                            <input type="hidden" name="line_description[]" value="<?php echo Rateb\App\Core\View::escape((string) ($line['description'] ?? '')); ?>">
                            <input type="hidden" name="line_sku[]" value="">
                            <input type="hidden" name="line_inventory_id[]" value="0">
                            <input type="hidden" name="line_unit[]" value="unit">
                        </td>
                        <td><input class="form-control form-control-sm" type="number" step="0.001" min="0.001" name="line_quantity[]" value="<?php echo Rateb\App\Core\View::escape((string) $qty); ?>" data-line-calc></td>
                        <td><input class="form-control form-control-sm rateb-ltr-num" type="number" step="any" min="0" name="line_unit_price[]" value="<?php echo Rateb\App\Core\View::escape((string) $price); ?>" data-line-calc></td>
                        <td>
                            <select class="form-select form-select-sm" name="line_tax_name[]" data-line-tax-preset>
                                <?php foreach ($taxPresets as $preset) {
                                    $presetRate = strpos($preset, '15%') !== false ? 15 : (strpos($preset, '5%') !== false ? 5 : 0);
                                    ?>
                                <option value="<?php echo Rateb\App\Core\View::escape($preset); ?>" data-tax-rate="<?php echo $presetRate; ?>"<?php echo $taxName === $preset ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(\Rateb\App\Helpers\LineItems::taxPresetLabel($preset)); ?></option>
                                <?php } ?>
                            </select>
                            <input type="hidden" name="line_excluding_tax[]" value="1">
                            <input type="hidden" name="line_tax_rate[]" value="<?php echo Rateb\App\Core\View::escape((string) $taxRate); ?>" data-line-tax-rate>
                        </td>
                        <td class="text-end"><span data-line-total><?php echo number_format($totals['total'], 2); ?></span></td>
                        <td><button type="button" class="btn btn-sm btn-outline-danger" data-line-items-remove><i class="fas fa-times"></i></button></td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
