<?php
use Rateb\App\Services\FormLookupService;

$formFields = FormLookupService::contractRenewalFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo __('contract_renewals'); ?></div>
    <div class="rateb-card-body">
        <?php if ($canManage ?? rateb_can_manage_entity('contract-renewals')) { ?>
        <?php Rateb\App\Core\View::partial('workflow-form', [
            'formFields' => $formFields,
            'formAction' => rateb_app_url('contract-renewals'),
            'csrf' => $csrf,
            'lookups' => $lookups,
        ]); ?>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $renewals ?? [],
            'columns' => [
                ['name' => 'contract_no', 'label' => 'contract_no'],
                ['name' => 'renewal_date', 'label' => 'renewal_date'],
                ['name' => 'new_end_date', 'label' => 'new_end_date'],
                ['name' => 'new_value', 'label' => 'new_value'],
                ['name' => 'status', 'label' => 'status'],
            ],
        ]); ?>
    </div>
</div>
<?php if (!empty($expiring)) { ?>
<div class="rateb-card">
    <div class="rateb-card-header text-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo __('contract_expiry_alerts'); ?></div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $expiring,
            'columns' => [
                ['name' => 'contract_no', 'label' => 'contract_no'],
                ['name' => 'title', 'label' => 'title'],
                ['name' => 'supplier_name', 'label' => 'suppliers'],
                ['name' => 'end_date', 'label' => 'end_date'],
            ],
        ]); ?>
    </div>
</div>
<?php } ?>
