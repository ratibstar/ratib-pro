<?php
$item = $item ?? [];
$id = (int) ($item['id'] ?? 0);
$docBarcode = (new \Rateb\App\Services\DocumentBarcodeService())->labelData('inventory', $id);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h5 class="mb-0"><?php echo Rateb\App\Core\View::escape($item['item_name'] ?? __('barcode_qr')); ?></h5>
    <?php if (rateb_can_manage_entity('inventory-codes')) { ?>
    <form method="post" action="<?php echo rateb_app_url('inventory/' . $id . '/codes/generate'); ?>" class="d-inline">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-barcode"></i> <?php echo __('generate_codes'); ?></button>
    </form>
    <?php } ?>
</div>
<?php if ($docBarcode) {
    Rateb\App\Core\View::partial('document-barcode-label', ['docBarcode' => $docBarcode]);
} else { ?>
<p class="text-muted"><?php echo __('no_barcode_yet'); ?></p>
<?php } ?>
<a href="<?php echo rateb_app_url('inventory'); ?>" class="btn btn-outline-secondary mt-3"><?php echo __('back_to_list'); ?></a>
