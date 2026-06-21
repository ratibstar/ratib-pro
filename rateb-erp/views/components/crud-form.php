<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var array<string, list<array{value: string|int, label: string}>> $lookups */
$isEdit = is_array($item) && (int) ($item['id'] ?? 0) > 0;
$action = $isEdit ? rateb_url($routePrefix . '/' . (int)$item['id']) : rateb_url($routePrefix);
$lookups = $lookups ?? (new \Rateb\App\Services\FormLookupService())->forFields($fields);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo $action; ?>"<?php echo !empty($multipart) ? ' enctype="multipart/form-data"' : ''; ?><?php
            if (str_contains((string) ($routePrefix ?? ''), 'inventory-batches')) {
                echo ' data-inventory-batch-form';
            }
            ?>>
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
                <?php if (!empty($categoryImage) && is_array($categoryImage)) {
                    Rateb\App\Core\View::partial('category-image-field', $categoryImage);
                } ?>
                <?php if (!empty($attachment) && is_array($attachment)) {
                    Rateb\App\Core\View::partial('entity-attachment-field', $attachment);
                } ?>
                <?php if (!empty($existingDocuments) && is_array($existingDocuments)) { ?>
                <div class="col-12">
                    <label class="form-label rateb-form-label"><?php echo __('entity_documents'); ?></label>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($existingDocuments as $doc) {
                            $docId = (int) ($doc['id'] ?? 0); ?>
                        <li class="mb-1 d-flex flex-wrap align-items-center gap-2">
                            <a href="<?php echo rateb_url('documents/view/' . $docId); ?>" target="_blank" rel="noopener">
                                <i class="fas fa-paperclip"></i>
                                <?php echo Rateb\App\Core\View::escape((string) ($doc['file_name'] ?? $doc['title'] ?? '')); ?>
                            </a>
                            <a href="<?php echo rateb_url('documents/download/' . $docId); ?>" class="btn btn-sm btn-outline-primary py-0">
                                <i class="fas fa-download"></i>
                            </a>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
                <?php } ?>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
