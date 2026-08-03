<?php
declare(strict_types=1);

use Rateb\App\GuestMenu\Support\GuestMenuView;

/** @var string $title */
/** @var array<string, mixed> $settings */
/** @var string $publicUrl */
/** @var string $qrPreviewSrc */
/** @var string $qrDownloadUrl */
/** @var string $csrf */
?>
<div class="gm-admin-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h3 mb-0"><?php echo GuestMenuView::escape($title); ?></h1>
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
                        <label class="form-label" for="gm-mode"><?php echo __('guest_menu_mode'); ?></label>
                        <input type="hidden" name="mode" value="browse">
                        <select class="form-select" id="gm-mode" aria-describedby="gm-mode-hint gm-mode-soon" disabled aria-disabled="true">
                            <option value="browse" selected><?php echo __('guest_menu_mode_browse'); ?></option>
                        </select>
                        <div class="form-text" id="gm-mode-hint"><?php echo __('guest_menu_mode_hint_browse'); ?></div>
                        <p class="small text-muted mb-0 mt-1" id="gm-mode-soon"><?php echo __('guest_menu_mode_order_soon'); ?></p>
                    </div>

                    <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                </div>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5"><?php echo __('guest_menu_public_url'); ?></h2>
                    <?php if (!empty($settings['is_enabled']) && ($settings['public_slug'] ?? '') !== '') { ?>
                    <p><a href="<?php echo GuestMenuView::escape($publicUrl); ?>" target="_blank" rel="noopener"><?php echo GuestMenuView::escape($publicUrl); ?></a></p>
                    <?php if (($qrPreviewSrc ?? '') !== '') { ?>
                    <div class="gm-qr-wrap text-center my-3">
                        <img src="<?php echo GuestMenuView::escape($qrPreviewSrc); ?>" alt="QR" width="200" height="200" class="gm-qr-img" decoding="async">
                    </div>
                    <a class="btn btn-outline-primary btn-sm" href="<?php echo GuestMenuView::escape($qrDownloadUrl); ?>" download="guest-menu-qr.png">
                        <?php echo __('guest_menu_qr_download'); ?>
                    </a>
                    <?php } else { ?>
                    <p class="text-warning mb-0"><?php echo __('guest_menu_qr_unavailable'); ?></p>
                    <?php } ?>
                    <?php } else { ?>
                    <p class="text-muted mb-0"><?php echo __('guest_menu_qr_hint'); ?></p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
