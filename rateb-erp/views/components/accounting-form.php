<?php
/** @var array<int, array<string, mixed>> $formFields */
/** @var array<string, mixed>|null $item */
/** @var array<string, list<array{value: string|int, label: string}>> $lookups */
$formFields = $formFields ?? [];
$item = $item ?? null;
$lookups = $lookups ?? (new \Rateb\App\Services\FormLookupService())->forFields($formFields);
?>
<div class="row g-3">
    <?php foreach ($formFields as $field) {
        $name = (string) ($field['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $value = $item[$name] ?? ($field['default'] ?? '');
        if (($field['type'] ?? '') === 'checkbox') {
            $checked = !empty($item[$name]) || (!empty($field['default']) && $item === null);
            ?>
    <div class="<?php echo Rateb\App\Core\View::escape((string) ($field['col'] ?? 'col-12')); ?>">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="<?php echo Rateb\App\Core\View::escape($name); ?>"
                   value="1" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
                   <?php echo $checked ? ' checked' : ''; ?>>
            <label class="form-check-label" for="f_<?php echo Rateb\App\Core\View::escape($name); ?>">
                <?php echo Rateb\App\Core\View::escape(rateb_label((string) ($field['label'] ?? $name))); ?>
            </label>
        </div>
    </div>
            <?php
            continue;
        }
        Rateb\App\Core\View::partial('form-field', [
            'field' => $field,
            'value' => $value,
            'lookups' => $lookups,
        ]);
    } ?>
</div>
