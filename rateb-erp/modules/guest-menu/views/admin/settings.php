<?php
declare(strict_types=1);

use Rateb\App\GuestMenu\Support\GuestMenuView;

/** @var string $title */
/** @var array<string, mixed> $settings */
/** @var string $publicUrl */
/** @var string $qrPreviewSrc */
/** @var string $qrDownloadUrl */
/** @var array{product_count:int, category_count:int} $catalogStats */
/** @var string $inventoryUrl */
/** @var string $platformCatalogUrl */
/** @var bool $platformCatalogEnabled */
/** @var list<array<string, mixed>> $branches */
/** @var string $csrf */
$productCount = (int) ($catalogStats['product_count'] ?? 0);
$categoryCount = (int) ($catalogStats['category_count'] ?? 0);
$branchId = isset($settings['branch_id']) ? (int) $settings['branch_id'] : 0;
?>
<div class="gm-admin-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0"><?php echo GuestMenuView::escape($title); ?></h1>
        <a class="btn btn-outline-primary btn-sm" href="<?php echo GuestMenuView::escape(rateb_app_url('guest-menu/orders')); ?>">
            <?php echo __('guest_menu_orders_title'); ?>
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <form method="post" action="<?php echo rateb_app_url('guest-menu'); ?>" class="card shadow-sm">
                <div class="card-body">
                    <input type="hidden" name="_csrf" value="<?php echo GuestMenuView::escape($csrf); ?>">

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="gm-enabled"
                            <?php echo !empty($settings['is_enabled']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="gm-enabled"><?php echo __('guest_menu_enable'); ?></label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="gm-slug"><?php echo __('guest_menu_public_slug'); ?></label>
                        <input class="form-control" id="gm-slug" name="public_slug" required minlength="3" maxlength="64"
                            pattern="[a-z0-9-]+"
                            placeholder="<?php echo GuestMenuView::escape(__('guest_menu_slug_placeholder')); ?>"
                            value="<?php echo GuestMenuView::escape((string) ($settings['public_slug'] ?? '')); ?>">
                        <div class="form-text"><?php echo __('guest_menu_slug_hint'); ?></div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="gm-title-ar"><?php echo __('guest_menu_title_ar'); ?></label>
                            <input class="form-control" id="gm-title-ar" name="title_ar"
                                placeholder="<?php echo GuestMenuView::escape(__('guest_menu_title_ar_placeholder')); ?>"
                                value="<?php echo GuestMenuView::escape((string) ($settings['title_ar'] ?? '')); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="gm-title-en"><?php echo __('guest_menu_title_en'); ?></label>
                            <input class="form-control" id="gm-title-en" name="title_en"
                                placeholder="<?php echo GuestMenuView::escape(__('guest_menu_title_en_placeholder')); ?>"
                                value="<?php echo GuestMenuView::escape((string) ($settings['title_en'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="gm-welcome"><?php echo __('guest_menu_welcome'); ?></label>
                        <textarea class="form-control" id="gm-welcome" name="welcome_message" rows="2"
                            placeholder="<?php echo GuestMenuView::escape(__('guest_menu_welcome_placeholder')); ?>"><?php echo GuestMenuView::escape((string) ($settings['welcome_message'] ?? '')); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="gm-branch"><?php echo __('guest_menu_branch'); ?></label>
                        <select class="form-select" id="gm-branch" name="branch_id">
                            <option value=""><?php echo __('guest_menu_branch_all'); ?></option>
                            <?php foreach ($branches ?? [] as $branch) {
                                $bid = (int) ($branch['id'] ?? 0);
                                ?>
                            <option value="<?php echo $bid; ?>"<?php echo $branchId === $bid ? ' selected' : ''; ?>><?php echo GuestMenuView::escape((string) ($branch['name'] ?? ('#' . $bid))); ?></option>
                            <?php } ?>
                        </select>
                        <div class="form-text"><?php echo __('guest_menu_branch_hint'); ?></div>
                    </div>

                    <div class="mb-3">
                        <?php
                        $currentMode = (string) ($settings['mode'] ?? 'browse');
                        if (!in_array($currentMode, ['browse', 'order'], true)) {
                            $currentMode = 'browse';
                        }
                        ?>
                        <label class="form-label" for="gm-mode"><?php echo __('guest_menu_mode'); ?></label>
                        <select class="form-select" id="gm-mode" name="mode" aria-describedby="gm-mode-hint">
                            <option value="browse"<?php echo $currentMode === 'browse' ? ' selected' : ''; ?>><?php echo __('guest_menu_mode_browse'); ?></option>
                            <option value="order"<?php echo $currentMode === 'order' ? ' selected' : ''; ?>><?php echo __('guest_menu_mode_order'); ?></option>
                        </select>
                        <div class="form-text" id="gm-mode-hint"><?php echo $currentMode === 'order' ? __('guest_menu_mode_hint_order') : __('guest_menu_mode_hint_browse'); ?></div>
                    </div>

                    <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                </div>
            </form>

            <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <h2 class="h5 mb-3"><?php echo __('guest_menu_catalog_panel_title'); ?></h2>
                    <p class="mb-2">
                        <?php echo __('guest_menu_catalog_stats', [
                            'products' => (string) $productCount,
                            'categories' => (string) $categoryCount,
                        ]); ?>
                    </p>
                    <?php if ($productCount < 1) { ?>
                    <div class="alert alert-warning mb-3"><?php echo __('guest_menu_catalog_empty'); ?></div>
                    <?php } ?>
                    <p class="text-muted small mb-3"><?php echo __('guest_menu_catalog_flow'); ?></p>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo GuestMenuView::escape($inventoryUrl); ?>">
                            <?php echo __('guest_menu_open_inventory'); ?>
                        </a>
                        <?php if (!empty($platformCatalogEnabled) && ($platformCatalogUrl ?? '') !== '') { ?>
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo GuestMenuView::escape($platformCatalogUrl); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo __('guest_menu_open_platform_catalog'); ?>
                        </a>
                        <?php } ?>
                        <form method="post" action="<?php echo GuestMenuView::escape(rateb_app_url('guest-menu/import-catalog')); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo GuestMenuView::escape($csrf); ?>">
                            <button type="submit" class="btn btn-primary btn-sm"><?php echo __('guest_menu_import_catalog'); ?></button>
                        </form>
                        <form method="post" action="<?php echo GuestMenuView::escape(rateb_app_url('guest-menu/seed-demo')); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo GuestMenuView::escape($csrf); ?>">
                            <button type="submit" class="btn btn-outline-primary btn-sm"><?php echo __('guest_menu_seed_demo'); ?></button>
                        </form>
                    </div>
                    <p class="text-muted small mt-3 mb-0"><?php echo __('guest_menu_mobile_scan_tip'); ?></p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5"><?php echo __('guest_menu_public_url'); ?></h2>
                    <?php if (!empty($settings['is_enabled']) && ($settings['public_slug'] ?? '') !== '' && ($publicUrl ?? '') !== '') { ?>
                    <p><a href="<?php echo GuestMenuView::escape($publicUrl); ?>" target="_blank" rel="noopener"><?php echo GuestMenuView::escape($publicUrl); ?></a></p>
                    <div class="gm-qr-wrap text-center my-3">
                        <?php if (($qrPreviewSrc ?? '') !== '') { ?>
                        <img class="gm-qr-img" id="gm-qr-img" src="<?php echo GuestMenuView::escape($qrPreviewSrc); ?>" alt="QR" width="200" height="200">
                        <?php } else { ?>
                        <div id="gm-qr-box" data-url="<?php echo GuestMenuView::escape($publicUrl); ?>"></div>
                        <?php } ?>
                    </div>
                    <a class="btn btn-outline-primary btn-sm" href="<?php echo GuestMenuView::escape($qrDownloadUrl); ?>" download="guest-menu-qr.png">
                        <?php echo __('guest_menu_qr_download'); ?>
                    </a>
                    <?php } else { ?>
                    <p class="text-muted mb-0"><?php echo __('guest_menu_qr_hint'); ?></p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($settings['is_enabled']) && ($settings['public_slug'] ?? '') !== '' && ($publicUrl ?? '') !== '' && ($qrPreviewSrc ?? '') === '') {
    $qrJs = function_exists('rateb_qrcode_js') ? rateb_qrcode_js() : (function_exists('rateb_vendor_asset') ? rateb_vendor_asset('qrcodejs/qrcode.min.js') : '/assets/vendor/qrcodejs/qrcode.min.js');
    ?>
<script src="<?php echo GuestMenuView::escape($qrJs); ?>"></script>
<script>
(function () {
    var box = document.getElementById('gm-qr-box');
    var mode = document.getElementById('gm-mode');
    var hint = document.getElementById('gm-mode-hint');
    if (box && window.QRCode) {
        var url = box.getAttribute('data-url') || '';
        if (url) {
            box.innerHTML = '';
            new QRCode(box, { text: url, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.H });
        }
    }
    if (mode && hint) {
        var hints = {
            browse: <?php echo json_encode(__('guest_menu_mode_hint_browse'), JSON_UNESCAPED_UNICODE); ?>,
            order: <?php echo json_encode(__('guest_menu_mode_hint_order'), JSON_UNESCAPED_UNICODE); ?>
        };
        mode.addEventListener('change', function () {
            hint.textContent = hints[mode.value] || hints.browse;
        });
    }
})();
</script>
<?php } elseif (!empty($settings['is_enabled']) && ($settings['public_slug'] ?? '') !== '' && ($publicUrl ?? '') !== '') { ?>
<script>
(function () {
    var mode = document.getElementById('gm-mode');
    var hint = document.getElementById('gm-mode-hint');
    if (mode && hint) {
        var hints = {
            browse: <?php echo json_encode(__('guest_menu_mode_hint_browse'), JSON_UNESCAPED_UNICODE); ?>,
            order: <?php echo json_encode(__('guest_menu_mode_hint_order'), JSON_UNESCAPED_UNICODE); ?>
        };
        mode.addEventListener('change', function () {
            hint.textContent = hints[mode.value] || hints.browse;
        });
    }
})();
</script>
<?php } ?>
