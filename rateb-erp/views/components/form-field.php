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
$allowManual = !empty($field['allow_manual']) || $type === 'hybrid';
$lookups = $lookups ?? [];
$label = rateb_label((string) ($field['label'] ?? $name));
$isHybrid = $allowManual && $lookup !== '';
$fieldAttrs = '';
foreach (($field['attrs'] ?? []) as $attrKey => $attrVal) {
    $fieldAttrs .= ' ' . Rateb\App\Core\View::escape((string) $attrKey) . '="' . Rateb\App\Core\View::escape((string) $attrVal) . '"';
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
    <?php } elseif ($isHybrid) {
        $options = $lookups[$lookup] ?? [];
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
            $pickValue = '__manual__';
            $manualValue = $selectedValue;
        }
        ?>
    <div class="rateb-hybrid-field">
        <select class="form-select rateb-form-control rateb-hybrid-select" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>_pick"
                <?php echo $fieldAttrs; ?><?php echo $required ? ' required' : ''; ?>>
            <option value=""><?php echo __('select'); ?></option>
            <?php foreach ($options as $opt) { ?>
            <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"<?php echo $pickValue === (string) $opt['value'] ? ' selected' : ''; ?>>
                <?php echo Rateb\App\Core\View::escape($opt['label']); ?>
            </option>
            <?php } ?>
            <option value="__manual__"<?php echo $pickValue === '__manual__' ? ' selected' : ''; ?>><?php echo __('manual_entry'); ?></option>
        </select>
        <input type="text" class="form-control rateb-form-control rateb-hybrid-manual mt-1"
               id="f_<?php echo Rateb\App\Core\View::escape($name); ?>_manual"
               placeholder="<?php echo __('type_manually'); ?>"
               value="<?php echo Rateb\App\Core\View::escape($manualValue); ?>"
               style="<?php echo $pickValue === '__manual__' ? '' : 'display:none'; ?>">
        <input type="hidden" class="rateb-hybrid-value" name="<?php echo Rateb\App\Core\View::escape($name); ?>"
               value="<?php echo Rateb\App\Core\View::escape($selectedValue); ?>">
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
    <?php } elseif ($type === 'fk' || ($lookup !== '' && in_array($type, ['number', 'text'], true))) { ?>
    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
            name="<?php echo Rateb\App\Core\View::escape($name); ?>"<?php echo $fieldAttrs; ?><?php echo $required ? ' required' : ''; ?>>
        <option value=""><?php echo __('select'); ?></option>
        <?php foreach (($lookups[$lookup] ?? []) as $opt) { ?>
        <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"<?php echo (string) $value === (string) $opt['value'] ? ' selected' : ''; ?>>
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
    <?php } else { ?>
    <input class="form-control rateb-form-control" type="<?php echo Rateb\App\Core\View::escape($type); ?>"
           id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
           name="<?php echo Rateb\App\Core\View::escape($name); ?>"
           value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>"
           <?php echo $required ? ' required' : ''; ?><?php echo $readonly ? ' readonly' : ''; ?>
           <?php if (!empty($field['step'])) { ?>step="<?php echo Rateb\App\Core\View::escape((string) $field['step']); ?>"<?php } ?>
           <?php if (isset($field['min'])) { ?>min="<?php echo Rateb\App\Core\View::escape((string) $field['min']); ?>"<?php } ?>
           <?php if (isset($field['max'])) { ?>max="<?php echo Rateb\App\Core\View::escape((string) $field['max']); ?>"<?php } ?>>
    <?php } ?>
</div>
