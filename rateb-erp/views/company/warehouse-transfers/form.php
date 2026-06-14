<?php
/** @var array<int, array<string, mixed>> $formFields */
/** @var array<string, list<array{value: string|int, label: string}>> $lookups */
/** @var string $csrf */
$formFields = $formFields ?? [];
$lookups = $lookups ?? (new \Rateb\App\Services\FormLookupService())->forFields($formFields);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? __('warehouse_transfers')); ?></div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('workflow-form', [
            'formFields' => $formFields,
            'formAction' => rateb_app_url('warehouse-transfers'),
            'csrf' => $csrf,
            'lookups' => $lookups,
        ]); ?>
        <a href="<?php echo rateb_app_url('warehouse-transfers'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
</div>
