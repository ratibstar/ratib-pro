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
$defaultVat15 = !empty($defaultVat15);
$workflow = $workflow ?? null;
$companyId = (int) (\Rateb\App\Core\TenantContext::companyId() ?? 0);
$suppliers = $suppliers ?? [];
$costCenters = $costCenters ?? [];
$warehouses = $warehouses ?? [];
$departments = $departments ?? [];
$inventoryItems = $inventoryItems ?? [];
$currencies = ['SAR', 'USD', 'EUR'];
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
                    $type = $field['type'] ?? 'text';
                    $value = $item[$name] ?? '';
                    if ($name === 'notes') {
                        Rateb\App\Core\View::partial('procurement-notes-field', [
                            'item' => $item,
                            'entityType' => $entityType,
                            'companyId' => $companyId,
                        ]);
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
                           value="<?php echo Rateb\App\Core\View::escape($value); ?>"
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
                           value="<?php echo Rateb\App\Core\View::escape($value ?: 0); ?>"
                           data-procurement-adjust>
                </div>
                        <?php
                        continue;
                    }
                    ?>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php echo Rateb\App\Core\View::escape(rateb_label((string) ($field['label'] ?? $name))); ?>
                    </label>
                    <?php                     if ($name === 'department') { ?>
                    <select class="form-select rateb-form-control" id="f_department" name="department">
                        <option value=""><?php echo __('select'); ?></option>
                        <?php foreach ($departments as $dept) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape($dept); ?>"<?php echo (string)$value === (string)$dept ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($dept); ?></option>
                        <?php } ?>
                    </select>
                    <?php } elseif ($name === 'supplier_id' && $suppliers !== []) { ?>
                    <select class="form-select rateb-form-control" id="f_supplier_id" name="supplier_id">
                        <option value="">—</option>
                        <?php foreach ($suppliers as $s) { ?>
                        <option value="<?php echo (int) $s['id']; ?>"<?php echo (int)$value === (int)$s['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($s['name'] ?? ''); ?></option>
                        <?php } ?>
                    </select>
                    <?php } elseif ($name === 'cost_center_id' && $costCenters !== []) { ?>
                    <select class="form-select rateb-form-control" id="f_cost_center_id" name="cost_center_id">
                        <option value="">—</option>
                        <?php foreach ($costCenters as $cc) {
                            $ccLabel = ($cc['code'] ?? '') . ' — ' . (rateb_locale() === 'ar' && !empty($cc['name_ar']) ? $cc['name_ar'] : ($cc['name'] ?? ''));
                            ?>
                        <option value="<?php echo (int) $cc['id']; ?>"<?php echo (int)$value === (int)$cc['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($ccLabel); ?></option>
                        <?php } ?>
                    </select>
                    <?php } elseif ($name === 'warehouse_id' && $warehouses !== []) { ?>
                    <select class="form-select rateb-form-control" id="f_warehouse_id" name="warehouse_id">
                        <option value="">—</option>
                        <?php foreach ($warehouses as $wh) { ?>
                        <option value="<?php echo (int) $wh['id']; ?>"<?php echo (int)$value === (int)$wh['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($wh['name'] ?? ''); ?></option>
                        <?php } ?>
                    </select>
                    <?php } elseif ($name === 'currency') { ?>
                    <select class="form-select rateb-form-control" id="f_currency" name="currency">
                        <?php foreach ($currencies as $cur) { ?>
                        <option value="<?php echo $cur; ?>"<?php echo (string)($value ?: 'SAR') === $cur ? ' selected' : ''; ?>><?php echo $cur; ?></option>
                        <?php } ?>
                    </select>
                    <?php } elseif ($type === 'select') { ?>
                    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php foreach (($field['options'] ?? []) as $opt) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape($opt); ?>"<?php echo (string)$value === (string)$opt ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__($opt)); ?></option>
                        <?php } ?>
                    </select>
                    <?php } elseif ($type === 'date') { ?>
                    <input class="form-control rateb-form-control" type="date" dir="ltr"
                           id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
                           name="<?php echo Rateb\App\Core\View::escape($name); ?>"
                           value="<?php echo Rateb\App\Core\View::escape($value); ?>">
                    <?php } else { ?>
                    <input class="form-control rateb-form-control" type="<?php echo Rateb\App\Core\View::escape($type); ?>"
                           id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
                           name="<?php echo Rateb\App\Core\View::escape($name); ?>"
                           value="<?php echo Rateb\App\Core\View::escape($value); ?>">
                    <?php } ?>
                </div>
                <?php } ?>
                <?php Rateb\App\Core\View::partial('line-items', [
                    'lineItems' => $lineItems ?? [],
                    'inventoryItems' => $inventoryItems,
                    'defaultVat15' => $defaultVat15,
                ]); ?>
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
