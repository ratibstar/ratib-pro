<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<?php
use Rateb\App\Services\FormLookupService;

Rateb\App\Core\View::partial('workflow-index', [
    'title' => $title ?? __('asset_depreciation'),
    'entitySlug' => 'asset-depreciation',
    'routePrefix' => rateb_app_route('asset-depreciation'),
    'formFields' => FormLookupService::assetDepreciationFormFields(),
    'formAction' => rateb_app_url('asset-depreciation'),
    'items' => $items ?? [],
    'columns' => [
        ['name' => 'depreciation_no', 'label' => 'record_id', 'type' => 'id'],
        ['name' => 'asset_name', 'label' => 'assets'],
        ['name' => 'period_date', 'label' => 'period_date'],
        ['name' => 'amount', 'label' => 'depreciation_amount', 'type' => 'money'],
        ['name' => 'book_value', 'label' => 'book_value', 'type' => 'money'],
    ],
    'exportRoute' => $exportRoute ?? rateb_app_url('asset-depreciation/export'),
    'exportEnabled' => $exportEnabled ?? true,
    'csrf' => $csrf,
    'canManage' => $canManage ?? null,
]);
