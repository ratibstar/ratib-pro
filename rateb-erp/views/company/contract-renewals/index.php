<?php
use Rateb\App\Services\FormLookupService;

Rateb\App\Core\View::partial('workflow-index', [
    'title' => __('contract_renewals'),
    'entitySlug' => 'contract-renewals',
    'routePrefix' => rateb_app_route('contract-renewals'),
    'formFields' => FormLookupService::contractRenewalFormFields(),
    'formAction' => rateb_app_url('contract-renewals'),
    'items' => $renewals ?? [],
    'columns' => [
        ['name' => 'renewal_no', 'label' => 'record_id', 'type' => 'id'],
        ['name' => 'contract_no', 'label' => 'contract_no'],
        ['name' => 'renewal_date', 'label' => 'renewal_date'],
        ['name' => 'new_end_date', 'label' => 'new_end_date'],
        ['name' => 'new_value', 'label' => 'new_value', 'type' => 'money'],
        ['name' => 'status', 'label' => 'status'],
        ['name' => 'notes', 'label' => 'notes', 'type' => 'notes'],
    ],
    'exportRoute' => $exportRoute ?? rateb_app_url('contract-renewals/export'),
    'exportEnabled' => $exportEnabled ?? true,
    'csrf' => $csrf,
    'canManage' => $canManage ?? null,
]);
?>
<?php if (!empty($expiring)) { ?>
<div class="rateb-card">
    <div class="rateb-card-header text-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo __('contract_expiry_alerts'); ?></div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $expiring,
            'columns' => [
                ['name' => 'contract_no', 'label' => 'contract_no', 'type' => 'id'],
                ['name' => 'title', 'label' => 'title'],
                ['name' => 'supplier_name', 'label' => 'suppliers'],
                ['name' => 'end_date', 'label' => 'end_date'],
            ],
        ]); ?>
    </div>
</div>
<?php } ?>
