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
        <?php Rateb\App\Core\View::partial('accounting-form', [
            'formFields' => $formFields,
            'item' => $item,
            'lookups' => $lookups,
        ]); ?>
    </div>
    <div class="rateb-card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
        <a href="<?php echo rateb_app_url('cash-vouchers'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
</form>
