<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var array<string, list<array{value: string|int, label: string}>> $lookups */
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int)$item['id']) : rateb_url($routePrefix);
$lookups = $lookups ?? (new \Rateb\App\Services\FormLookupService())->forFields($fields);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>"<?php echo !empty($multipart) ? ' enctype="multipart/form-data"' : ''; ?>>
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <?php foreach ($fields as $field) {
                    $name = $field['name'];
                    $value = $item[$name] ?? ($field['default'] ?? '');
                    Rateb\App\Core\View::partial('form-field', [
                        'field' => $field,
                        'value' => $value,
                        'lookups' => $lookups,
                    ]);
                } ?>
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
