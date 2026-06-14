<?php
use Rateb\App\Services\FormLookupService;

$formFields = FormLookupService::assetDepreciationFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('asset_depreciation')); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('asset-depreciation')) { ?>
        <?php Rateb\App\Core\View::partial('workflow-form', [
            'formFields' => $formFields,
            'formAction' => rateb_app_url('asset-depreciation'),
            'csrf' => $csrf,
            'lookups' => $lookups,
        ]); ?>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $items ?? [],
            'columns' => [
                ['name' => 'asset_name', 'label' => 'assets'],
                ['name' => 'period_date', 'label' => 'period_date'],
                ['name' => 'amount', 'label' => 'depreciation_amount'],
                ['name' => 'book_value', 'label' => 'book_value'],
            ],
        ]); ?>
    </div>
</div>
