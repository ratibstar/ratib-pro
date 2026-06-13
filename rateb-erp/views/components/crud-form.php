<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var string $routePrefix */
/** @var string $csrf */
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int)$item['id']) : rateb_url($routePrefix);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>"<?php echo !empty($multipart) ? ' enctype="multipart/form-data"' : ''; ?>>
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <?php foreach ($fields as $field) {
                    $name = $field['name'];
                    $type = $field['type'] ?? 'text';
                    $value = $item[$name] ?? '';
                    ?>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php echo Rateb\App\Core\View::escape(rateb_label((string) ($field['label'] ?? $name))); ?>
                    </label>
                    <?php if ($type === 'textarea') { ?>
                    <textarea class="form-control rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" rows="4"><?php echo Rateb\App\Core\View::escape($value); ?></textarea>
                    <?php } elseif ($type === 'select') { ?>
                    <select class="form-select rateb-form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php foreach (($field['options'] ?? []) as $opt) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape($opt); ?>"<?php echo (string)$value === (string)$opt ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt); ?></option>
                        <?php } ?>
                    </select>
                    <?php } else { ?>
                    <input class="form-control rateb-form-control" type="<?php echo Rateb\App\Core\View::escape($type); ?>" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" value="<?php echo Rateb\App\Core\View::escape($value); ?>">
                    <?php } ?>
                </div>
                <?php } ?>
                <?php if (($routePrefix ?? '') === 'admin/users' && !$isEdit) { ?>
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="f_password"><?php echo __('password'); ?></label>
                    <input class="form-control rateb-form-control" type="password" id="f_password" name="password">
                </div>
                <?php } ?>
                <?php if (!empty($attachment) && is_array($attachment)) {
                    Rateb\App\Core\View::partial('entity-attachment-field', $attachment);
                } ?>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
