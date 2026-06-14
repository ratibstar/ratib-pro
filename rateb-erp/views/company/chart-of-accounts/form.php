<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var array<int|string, string> $parentOptions */
/** @var string $routePrefix */
/** @var string $csrf */
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
$isEdit = !empty($item);
$action = $isEdit
    ? rateb_app_url('chart-of-accounts/' . (int) $item['id'])
    : rateb_app_url('chart-of-accounts');
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <?php foreach ($fields as $field) {
                    $name = $field['name'];
                    $type = $field['type'] ?? 'text';
                    $value = $item[$name] ?? '';
                    ?>
                <div class="col-md-6">
                    <label class="form-label" for="f_<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php echo Rateb\App\Core\View::escape(rateb_label((string) ($field['label'] ?? $name))); ?>
                    </label>
                    <?php if ($type === 'parent_select') { ?>
                    <select class="form-select" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php foreach ($parentOptions as $optId => $optLabel) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape((string) $optId); ?>"<?php echo (string) $value === (string) $optId ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($optLabel); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <?php } elseif ($type === 'select') { ?>
                    <select class="form-select" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php foreach (($field['options'] ?? []) as $opt) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape($opt); ?>"<?php echo (string) $value === (string) $opt ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__($opt)); ?></option>
                        <?php } ?>
                    </select>
                    <?php } else { ?>
                    <input class="form-control" type="text" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>">
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_app_url('chart-of-accounts'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
