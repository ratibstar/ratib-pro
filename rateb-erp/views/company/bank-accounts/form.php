<?php
use Rateb\App\Services\FormLookupService;

$isEdit = !empty($item);
$action = $isEdit
    ? rateb_app_url('bank-accounts/' . (int) $item['id'])
    : rateb_app_url('bank-accounts');
$formFields = FormLookupService::bankAccountFormFields($isEdit);
$lookups = (new FormLookupService())->forFields($formFields);
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
        <?php if ($isEdit) {
            $chartLookups = (new FormLookupService())->get('chart_of_accounts');
            if ($chartLookups !== []) { ?>
        <div class="row g-3 mt-2">
            <div class="col-md-6">
                <label class="form-label rateb-form-label"><?php echo __('chart_of_accounts'); ?></label>
                <select class="form-select rateb-form-control" disabled>
                    <option value=""><?php echo __('select'); ?></option>
                    <?php foreach ($chartLookups as $opt) { ?>
                    <option value="<?php echo (int) $opt['value']; ?>"<?php echo (int) ($item['chart_account_id'] ?? 0) === (int) $opt['value'] ? ' selected' : ''; ?>>
                        <?php echo Rateb\App\Core\View::escape($opt['label']); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <?php }
        } ?>
    </div>
    <div class="rateb-card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
        <a href="<?php echo rateb_app_url('bank-accounts'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
</form>
