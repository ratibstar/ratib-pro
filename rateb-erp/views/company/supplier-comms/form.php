<?php
/** @var array<string, mixed>|null $item */
/** @var string $routePrefix */
/** @var array<int, array<string, mixed>> $fields */
/** @var string $csrf */
$isEdit = !empty($item);
$action = $isEdit
    ? rateb_url($routePrefix . '/' . (int) $item['id'])
    : rateb_url($routePrefix);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php
            $lookups = (new \Rateb\App\Services\FormLookupService())->forFields($fields);
            foreach ($fields as $field) {
                $name = (string) ($field['name'] ?? '');
                $label = rateb_label((string) ($field['label'] ?? $name));
                $type = (string) ($field['type'] ?? 'text');
                $val = $item[$name] ?? '';
                ?>
            <div class="mb-3">
                <label class="form-label" for="f_<?php echo Rateb\App\Core\View::escape($name); ?>"><?php echo Rateb\App\Core\View::escape($label); ?></label>
                <?php if ($type === 'textarea') { ?>
                <textarea class="form-control" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" rows="4" required><?php echo Rateb\App\Core\View::escape((string) $val); ?></textarea>
                <?php } elseif ($type === 'select') { ?>
                <select class="form-select" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" required>
                    <?php foreach (($field['options'] ?? []) as $opt) { ?>
                    <option value="<?php echo Rateb\App\Core\View::escape((string) $opt); ?>"<?php echo (string) $val === (string) $opt ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape(__($opt)); ?></option>
                    <?php } ?>
                </select>
                <?php } elseif ($type === 'fk') {
                    $opts = $lookups[$name] ?? [];
                    ?>
                <select class="form-select" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" required>
                    <option value="">—</option>
                    <?php foreach ($opts as $oid => $olabel) { ?>
                    <option value="<?php echo (int) $oid; ?>"<?php echo (int) $val === (int) $oid ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape((string) $olabel); ?></option>
                    <?php } ?>
                </select>
                <?php } else { ?>
                <input class="form-control" type="<?php echo Rateb\App\Core\View::escape($type); ?>" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" value="<?php echo Rateb\App\Core\View::escape((string) $val); ?>"<?php echo $name !== 'subject' ? ' required' : ''; ?>>
                <?php } ?>
            </div>
            <?php } ?>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
