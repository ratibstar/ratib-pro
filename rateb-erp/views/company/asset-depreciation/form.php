<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<?php
/** @var array<string, mixed> $item */
/** @var array<int, array<string, mixed>> $formFields */
$id = (int) ($item['id'] ?? 0);
$bookJson = json_encode($assetBookValues ?? [], JSON_UNESCAPED_UNICODE);
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <a href="<?php echo rateb_app_url('asset-depreciation'); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_app_url('asset-depreciation/' . $id); ?>"
              enctype="multipart/form-data"
              class="row g-3" data-asset-depreciation-form="1"
              data-asset-book-values="<?php echo Rateb\App\Core\View::escape($bookJson ?: '{}'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php foreach ($formFields as $field) {
                $name = (string) $field['name'];
                $value = $item[$name] ?? ($field['default'] ?? '');
                Rateb\App\Core\View::partial('form-field', [
                    'field' => $field,
                    'value' => $value,
                    'lookups' => $lookups ?? [],
                ]);
            } ?>
            <div class="col-md-4">
                <label class="form-label rateb-form-label"><?php echo __('book_value_before'); ?></label>
                <input class="form-control rateb-form-control rateb-ltr-num" type="text" readonly data-dep-before
                       value="<?php echo Rateb\App\Core\View::escape(number_format((float) ($item['book_value_before'] ?? 0), 2, '.', '')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label rateb-form-label"><?php echo __('book_value_after'); ?></label>
                <input class="form-control rateb-form-control rateb-ltr-num" type="text" readonly data-dep-after
                       value="<?php echo Rateb\App\Core\View::escape(number_format((float) ($item['book_value'] ?? 0), 2, '.', '')); ?>">
            </div>
            <?php Rateb\App\Core\View::partial('entity-attachment-field', [
                'entityType' => 'asset_depreciation',
                'entityId' => $id,
                'companyId' => (int) ($companyId ?? 0),
                'label' => __('attach_document'),
            ]); ?>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
            </div>
        </form>
    </div>
</div>
<?php if (!empty($assetJs)) { ?>
<script src="<?php echo Rateb\App\Core\View::escape($assetJs); ?>"></script>
<?php } ?>
