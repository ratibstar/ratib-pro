<?php
/** @var string $inputName */
/** @var string $label */
/** @var string $imageUrl */
/** @var bool $hasImage */
$inputName = (string) ($inputName ?? 'category_image');
$label = (string) ($label ?? __('category_image'));
$imageUrl = (string) ($imageUrl ?? '');
$hasImage = !empty($hasImage);
?>
<div class="col-12">
    <label class="form-label rateb-form-label" for="f_<?php echo Rateb\App\Core\View::escape($inputName); ?>">
        <i class="fas fa-image"></i> <?php echo Rateb\App\Core\View::escape($label); ?>
    </label>
    <input class="form-control rateb-form-control" type="file" id="f_<?php echo Rateb\App\Core\View::escape($inputName); ?>"
        name="<?php echo Rateb\App\Core\View::escape($inputName); ?>"
        accept="image/jpeg,image/png,image/webp,image/gif">
    <small class="text-muted d-block mt-1"><?php echo __('category_image_hint'); ?></small>
    <?php if ($hasImage && $imageUrl !== '') { ?>
    <div class="mt-3 d-flex flex-wrap align-items-start gap-3">
        <img src="<?php echo Rateb\App\Core\View::escape($imageUrl); ?>" alt="" class="rounded border"
            style="max-width: 160px; max-height: 120px; object-fit: cover;">
        <div class="form-check mt-1">
            <input class="form-check-input" type="checkbox" name="remove_category_image" value="1" id="remove_category_image">
            <label class="form-check-label" for="remove_category_image"><?php echo __('remove_category_image'); ?></label>
        </div>
    </div>
    <?php } ?>
</div>
