<?php
/** @var array<int, array<string, mixed>> $lineItems */
/** @var bool $showDeliveryCols */
/** @var bool $defaultVat15 */
/** @var bool $showTableTotals */
$lineItems = $lineItems ?? [];
$showDeliveryCols = !empty($showDeliveryCols);
$inventoryItems = $inventoryItems ?? [];
$defaultVat15 = !empty($defaultVat15);
$showTableTotals = $showTableTotals ?? true;
$unitOptions = \Rateb\App\Helpers\LineItems::unitOptions();
$unitFactors = \Rateb\App\Helpers\LineItems::unitFactors();
$taxPresets = \Rateb\App\Helpers\LineItems::taxPresets();
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
            <div class="table-responsive rateb-line-items-wrap">
                <table class="table rateb-line-items-table mb-0" data-line-items-table<?php echo $defaultVat15 ? ' data-default-vat="15"' : ''; ?>>
                    <colgroup>
                        <col class="rateb-col-inv">
                        <col class="rateb-col-name">
                        <col class="rateb-col-sku">
                        <col class="rateb-col-qty">
                        <col class="rateb-col-unit">
                        <col class="rateb-col-price">
                        <col class="rateb-col-subtotal">
                        <col class="rateb-col-tax">
                        <col class="rateb-col-total">
                        <col class="rateb-col-action">
                    </colgroup>
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
                        <th class="text-end"><?php echo __('line_total'); ?></th>
                        <th class="rateb-line-actions"></th>
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
                        <td class="rateb-line-inv">
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
                        <td class="rateb-line-name">
                            <input class="form-control form-control-sm" name="line_item_name[]" value="<?php echo Rateb\App\Core\View::escape($line['item_name'] ?? ''); ?>" required data-line-calc>
                        </td>
                        <td class="rateb-line-sku">
                            <input class="form-control form-control-sm rateb-ltr-num" name="line_sku[]" value="<?php echo Rateb\App\Core\View::escape($line['sku'] ?? ''); ?>">
                        </td>
                        <td class="rateb-line-qty">
                            <input class="form-control form-control-sm rateb-ltr-num" type="number" step="0.001" min="0.001" name="line_quantity[]" value="<?php echo Rateb\App\Core\View::escape($line['quantity'] ?? 1); ?>" required data-line-calc>
                        </td>
                        <td class="rateb-line-unit">
                            <select class="form-select form-select-sm" name="line_unit[]" data-line-unit>
                                <?php foreach ($unitOptions as $opt) {
                                    $factor = $unitFactors[$opt] ?? 1;
                                    ?>
                                <option value="<?php echo Rateb\App\Core\View::escape($opt); ?>" data-factor="<?php echo (int) $factor; ?>"<?php echo $unit === $opt ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__('unit_' . $opt)); ?></option>
                                <?php } ?>
                            </select>
                            <small class="rateb-line-unit-hint rateb-ltr-num" data-unit-hint data-hint-template="<?php echo Rateb\App\Core\View::escape(__('unit_factor_hint', ['qty' => ':qty'])); ?>"><?php echo __('unit_factor_hint', ['qty' => number_format($qtyEach, 2)]); ?></small>
                        </td>
                        <td class="rateb-line-price">
                            <input class="form-control form-control-sm rateb-ltr-num" type="number" step="any" min="0" name="line_unit_price[]" value="<?php echo Rateb\App\Core\View::escape($line['unit_price'] ?? 0); ?>" required data-line-calc>
                        </td>
                        <td class="text-end rateb-line-amount"><span data-line-subtotal><?php echo number_format($totals['subtotal'], 2); ?></span></td>
                        <td class="rateb-line-tax-cell">
                            <div class="rateb-line-tax-stack">
                                <select class="form-select form-select-sm" name="line_tax_name[]" data-line-tax-preset>
                                    <?php foreach ($taxPresets as $preset) {
                                        $presetRate = strpos($preset, '15%') !== false ? 15 : (strpos($preset, '5%') !== false ? 5 : 0);
                                        ?>
                                    <option value="<?php echo Rateb\App\Core\View::escape($preset); ?>" data-tax-rate="<?php echo $presetRate; ?>"<?php echo $taxName === $preset ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($preset); ?></option>
                                    <?php } ?>
                                </select>
                                <select class="form-select form-select-sm" name="line_excluding_tax[]" data-line-calc title="<?php echo __('excluding_tax'); ?>">
                                    <option value="1"<?php echo $excluding ? ' selected' : ''; ?>><?php echo __('excluding_tax_yes'); ?></option>
                                    <option value="0"<?php echo !$excluding ? ' selected' : ''; ?>><?php echo __('excluding_tax_no'); ?></option>
                                </select>
                            </div>
                            <input type="hidden" name="line_tax_rate[]" value="<?php echo Rateb\App\Core\View::escape($line['tax_rate'] ?? ($defaultVat15 ? 15 : 0)); ?>" data-line-tax-rate>
                        </td>
                        <td class="text-end rateb-line-amount"><span data-line-total><?php echo number_format($totals['total'], 2); ?></span></td>
                        <td class="rateb-line-actions">
                            <button type="button" class="btn btn-sm btn-outline-danger" data-line-items-remove title="<?php echo __('delete'); ?>"><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($showTableTotals) { ?>
            <div class="rateb-line-items-totals px-3 py-3">
                <div class="row g-2 justify-content-end text-end">
                    <div class="col-sm-4 col-md-3">
                        <span class="text-muted"><?php echo __('subtotal'); ?></span>
                        <div class="fw-semibold rateb-ltr-num"><span data-procurement-subtotal>0.00</span></div>
                    </div>
                    <div class="col-sm-4 col-md-3">
                        <span class="text-muted"><?php echo __('tax_amount'); ?> (<?php echo __('vat_15'); ?>)</span>
                        <div class="fw-semibold rateb-ltr-num"><span data-procurement-tax>0.00</span></div>
                    </div>
                    <div class="col-sm-4 col-md-3">
                        <span class="text-muted"><?php echo __('total'); ?></span>
                        <div class="fw-bold text-primary rateb-ltr-num"><span data-procurement-grand-total>0.00</span></div>
                    </div>
                </div>
            </div>
            <?php } else { ?>
            <span class="d-none" data-procurement-subtotal>0.00</span>
            <span class="d-none" data-procurement-tax>0.00</span>
            <span class="d-none" data-procurement-grand-total>0.00</span>
            <?php } ?>
        </div>
    </div>
</div>
