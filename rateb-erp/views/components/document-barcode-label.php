<?php
/** @var array<string, mixed>|null $docBarcode */
if (empty($docBarcode) || empty($docBarcode['barcode'])) {
    return;
}
$uid = 'docbc-' . preg_replace('/[^a-z0-9]/i', '', (string) ($docBarcode['type'] ?? 'doc')) . '-' . (int) ($docBarcode['recordId'] ?? 0);
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/document-barcodes.css'); ?>">
<div class="rateb-doc-barcode mt-4" data-rateb-barcodes
    data-barcode="<?php echo Rateb\App\Core\View::escape((string) $docBarcode['barcode']); ?>"
    data-qr="<?php echo Rateb\App\Core\View::escape((string) ($docBarcode['qr_code'] ?? '')); ?>"
    data-label-title="<?php echo Rateb\App\Core\View::escape((string) ($docBarcode['title'] ?? '')); ?>">
    <div class="rateb-card">
        <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fas fa-barcode"></i> <?php echo __('document_barcode'); ?></span>
            <div class="d-flex gap-2 rateb-barcode-actions">
                <button type="button" class="btn btn-sm btn-outline-primary" data-barcode-print>
                    <i class="fas fa-print"></i> <?php echo __('print_label'); ?>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-barcode-download>
                    <i class="fas fa-download"></i> <?php echo __('download_png'); ?>
                </button>
            </div>
        </div>
        <div class="rateb-card-body">
            <div class="rateb-doc-barcode-print-area" id="<?php echo Rateb\App\Core\View::escape($uid); ?>">
                <div class="rateb-doc-barcode-brand text-center mb-2"><?php echo __('rateb_erp'); ?></div>
                <h5 class="text-center mb-1 rateb-doc-barcode-title"><?php echo Rateb\App\Core\View::escape((string) ($docBarcode['title'] ?? '')); ?></h5>
                <?php if (!empty($docBarcode['subtitle'])) { ?>
                <p class="text-center text-muted small mb-3 rateb-doc-barcode-subtitle"><?php echo Rateb\App\Core\View::escape((string) $docBarcode['subtitle']); ?></p>
                <?php } ?>
                <div class="row g-3 align-items-center justify-content-center">
                    <div class="col-md-6 text-center">
                        <div class="small text-muted mb-1"><?php echo __('barcode'); ?></div>
                        <svg data-barcode-svg></svg>
                    </div>
                    <div class="col-md-6 text-center">
                        <div class="small text-muted mb-1"><?php echo __('qr_code'); ?></div>
                        <?php if (!empty($docBarcode['qr_image_url'])) { ?>
                        <img data-qr-img src="<?php echo Rateb\App\Core\View::escape((string) $docBarcode['qr_image_url']); ?>"
                            alt="<?php echo __('qr_code'); ?>" width="180" height="180" class="rateb-doc-qr-img">
                        <?php } else { ?>
                        <canvas data-qr-canvas width="180" height="180"></canvas>
                        <?php } ?>
                    </div>
                </div>
                <p class="font-monospace text-center mt-3 mb-0 rateb-doc-barcode-code"><?php echo Rateb\App\Core\View::escape((string) $docBarcode['barcode']); ?></p>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" crossorigin="anonymous"></script>
<script src="<?php echo rateb_asset('js/barcodes.js'); ?>?v=2"></script>
