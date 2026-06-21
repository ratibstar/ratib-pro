<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var array<int, array<string, mixed>> $lineItems */
/** @var string $entityType purchase_request|purchase_order */
/** @var string $totalField total_estimated|total_amount */
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int)$item['id']) : rateb_url($routePrefix);
$entityType = (string) ($entityType ?? 'purchase_request');
$totalField = (string) ($totalField ?? 'total_estimated');
$defaultVat15 = !empty($defaultVat15) || $entityType === 'purchase_request';
$workflow = $workflow ?? null;
$companyId = (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0);
$lookups = (new \Rateb\App\Services\FormLookupService())->forFields($fields);
$estimatedTotalManual = false;
if ($entityType === 'purchase_request' && $isEdit) {
    $storedTotal = (float) ($item[$totalField] ?? 0);
    $lineRows = $lineItems ?? [];
    if ($lineRows === []) {
        $estimatedTotalManual = $storedTotal > 0;
    } else {
        $agg = \Rateb\App\Helpers\LineItems::aggregateTotals($lineRows);
        $estimatedTotalManual = $storedTotal > 0 && abs($storedTotal - $agg['total']) > 0.009;
    }
}
$prLineLookups = [];
if ($entityType === 'purchase_request') {
    $prLineLookups = (new \Rateb\App\Services\FormLookupService())->forFields([
        ['lookup' => 'suppliers'],
        ['lookup' => 'warehouses'],
        ['lookup' => 'chart_of_accounts'],
    ]);
}
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>" enctype="multipart/form-data" data-procurement-form>
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <?php if ($entityType === 'purchase_order') {
                    Rateb\App\Core\View::partial('procurement-workflow-banner', ['workflow' => $workflow]);
                } ?>
                <?php foreach ($fields as $field) {
                    $name = $field['name'];
                    $value = $item[$name] ?? ($field['default'] ?? '');
                    if ($name === 'notes') {
                        Rateb\App\Core\View::partial('procurement-notes-field', [
                            'item' => $item,
                            'entityType' => $entityType,
                            'companyId' => $companyId,
                        ]);
                        continue;
                    }
                    if (in_array($name, [$totalField], true) && $entityType === 'purchase_request') {
                        continue;
                    }
                    if (in_array($name, [$totalField], true)) {
                        ?>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php echo Rateb\App\Core\View::escape(rateb_label((string) ($field['label'] ?? $name))); ?>
                    </label>
                    <input class="form-control rateb-form-control" type="number" step="0.01" min="0"
                           id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
                           name="<?php echo Rateb\App\Core\View::escape($name); ?>"
                           value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>"
                           readonly data-procurement-total-field>
                </div>
                        <?php
                        continue;
                    }
                    if (in_array($name, ['discount_amount', 'shipping_amount'], true)) {
                        ?>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php echo Rateb\App\Core\View::escape(rateb_label((string) ($field['label'] ?? $name))); ?>
                    </label>
                    <input class="form-control rateb-form-control" type="number" step="0.01" min="0"
                           id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
                           name="<?php echo Rateb\App\Core\View::escape($name); ?>"
                           value="<?php echo Rateb\App\Core\View::escape((string) ($value ?: 0)); ?>"
                           data-procurement-adjust>
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
                <?php Rateb\App\Core\View::partial('line-items', [
                    'lineItems' => $lineItems ?? [],
                    'inventoryItems' => $inventoryItems ?? [],
                    'defaultVat15' => $defaultVat15,
                    'lookups' => $lookups,
                    'showTableTotals' => $entityType !== 'purchase_order',
                    'lineItemContext' => $entityType,
                    'supplierOptions' => $prLineLookups['suppliers'] ?? [],
                    'warehouseOptions' => $prLineLookups['warehouses'] ?? [],
                    'chartAccounts' => $prLineLookups['chart_of_accounts'] ?? [],
                    'lineAttachmentRoute' => $entityType === 'purchase_request'
                        ? rateb_url(rateb_app_route('purchase-requests/line-attachment'))
                        : '',
                ]); ?>
                <?php if ($entityType === 'purchase_request') {
                    Rateb\App\Core\View::partial('procurement-estimated-total', [
                        'value' => (float) (($item ?? [])[$totalField] ?? 0),
                        'currency' => (string) (($item ?? [])['currency'] ?? 'SAR'),
                        'manual' => $estimatedTotalManual,
                        'fieldName' => $totalField,
                    ]);
                } ?>
                <?php if ($entityType === 'purchase_order') {
                    Rateb\App\Core\View::partial('procurement-summary', [
                        'currency' => (string) ($item['currency'] ?? 'SAR'),
                        'discount' => (float) ($item['discount_amount'] ?? 0),
                        'shipping' => (float) ($item['shipping_amount'] ?? 0),
                    ]);
                } ?>
            </div>
            <div class="mt-4 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <?php if ($isEdit && $entityType === 'purchase_request' && (string)($item['status'] ?? '') === 'draft') { ?>
                <button type="submit" formaction="<?php echo rateb_url($routePrefix . '/' . (int)$item['id'] . '/submit'); ?>" class="btn btn-success"><?php echo __('submit_for_approval'); ?></button>
                <?php } ?>
                <?php if ($isEdit && $entityType === 'purchase_order' && in_array((string)($item['status'] ?? ''), ['draft', 'confirmed'], true)) { ?>
                <button type="submit" formaction="<?php echo rateb_url($routePrefix . '/' . (int)$item['id'] . '/submit'); ?>" class="btn btn-success"><?php echo __('send_to_supplier'); ?></button>
                <?php } ?>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
