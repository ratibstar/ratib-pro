<?php
/** Inline workflow create form — uses FormLookupService for rich dropdown options. */
/** @var array<int, array<string, mixed>> $formFields */
/** @var string $formAction */
/** @var string $csrf */
/** @var array<string, list<array{value: string|int, label: string}>> $lookups */
$formFields = $formFields ?? [];
$formAction = $formAction ?? '';
$csrf = $csrf ?? '';
$lookups = $lookups ?? (new \Rateb\App\Services\FormLookupService())->forFields($formFields);
$isContractRenewalForm = false;
foreach ($formFields as $wf) {
    if ((string) ($wf['name'] ?? '') === 'contract_id' && (string) ($wf['lookup'] ?? '') === 'contracts') {
        $isContractRenewalForm = true;
        break;
    }
}
?>
<form method="post" action="<?php echo Rateb\App\Core\View::escape($formAction); ?>" class="row g-3 mb-4"<?php echo $isContractRenewalForm ? ' data-contract-renewal-form="1"' : ''; ?>>
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <?php foreach ($formFields as $field) {
        $value = (string) ($field['default'] ?? '');
        Rateb\App\Core\View::partial('form-field', [
            'field' => $field,
            'value' => $value,
            'lookups' => $lookups,
        ]);
    } ?>
    <div class="col-12">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
    </div>
</form>
