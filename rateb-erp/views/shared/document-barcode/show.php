<?php
/** @var array<string, mixed> $docBarcode */
/** @var string $backUrl */
/** @var string $title */
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0"><?php echo Rateb\App\Core\View::escape($title ?? __('document_barcode')); ?></h5>
    <a href="<?php echo Rateb\App\Core\View::escape($backUrl ?? '#'); ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-right"></i> <?php echo __('back_to_list'); ?>
    </a>
</div>
<?php
if (!empty($docBarcode)) {
    Rateb\App\Core\View::partial('document-barcode-label', ['docBarcode' => $docBarcode]);
} else { ?>
<p class="text-muted"><?php echo __('no_barcode_yet'); ?></p>
<?php } ?>
