<?php
/** @var string $loginBarcode */
/** @var string $badgeScanQrUrl */
/** @var string $badgeLoginUrl */
/** @var string $csrf */
/** @var string $regenerateAction */
?>
<div class="rateb-card mb-3" data-login-badge-card data-badge-title="<?php echo Rateb\App\Core\View::escape($loginBarcode); ?>">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo __('login_badge'); ?></span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" data-badge-print>
                <i class="fas fa-print"></i> <?php echo __('print_label'); ?>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-badge-download>
                <i class="fas fa-download"></i> <?php echo __('download_png'); ?>
            </button>
        </div>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted small mb-3"><?php echo __('login_badge_hint'); ?></p>
        <div data-badge-print-area>
            <p class="rateb-badge-scan-label mb-2"><i class="fas fa-mobile-screen"></i> <?php echo __('badge_scan_this'); ?></p>
            <div class="rateb-badge-scan-wrap text-center mb-3">
                <?php if (!empty($badgeScanQrUrl)) { ?>
                <img src="<?php echo Rateb\App\Core\View::escape($badgeScanQrUrl); ?>" alt="<?php echo __('qr_code'); ?>" class="rateb-badge-scan-qr" width="280" height="280">
                <?php } ?>
            </div>
            <div class="font-monospace text-center p-2 border rounded mb-2 rateb-badge-code"><?php echo Rateb\App\Core\View::escape($loginBarcode); ?></div>
        </div>
        <?php if (!empty($badgeLoginUrl)) { ?>
        <p class="small text-muted mb-3 text-break">
            <i class="fas fa-link"></i>
            <a href="<?php echo Rateb\App\Core\View::escape($badgeLoginUrl); ?>" target="_blank" rel="noopener"><?php echo __('badge_open_link'); ?></a>
        </p>
        <?php } ?>
        <form method="post" action="<?php echo Rateb\App\Core\View::escape($regenerateAction); ?>" class="d-inline"
            onsubmit="return confirm('<?php echo Rateb\App\Core\View::escape(__('barcode_regenerate_confirm')); ?>');">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <button type="submit" class="btn btn-outline-warning btn-sm">
                <i class="fas fa-rotate"></i> <?php echo __('barcode_regenerate'); ?>
            </button>
        </form>
    </div>
</div>
<script src="<?php echo rateb_asset('js/login-badge-actions.js'); ?>"></script>
