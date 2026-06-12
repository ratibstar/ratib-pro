<?php $item = $item ?? []; ?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($item['item_name'] ?? __('barcode_qr')); ?></span>
        <?php if (rateb_can_manage_entity('inventory')) { ?>
        <form method="post" action="<?php echo rateb_app_url('inventory/' . (int) $item['id'] . '/codes/generate'); ?>" class="d-inline">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-barcode"></i> <?php echo __('generate_codes'); ?></button>
        </form>
        <?php } ?>
    </div>
    <div class="rateb-card-body">
        <div class="row g-4" data-rateb-barcodes data-barcode="<?php echo Rateb\App\Core\View::escape($item['barcode'] ?? ''); ?>" data-qr="<?php echo Rateb\App\Core\View::escape($item['qr_code'] ?? ''); ?>">
            <div class="col-md-6 text-center">
                <h6><?php echo __('barcode'); ?></h6>
                <?php if (!empty($item['barcode'])) { ?>
                <svg data-barcode-svg></svg>
                <div class="small text-muted mt-2"><?php echo Rateb\App\Core\View::escape($item['barcode']); ?></div>
                <?php } else { ?>
                <p class="text-muted"><?php echo __('no_barcode_yet'); ?></p>
                <?php } ?>
            </div>
            <div class="col-md-6 text-center">
                <h6><?php echo __('qr_code'); ?></h6>
                <?php if (!empty($item['qr_code'])) { ?>
                <canvas data-qr-canvas></canvas>
                <?php } else { ?>
                <p class="text-muted"><?php echo __('no_qr_yet'); ?></p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script src="<?php echo rateb_asset('js/barcodes.js'); ?>"></script>
<a href="<?php echo rateb_app_url('inventory'); ?>" class="btn btn-outline-secondary mt-3"><?php echo __('back_to_list'); ?></a>
