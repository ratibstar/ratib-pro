<?php
declare(strict_types=1);

/**
 * Same card for every app on a company page: enable/disable, server, build command, APK + link/QR.
 *
 * @var array<string,mixed> $appCard
 * @var string $csrf
 */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$cardApp = (string) $appCard['app'];
$cardCid = (int) $appCard['cid'];
$cardActive = !empty($appCard['active']);
?>
<div class="rateb-card mb-3" id="rateb-app-apk-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center gap-2">
        <span>
            <i class="fas fa-mobile-screen-button"></i> <?php echo $e(__('mobile_apps_tab_' . $cardApp)); ?> —
            <?php if ($cardActive) { ?>
                <span class="badge text-bg-success"><?php echo $e(__('mobile_apps_status_active')); ?></span>
            <?php } else { ?>
                <span class="badge text-bg-secondary"><?php echo $e(__('mobile_apps_status_inactive')); ?></span>
            <?php } ?>
        </span>
        <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cardCid . '/toggle'); ?>" class="d-inline">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
            <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
            <input type="hidden" name="back" value="edit">
            <input type="hidden" name="status" value="<?php echo $cardActive ? 'inactive' : 'active'; ?>">
            <?php if ($cardActive) { ?>
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-power-off"></i> <?php echo $e(__('mobile_apps_disable_btn')); ?></button>
            <?php } else { ?>
                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> <?php echo $e(__('mobile_apps_enable_btn')); ?></button>
            <?php } ?>
        </form>
    </div>
    <div class="rateb-card-body">
        <?php if (($appCard['activationCode'] ?? '') !== '') { ?>
        <div class="row g-3 align-items-center mb-3 pb-3 border-bottom">
            <div class="col-md-8">
                <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_activation_code')); ?></label>
                <div class="fs-4 fw-bold font-monospace mb-2" dir="ltr"><?php echo $e($appCard['activationCode']); ?></div>
                <div class="small text-muted mb-2"><?php echo $e(__(!empty($appCard['needsCode']) ? 'mobile_activation_hint_required' : 'mobile_activation_hint_optional')); ?></div>
                <div class="input-group input-group-sm mb-2">
                    <input class="form-control" dir="ltr" readonly onclick="this.select()" value="<?php echo $e($appCard['activationUrl']); ?>">
                    <a class="btn btn-outline-primary" href="<?php echo $e($appCard['activationUrl']); ?>" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i></a>
                </div>
                <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cardCid . '/activation-code'); ?>" class="d-inline"
                      onsubmit="return confirm(<?php echo $e((string) json_encode(__('mobile_activation_regenerate_confirm'), JSON_UNESCAPED_UNICODE)); ?>);">
                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                    <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-rotate"></i> <?php echo $e(__('mobile_activation_regenerate')); ?></button>
                </form>
            </div>
            <div class="col-md-4 text-center">
                <img src="<?php echo $e($appCard['activationQr']); ?>" alt="QR" width="180" height="180" class="img-fluid rounded border bg-white p-2">
                <div class="small text-muted mt-1"><?php echo $e(__('mobile_activation_qr_hint')); ?></div>
            </div>
        </div>
        <?php } ?>
        <?php $cardNames = $appCard['names'] ?? null; ?>
        <?php if (is_array($cardNames)) { ?>
        <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cardCid . '/names'); ?>" class="mb-3 pb-3 border-bottom">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
            <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
            <div class="fw-semibold mb-2"><i class="fas fa-signature"></i> <?php echo $e(__('mobile_names_title')); ?></div>
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1" for="mobile-name-ar"><?php echo $e(__('mobile_names_ar')); ?></label>
                    <input class="form-control form-control-sm" id="mobile-name-ar" name="name_ar" dir="rtl" maxlength="40" value="<?php echo $e($cardNames['ar']); ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label small text-muted mb-1" for="mobile-name-en"><?php echo $e(__('mobile_names_en')); ?></label>
                    <input class="form-control form-control-sm" id="mobile-name-en" name="name_en" dir="ltr" maxlength="40" value="<?php echo $e($cardNames['en']); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100"><?php echo $e(__('mobile_names_save')); ?></button>
                </div>
            </div>
            <div class="form-text"><?php echo $e(__('mobile_names_hint')); ?></div>
        </form>
        <?php } ?>
        <?php $brand = $appCard['branded'] ?? null; ?>
        <?php if (is_array($brand)) { ?>
        <div class="mb-3 pb-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                <span class="fw-semibold"><i class="fas fa-palette"></i> <?php echo $e(__('mobile_branded_title')); ?></span>
                <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cardCid . '/branded'); ?>" class="d-inline"
                      <?php if ($brand['requested']) { ?>onsubmit="return confirm(<?php echo $e((string) json_encode(__('mobile_branded_cancel_confirm'), JSON_UNESCAPED_UNICODE)); ?>);"<?php } ?>>
                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                    <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
                    <input type="hidden" name="requested" value="<?php echo $brand['requested'] ? '0' : '1'; ?>">
                    <?php if ($brand['requested']) { ?>
                        <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo $e(__('mobile_branded_cancel')); ?></button>
                    <?php } else { ?>
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-wand-magic-sparkles"></i> <?php echo $e(__('mobile_branded_request')); ?></button>
                    <?php } ?>
                </form>
            </div>
            <?php if (!$brand['requested']) { ?>
                <div class="small text-muted"><?php echo $e(__('mobile_branded_hint')); ?></div>
            <?php } else { ?>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <?php if ($brand['icon'] !== '') { ?>
                        <img src="<?php echo $e($brand['icon']); ?>" alt="" width="48" height="48" class="rounded border bg-white" style="object-fit:contain">
                    <?php } ?>
                    <div class="small">
                        <div class="fw-semibold"><?php echo $e($brand['name']); ?></div>
                        <div class="text-muted font-monospace" dir="ltr"><?php echo $e($brand['package']); ?></div>
                    </div>
                    <div class="ms-auto">
                        <?php if ($brand['built']) { ?>
                            <span class="badge text-bg-success"><?php echo $e(sprintf(__('mobile_branded_ready'), $brand['version'])); ?></span>
                        <?php } else { ?>
                            <span class="badge text-bg-warning"><?php echo $e(__('mobile_branded_pending')); ?></span>
                        <?php } ?>
                    </div>
                </div>
                <?php if ($brand['icon'] === '') { ?>
                    <div class="alert alert-info py-2 small mb-2"><?php echo $e(__('mobile_branded_no_icon')); ?></div>
                <?php } ?>
                <?php if (!empty($brand['needs_build']) || !empty($brand['queued'])) { ?>
                <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cardCid . '/branded-build'); ?>" class="mb-2">
                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                    <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
                    <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-hammer"></i> <?php echo $e(__('mobile_branded_build_btn')); ?></button>
                </form>
                <div class="form-text mb-2"><?php echo $e(!empty($appCard['dispatchEnabled']) ? __('mobile_branded_build_hint_auto') : __('mobile_branded_build_hint_manual')); ?></div>
                <?php } ?>
                <details class="small">
                    <summary class="text-muted"><?php echo $e(__('mobile_branded_command')); ?></summary>
                    <input class="form-control form-control-sm font-monospace mt-1" dir="ltr" readonly onclick="this.select()" value="<?php echo $e($brand['command']); ?>">
                </details>
            <?php } ?>
        </div>
        <?php } ?>
        <div class="mb-3">
            <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_apps_server')); ?></label>
            <input class="form-control form-control-sm" dir="ltr" readonly value="<?php echo $e($appCard['server']); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_apps_build_command_in')); ?> <code dir="ltr"><?php echo $e($appCard['buildDir']); ?></code></label>
            <input class="form-control form-control-sm font-monospace" dir="ltr" readonly onclick="this.select()" value="<?php echo $e($appCard['buildCommand']); ?>">
            <div class="form-text"><?php echo $e(__('mobile_apps_build_hint')); ?></div>
        </div>
        <?php
        $apk = $appCard['apk'];
        $sharedApk = $appCard['sharedApk'];
        $apkUrl = (string) $appCard['url'];
        $apkQr = ($appCard['activationCode'] ?? '') !== '' ? '' : (string) $appCard['qr'];
        $apkChunkUrl = rateb_url('admin/mobile-apps/' . $cardCid . '/apk-chunk') . '?app=' . $cardApp;
        $apkDeleteUrl = rateb_url('admin/mobile-apps/' . $cardCid . '/apk-delete');
        $apkDeleteFields = ['app' => $cardApp];
        $apkInactive = !$cardActive;
        $apkNoneKey = 'mobile_apps_apk_none_yet';
        $apkChunkBytes = (int) $appCard['chunkBytes'];
        $apkMaxBytes = (int) $appCard['maxBytes'];
        require __DIR__ . '/_apk-panel.php';
        ?>
    </div>
</div>
