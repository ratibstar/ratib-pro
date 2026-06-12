<?php
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int)$item['id']) : rateb_url($routePrefix);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>" enctype="multipart/form-data">
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
                    <?php if ($name === 'supplier_id' && !empty($suppliers)) { ?>
                    <select class="form-select rateb-form-control" id="f_supplier_id" name="supplier_id">
                        <option value="">—</option>
                        <?php foreach ($suppliers as $s) { ?>
                        <option value="<?php echo (int) $s['id']; ?>"<?php echo (int)$value === (int)$s['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($s['name'] ?? ''); ?></option>
                        <?php } ?>
                    </select>
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
                <div class="col-md-6">
                    <label class="form-label rateb-form-label" for="contract_file"><?php echo __('contract_attachment'); ?></label>
                    <input class="form-control rateb-form-control" type="file" id="contract_file" name="contract_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                    <?php if (!empty($item['document_path'])) { ?>
                    <small class="text-muted d-block mt-1"><?php echo __('current_file'); ?>: <?php echo Rateb\App\Core\View::escape(basename((string) $item['document_path'])); ?></small>
                    <?php } ?>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
