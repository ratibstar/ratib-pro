<?php Rateb\App\Core\View::partial('crud-form', get_defined_vars()); ?>
<?php if (!empty($item['id'])) {
    $docBarcode = (new \Rateb\App\Services\DocumentBarcodeService())->labelData('inventory', (int) $item['id']);
    Rateb\App\Core\View::partial('document-barcode-label', ['docBarcode' => $docBarcode]);
} ?>
