<?php
/** @var array<string, mixed> $doc */
$safeFilename = preg_replace('/[^\w\.\-]+/u', '_', (string) ($doc['title'] ?? 'document')) ?: 'document';
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/document-barcodes.css'); ?>?v=4">
<div class="rateb-scan-view" data-rateb-scan-view data-label-title="<?php echo Rateb\App\Core\View::escape($safeFilename); ?>">
    <p class="text-muted small text-center mb-3"><?php echo __('scan_view_only_hint'); ?></p>
    <div class="d-flex justify-content-center gap-2 mb-3 rateb-scan-actions">
        <button type="button" class="btn btn-sm btn-outline-primary" data-scan-print>
            <i class="fas fa-print"></i> <?php echo __('print_label'); ?>
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-scan-download>
            <i class="fas fa-download"></i> <?php echo __('download_png'); ?>
        </button>
    </div>
    <div class="rateb-scan-print-area rateb-doc-barcode-print-area" dir="<?php echo rateb_locale() === 'ar' ? 'rtl' : 'ltr'; ?>">
        <div class="rateb-doc-barcode-brand text-center mb-2"><?php echo __('rateb_erp'); ?></div>
        <div class="text-center mb-3">
            <?php if (!empty($doc['qr_image_url'])) { ?>
            <img data-qr-img src="<?php echo Rateb\App\Core\View::escape((string) $doc['qr_image_url']); ?>" alt="" width="180" height="180" class="rateb-doc-qr-img mb-2">
            <?php } ?>
            <h4 class="mb-1 rateb-doc-barcode-title"><?php echo Rateb\App\Core\View::escape((string) ($doc['title'] ?? '')); ?></h4>
            <?php if (!empty($doc['subtitle'])) { ?>
            <p class="text-muted mb-2 rateb-doc-barcode-subtitle"><?php echo Rateb\App\Core\View::escape((string) $doc['subtitle']); ?></p>
            <?php } ?>
            <p class="font-monospace small rateb-doc-barcode-code mb-0"><?php echo Rateb\App\Core\View::escape((string) ($doc['barcode'] ?? '')); ?></p>
        </div>
        <div class="rateb-card mb-0">
            <div class="rateb-card-body py-3">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted"><?php echo __('document_type'); ?></dt>
                    <dd class="col-7"><?php echo Rateb\App\Core\View::escape((string) ($doc['type_label'] ?? '')); ?></dd>
                    <?php foreach (($doc['fields'] ?? []) as $field) { ?>
                    <dt class="col-5 text-muted"><?php echo Rateb\App\Core\View::escape((string) ($field['label'] ?? '')); ?></dt>
                    <dd class="col-7"><?php echo Rateb\App\Core\View::escape((string) ($field['value'] ?? '')); ?></dd>
                    <?php } ?>
                </dl>
            </div>
        </div>
    </div>
</div>
<script src="<?php echo rateb_asset('js/document-scan.js'); ?>?v=1"></script>
