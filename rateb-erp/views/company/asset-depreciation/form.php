<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<?php
/** @var array<string, mixed> $item */
/** @var array<int, array<string, mixed>> $formFields */
$id = (int) ($item['id'] ?? 0);
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <a href="<?php echo rateb_app_url('asset-depreciation'); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_app_url('asset-depreciation/' . $id); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <?php foreach ($formFields as $field) {
                    $name = (string) $field['name'];
                    $value = $item[$name] ?? ($field['default'] ?? '');
                    Rateb\App\Core\View::partial('form-field', [
                        'field' => $field,
                        'value' => $value,
                        'lookups' => $lookups ?? [],
                    ]);
                } ?>
            </div>
            <p class="text-muted small mt-3 mb-0"><?php echo __('depreciation_auto_values_hint'); ?></p>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
            </div>
        </form>
    </div>
</div>
