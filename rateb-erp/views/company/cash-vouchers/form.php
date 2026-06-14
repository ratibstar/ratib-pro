<?php
use Rateb\App\Services\FormLookupService;

/** @var array<string, mixed>|null $voucher */
$isEdit = !empty($voucher);
$action = $isEdit
    ? rateb_app_url('cash-vouchers/' . (int) $voucher['id'])
    : rateb_app_url('cash-vouchers');
$formFields = FormLookupService::cashVoucherFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
$item = $voucher;
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<form method="post" action="<?php echo $action; ?>" class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <div class="row g-3">
            <?php foreach ($formFields as $field) {
                $name = (string) $field['name'];
                $value = $item[$name] ?? ($field['default'] ?? '');
                if ($name === 'voucher_type' && $value === '') {
                    $value = 'receipt';
                }
                Rateb\App\Core\View::partial('form-field', [
                    'field' => $field,
                    'value' => $value,
                    'lookups' => $lookups,
                ]);
            } ?>
        </div>
    </div>
    <div class="rateb-card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
        <a href="<?php echo rateb_app_url('cash-vouchers'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
</form>
