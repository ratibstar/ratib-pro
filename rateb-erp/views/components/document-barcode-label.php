<?php
/** @var array<string, mixed>|null $docBarcode */
if (empty($docBarcode) || empty($docBarcode['barcode'])) {
    return;
}
$uid = 'docbc-' . preg_replace('/[^a-z0-9]/i', '', (string) ($docBarcode['type'] ?? 'doc')) . '-' . (int) ($docBarcode['recordId'] ?? 0);
$safeFilename = preg_replace('/[^\w\.\-]+/u', '_', (string) ($docBarcode['title'] ?? 'label')) ?: 'label';
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/document-barcodes.css'); ?>?v=3">
<div class="rateb-doc-barcode mt-4" data-rateb-barcodes
    data-barcode="<?php echo Rateb\App\Core\View::escape((string) $docBarcode['barcode']); ?>"
    data-qr="<?php echo Rateb\App\Core\View::escape((string) ($docBarcode['qr_code'] ?? '')); ?>"
    data-label-title="<?php echo Rateb\App\Core\View::escape($safeFilename); ?>">
    <div class="rateb-card">
        <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="fas fa-qrcode"></i> <?php echo __('document_barcode'); ?></span>
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
            <p class="text-muted small mb-3"><?php echo __('document_barcode_hint'); ?></p>
            <div class="rateb-doc-barcode-print-area" id="<?php echo Rateb\App\Core\View::escape($uid); ?>"
                dir="<?php echo rateb_locale() === 'ar' ? 'rtl' : 'ltr'; ?>" lang="<?php echo Rateb\App\Core\View::escape(rateb_locale()); ?>">
                <div class="rateb-doc-barcode-brand text-center mb-2"><?php echo __('rateb_erp'); ?></div>
                <h5 class="text-center mb-1 rateb-doc-barcode-title"><?php echo Rateb\App\Core\View::escape((string) ($docBarcode['title'] ?? '')); ?></h5>
                <?php if (!empty($docBarcode['subtitle'])) { ?>
                <p class="text-center text-muted small mb-3 rateb-doc-barcode-subtitle"><?php echo Rateb\App\Core\View::escape((string) $docBarcode['subtitle']); ?></p>
                <?php } ?>
                <div class="text-center rateb-doc-barcode-codes">
                    <?php if (!empty($docBarcode['qr_code'])) { ?>
                    <img data-qr-img
                        src="<?php echo Rateb\App\Core\View::escape((string) ($docBarcode['qr_image_url'] ?? '')); ?>"
                        data-qr-fallback="<?php echo Rateb\App\Core\View::escape((string) ($docBarcode['qr_proxy_url'] ?? '')); ?>"
                        alt="<?php echo __('qr_code'); ?>" width="200" height="200" class="rateb-doc-qr-img" loading="lazy" decoding="async">
                    <?php } else { ?>
                    <canvas data-qr-canvas width="200" height="200"></canvas>
                    <?php } ?>
                </div>
                <p class="font-monospace text-center mt-3 mb-0 rateb-doc-barcode-code"><?php echo Rateb\App\Core\View::escape((string) $docBarcode['barcode']); ?></p>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo rateb_asset('js/barcodes.js'); ?>?v=4"></script>
