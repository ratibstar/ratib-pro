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
$lookups = $lookups ?? [];
$label = rateb_label((string) ($field['label'] ?? $name));
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
    <?php } elseif ($type === 'fk' || ($lookup !== '' && in_array($type, ['number', 'text'], true))) { ?>
    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>"
            name="<?php echo Rateb\App\Core\View::escape($name); ?>"<?php echo $required ? ' required' : ''; ?>>
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
            name="<?php echo Rateb\App\Core\View::escape($name); ?>"<?php echo $required ? ' required' : ''; ?><?php echo $readonly ? ' disabled' : ''; ?>>
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
           <?php if (isset($field['min'])) { ?>min="<?php echo Rateb\App\Core\View::escape((string) $field['min']); ?>"<?php } ?>>
    <?php } ?>
</div>
