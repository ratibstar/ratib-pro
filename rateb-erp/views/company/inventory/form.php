<?php Rateb\App\Core\View::partial('crud-form', get_defined_vars()); ?>
<?php if (!empty($item['id'])) {
    $docBarcode = (new \Rateb\App\Services\DocumentBarcodeService())->labelData('inventory', (int) $item['id']);
    Rateb\App\Core\View::partial('document-barcode-label', ['docBarcode' => $docBarcode]);
    ?>
<p class="mt-2">
    <a href="<?php echo rateb_app_url('inventory/' . (int) $item['id'] . '/codes'); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-qrcode"></i> <?php echo __('barcode_qr'); ?>
    </a>
</p>
<?php } ?>
