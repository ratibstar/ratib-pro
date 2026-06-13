<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var array<int, array<string, mixed>> $allTags */
/** @var array<int, int> $selectedTags */
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <?php foreach ($fields as $field) {
                    $name = (string) ($field['name'] ?? '');
                    $type = (string) ($field['type'] ?? 'text');
                    $value = $item[$name] ?? '';
                    ?>
                <div class="col-md-6">
                    <label class="form-label" for="f_<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php echo Rateb\App\Core\View::escape(__($field['label'] ?? $name)); ?>
                    </label>
                    <?php if ($type === 'textarea' || $type === 'wysiwyg') { ?>
                    <textarea class="form-control<?php echo $type === 'wysiwyg' ? ' rateb-cms-wysiwyg' : ''; ?>" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" rows="6"><?php echo Rateb\App\Core\View::escape((string) $value); ?></textarea>
                    <?php } elseif ($type === 'select') { ?>
                    <select class="form-select" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>">
                        <?php foreach (($field['options'] ?? []) as $opt) { ?>
                        <option value="<?php echo Rateb\App\Core\View::escape($opt); ?>"<?php echo (string) $value === (string) $opt ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt); ?></option>
                        <?php } ?>
                    </select>
                    <?php } else { ?>
                    <input class="form-control" type="<?php echo Rateb\App\Core\View::escape($type); ?>" id="f_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>">
                    <?php } ?>
                </div>
                <?php } ?>
            </div>
            <?php if (!empty($allTags)) { ?>
            <div class="mt-4">
                <h3 class="h6"><?php echo __('cms_article_tags'); ?></h3>
                <div class="row g-2">
                    <?php foreach ($allTags as $tag) {
                        $tid = (int) ($tag['id'] ?? 0);
                        ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tag_ids[]" value="<?php echo $tid; ?>" id="tag_<?php echo $tid; ?>"
                                <?php echo in_array($tid, $selectedTags ?? [], true) ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="tag_<?php echo $tid; ?>">
                                <?php echo Rateb\App\Core\View::escape((string) ($tag['name_en'] ?? '')); ?>
                            </label>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
