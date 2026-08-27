<?php
/** @var array<string, mixed> $field */
/** @var mixed $value */
/** @var array<string, list<array{value: string|int, label: string}>> $lookups */
$name = (string) $field['name'];
$type = (string) ($field['type'] ?? 'text');
$lookup = (string) ($field['lookup'] ?? '');
$col = (string) ($field['col'] ?? 'col-md-6');
$required = !empty($field['required']);
$readonly = !empty($field['readonly']);
$displayOnly = !empty($field['display_only']);
$allowManual = !empty($field['allow_manual']) || $type === 'hybrid';
$lookups = $lookups ?? [];
$label = rateb_label((string) ($field['label'] ?? $name));
$isHybrid = $allowManual && $lookup !== '';
$inputClass = 'form-control rateb-form-control';
$fieldAttrs = '';
foreach (($field['attrs'] ?? []) as $attrKey => $attrVal) {
    if ((string) $attrKey === 'class') {
        $inputClass = (string) $attrVal;
        continue;
    }
    $attrDisplay = ((string) $attrKey === 'title' && preg_match('/^[a-z0-9_]+$/', (string) $attrVal))
        ? __((string) $attrVal)
        : (string) $attrVal;
    $fieldAttrs .= ' ' . Rateb\App\Core\View::escape((string) $attrKey) . '="' . Rateb\App\Core\View::escape($attrDisplay) . '"';
}
?>
<div class="<?php echo Rateb\App\Core\View::escape($col); ?>">
    <label class="form-label rateb-form-label" for="f_<?php echo Rateb\App\Core\View::escape($name); ?>">
        <?php echo Rateb\App\Core\View::escape($label); ?>
        <?php if ($required) { ?><span class="text-danger">*</span><?php } ?>
    </label>
    <?php if ($type === 'textarea') { ?>
    <textarea class="form-control rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
              name="<?php echo Rateb\App\Core\View::escape($name); ?>" rows="<?php echo (int) ($field['rows'] ?? 4); ?>"
              <?php echo $required ? ' required' : ''; ?><?php echo $readonly ? ' readonly' : ''; ?>><?php echo Rateb\App\Core\View::escape((string) $value); ?></textarea>
    <?php } elseif ($type === 'wysiwyg') { ?>
    <textarea class="form-control rateb-form-control rateb-cms-wysiwyg" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
              name="<?php echo Rateb\App\Core\View::escape($name); ?>" rows="8"><?php echo Rateb\App\Core\View::escape((string) $value); ?></textarea>
    <?php } elseif ($type === 'score_select') {
        $max = (int) ($field['max'] ?? 10);
        $min = (int) ($field['min'] ?? 0);
        ?>
    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
            name="<?php echo Rateb\App\Core\View::escape($name); ?>"<?php echo $fieldAttrs; ?><?php echo $required ? ' required' : ''; ?>>
        <?php for ($s = $min; $s <= $max; $s++) { ?>
        <option value="<?php echo $s; ?>"<?php echo (string) $value === (string) $s ? ' selected' : ''; ?>><?php echo $s; ?>/<?php echo $max; ?></option>
        <?php } ?>
    </select>
    <?php     } elseif ($isHybrid) {
        $options = $lookups[$lookup] ?? [];
        if ($options === [] && !empty($field['options']) && is_array($field['options'])) {
            foreach ($field['options'] as $opt) {
                if (is_array($opt)) {
                    $options[] = [
                        'value' => (string) ($opt['value'] ?? ''),
                        'label' => (string) ($opt['label'] ?? $opt['value'] ?? ''),
                    ];
                } else {
                    $key = (string) $opt;
                    $options[] = ['value' => $key, 'label' => __($key)];
                }
            }
        }
        $selectedValue = (string) $value;
        $manualValue = '';
        $pickValue = '';
        $found = false;
        foreach ($options as $opt) {
            if ((string) ($opt['value'] ?? '') === $selectedValue) {
                $found = true;
                $pickValue = $selectedValue;
                break;
            }
        }
        if (!$found && $selectedValue !== '') {
            // Match translated labels saved previously.
            foreach ($options as $opt) {
                if ((string) ($opt['label'] ?? '') === $selectedValue) {
                    $found = true;
                    $pickValue = (string) ($opt['value'] ?? '');
                    break;
                }
            }
        }
        if (!$found && $selectedValue !== '') {
            $pickValue = '__manual__';
            $manualValue = $selectedValue;
        }
        $detailsOnPick = !empty($field['details_on_pick']);
        if ($detailsOnPick && $found && $pickValue !== '' && $pickValue !== '__manual__' && $manualValue === '') {
            foreach ($options as $opt) {
                if ((string) ($opt['value'] ?? '') === $pickValue) {
                    $manualValue = (string) ($opt['label'] ?? '');
                    break;
                }
            }
        }
        $showManual = ($pickValue === '__manual__') || ($detailsOnPick && $pickValue !== '');
        ?>
    <div class="rateb-hybrid-field"<?php echo $detailsOnPick ? ' data-details-on-pick="1"' : ''; ?>>
        <select class="form-select rateb-form-control rateb-hybrid-select" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>_pick"
                name="<?php echo Rateb\App\Core\View::escape($name); ?>_pick"
                <?php echo $fieldAttrs; ?><?php echo $required ? ' required' : ''; ?>>
            <option value=""><?php echo __('select'); ?></option>
            <?php foreach ($options as $opt) { ?>
            <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"<?php echo $pickValue === (string) $opt['value'] ? ' selected' : ''; ?>
                    data-label="<?php echo Rateb\App\Core\View::escape((string) ($opt['label'] ?? '')); ?>">
                <?php echo Rateb\App\Core\View::escape($opt['label']); ?>
            </option>
            <?php } ?>
            <option value="__manual__"<?php echo $pickValue === '__manual__' ? ' selected' : ''; ?>><?php echo __('manual_entry'); ?></option>
        </select>
        <?php if (($field['manual_type'] ?? 'text') === 'textarea') { ?>
        <textarea class="form-control rateb-form-control rateb-hybrid-manual mt-1"
                  id="f_<?php echo Rateb\App\Core\View::escape($name); ?>_manual"
                  name="<?php echo Rateb\App\Core\View::escape($name); ?>_manual"
                  rows="<?php echo (int) ($field['rows'] ?? 4); ?>"
                  placeholder="<?php echo __('type_manually'); ?>"
                  <?php echo $showManual ? '' : 'disabled'; ?>
                  style="<?php echo $showManual ? '' : 'display:none'; ?>"><?php echo Rateb\App\Core\View::escape($manualValue); ?></textarea>
        <?php } else { ?>
        <input type="text" class="form-control rateb-form-control rateb-hybrid-manual mt-1"
               id="f_<?php echo Rateb\App\Core\View::escape($name); ?>_manual"
               name="<?php echo Rateb\App\Core\View::escape($name); ?>_manual"
               placeholder="<?php echo __('type_manually'); ?>"
               value="<?php echo Rateb\App\Core\View::escape($manualValue); ?>"
               <?php echo $showManual ? '' : 'disabled'; ?>
               style="<?php echo $showManual ? '' : 'display:none'; ?>">
        <?php } ?>
        <input type="hidden" class="rateb-hybrid-value" name="<?php echo Rateb\App\Core\View::escape($name); ?>"
               value="<?php echo Rateb\App\Core\View::escape($detailsOnPick && $showManual ? $manualValue : $selectedValue); ?>">
    </div>
    <?php } elseif ($type === 'datalist' && $lookup !== '') {
        $options = $lookups[$lookup] ?? [];
        $listId = 'dl_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
        ?>
    <input class="form-control rateb-form-control" type="text" list="<?php echo Rateb\App\Core\View::escape($listId); ?>"
           id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
           name="<?php echo Rateb\App\Core\View::escape($name); ?>"
           value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>"
           <?php echo $required ? ' required' : ''; ?><?php echo $readonly ? ' readonly' : ''; ?>>
    <datalist id="<?php echo Rateb\App\Core\View::escape($listId); ?>">
        <?php foreach ($options as $opt) { ?>
        <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"></option>
        <?php } ?>
    </datalist>
    <?php } elseif ($type === 'fk' || ($lookup !== '' && in_array($type, ['number', 'text'], true))) {
        $selectedKey = ($value !== '' && $value !== null) ? (string) (int) $value : '';
        ?>
    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
            name="<?php echo Rateb\App\Core\View::escape($name); ?>"<?php echo $fieldAttrs; ?><?php echo $required ? ' required' : ''; ?>>
        <option value=""><?php echo __('select'); ?></option>
        <?php foreach (($lookups[$lookup] ?? []) as $opt) {
            $optKey = (string) (int) ($opt['value'] ?? 0);
            if ($optKey === '0') {
                continue;
            }
            ?>
        <option value="<?php echo Rateb\App\Core\View::escape($optKey); ?>"<?php echo $selectedKey === $optKey ? ' selected' : ''; ?><?php
            $itemCode = trim((string) ($opt['item_code'] ?? ''));
            if ($itemCode !== '') {
                echo ' data-item-code="' . Rateb\App\Core\View::escape($itemCode) . '"';
            }
            if (isset($opt['contract_value'])) {
                echo ' data-contract-value="' . Rateb\App\Core\View::escape((string) $opt['contract_value']) . '"';
            }
            $contractEnd = trim((string) ($opt['end_date'] ?? ''));
            if ($contractEnd !== '') {
                echo ' data-end-date="' . Rateb\App\Core\View::escape($contractEnd) . '"';
            }
            ?>>
            <?php echo Rateb\App\Core\View::escape($opt['label']); ?>
        </option>
        <?php } ?>
    </select>
    <?php } elseif ($type === 'select') {
        $options = $field['options'] ?? [];
        if ($options === [] && $lookup !== '' && isset($lookups[$lookup])) {
            $options = $lookups[$lookup];
        }
        $translate = !isset($field['translate_options']) || $field['translate_options'] !== false;
        ?>
    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
            name="<?php echo Rateb\App\Core\View::escape($name); ?>"<?php echo $fieldAttrs; ?><?php echo $required ? ' required' : ''; ?><?php echo $readonly ? ' disabled' : ''; ?>>
        <?php if (!empty($field['placeholder'])) { ?>
        <option value=""><?php echo Rateb\App\Core\View::escape((string) $field['placeholder']); ?></option>
        <?php } ?>
        <?php foreach ($options as $opt) {
            $optLabel = is_array($opt) ? ($opt['label'] ?? $opt['value'] ?? '') : ($translate ? __((string) $opt) : (string) $opt);
            $optVal = is_array($opt) ? ($opt['value'] ?? '') : $opt;
            ?>
        <option value="<?php echo Rateb\App\Core\View::escape((string) $optVal); ?>"<?php echo (string) $value === (string) $optVal ? ' selected' : ''; ?>>
            <?php echo Rateb\App\Core\View::escape((string) $optLabel); ?>
        </option>
        <?php } ?>
    </select>
    <?php if ($readonly) { ?>
    <input type="hidden" name="<?php echo Rateb\App\Core\View::escape($name); ?>" value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>">
    <?php } ?>
    <?php } else {
        $dateTypes = ['date', 'datetime-local', 'time', 'month', 'week'];
        $isDateInput = in_array($type, $dateTypes, true);
        $isNumberField = $type === 'number';
        $renderType = $isNumberField ? 'text' : $type;
        if ($isNumberField && $value !== '' && $value !== null && function_exists('rateb_western_digits')) {
            $western = rateb_western_digits((string) $value);
            $value = is_numeric($western) ? (string) (0 + $western) : $western;
        }
        $dateClass = $isDateInput ? ' rateb-ltr-date rateb-date-input' : '';
        $numClass = $isNumberField ? ' rateb-ltr-num' : '';
        $dateHintKey = match ($type) {
            'datetime-local' => 'datetime_format_hint',
            'time' => 'time_format_hint',
            'month' => 'month_format_hint',
            'week' => 'week_format_hint',
            default => 'date_format_hint',
        };
        if ($isDateInput) { ?>
    <div class="rateb-date-wrap" data-date-type="<?php echo Rateb\App\Core\View::escape($type); ?>"
         data-format-hint="<?php echo Rateb\App\Core\View::escape(__($dateHintKey)); ?>"
         data-empty="<?php echo trim((string) $value) === '' ? '1' : '0'; ?>">
        <button type="button" class="rateb-date-wrap-icon" tabindex="-1" aria-hidden="true">
            <i class="fas fa-calendar-alt"></i>
        </button>
        <input class="<?php echo Rateb\App\Core\View::escape($inputClass . $dateClass); ?>" type="<?php echo Rateb\App\Core\View::escape($renderType); ?>"
               id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
               <?php if (!$displayOnly) { ?>name="<?php echo Rateb\App\Core\View::escape($name); ?>" <?php } ?>
               value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>"
               <?php echo $required && !$displayOnly ? ' required' : ''; ?><?php echo $readonly || $displayOnly ? ' readonly' : ''; ?>
               <?php echo $fieldAttrs; ?>
               dir="ltr" lang="en" autocomplete="off"
               <?php if (!empty($field['step'])) { ?>step="<?php echo Rateb\App\Core\View::escape((string) $field['step']); ?>"<?php } ?>
               <?php if (isset($field['min'])) { ?>min="<?php echo Rateb\App\Core\View::escape((string) $field['min']); ?>"<?php } ?>
               <?php if (isset($field['max'])) { ?>max="<?php echo Rateb\App\Core\View::escape((string) $field['max']); ?>"<?php } ?>>
    </div>
        <?php } else { ?>
    <input class="<?php echo Rateb\App\Core\View::escape($inputClass . $dateClass . $numClass); ?>" type="<?php echo Rateb\App\Core\View::escape($renderType); ?>"
           id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
           <?php if (!$displayOnly) { ?>name="<?php echo Rateb\App\Core\View::escape($name); ?>" <?php } ?>
           value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>"
           <?php echo $required && !$displayOnly ? ' required' : ''; ?><?php echo $readonly || $displayOnly ? ' readonly' : ''; ?>
           <?php echo $fieldAttrs; ?>
           <?php if ($isNumberField) { ?>dir="ltr" lang="en" inputmode="decimal" autocomplete="off"<?php } else { ?>
           <?php if (!empty($field['step'])) { ?>step="<?php echo Rateb\App\Core\View::escape((string) $field['step']); ?>"<?php } ?>
           <?php if (isset($field['min'])) { ?>min="<?php echo Rateb\App\Core\View::escape((string) $field['min']); ?>"<?php } ?>
           <?php if (isset($field['max'])) { ?>max="<?php echo Rateb\App\Core\View::escape((string) $field['max']); ?>"<?php } ?>
           <?php } ?>>
        <?php } ?>
    <?php if (!empty($field['hint'])) { ?>
    <small class="text-muted d-block mt-1"><?php echo __((string) $field['hint']); ?></small>
    <?php } ?>
    <?php } ?></div>
