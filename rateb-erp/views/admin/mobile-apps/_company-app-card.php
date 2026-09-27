<?php
declare(strict_types=1);

/**
 * Company mobile app card — unified platform model: activation, name + logo, shared APK distribution.
 *
 * @var array<string,mixed> $appCard
 * @var string $csrf
 */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$cardApp = (string) $appCard['app'];
$cardCid = (int) $appCard['cid'];
$cardActive = !empty($appCard['active']);
$distribution = (string) ($appCard['distribution'] ?? 'missing');
$brand = is_array($appCard['branded'] ?? null) ? $appCard['branded'] : null;
$identityLogo = (string) ($appCard['identityLogo'] ?? '');
$showHrBranding = !empty($appCard['showHrBrandingLink']);
?>
<div class="rateb-card mb-3" id="rateb-app-apk-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <span>
            <i class="fas fa-mobile-screen-button"></i> <?php echo $e(__('mobile_apps_tab_' . $cardApp)); ?> —
            <?php if ($cardActive) { ?>
                <span class="badge text-bg-success"><?php echo $e(__('mobile_apps_status_active')); ?></span>
            <?php } else { ?>
                <span class="badge text-bg-secondary"><?php echo $e(__('mobile_apps_status_inactive')); ?></span>
            <?php } ?>
            <?php if ($distribution === 'shared') { ?>
                <span class="badge text-bg-primary ms-1"><?php echo $e(__('mobile_distribution_shared')); ?></span>
            <?php } elseif ($distribution === 'branded') { ?>
                <span class="badge text-bg-warning ms-1"><?php echo $e(__('mobile_distribution_branded')); ?></span>
            <?php } elseif ($distribution === 'own_upload') { ?>
                <span class="badge text-bg-info ms-1"><?php echo $e(__('mobile_distribution_own')); ?></span>
            <?php } ?>
        </span>
        <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/' . $cardCid . '/toggle')); ?>" class="d-inline">
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
        <div class="row g-3 align-items-center mb-3 pb-3 border-bottom" id="mobile-activation">
            <div class="col-md-8">
                <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_activation_code')); ?></label>
                <div class="fs-4 fw-bold font-monospace mb-2" dir="ltr"><?php echo $e($appCard['activationCode']); ?></div>
                <div class="small text-muted mb-2"><?php echo $e(__(!empty($appCard['needsCode']) ? 'mobile_activation_hint_required' : 'mobile_activation_hint_optional')); ?></div>
                <div class="input-group input-group-sm mb-2">
                    <input class="form-control" dir="ltr" readonly onclick="this.select()" value="<?php echo $e($appCard['activationUrl']); ?>">
                    <a class="btn btn-outline-primary" href="<?php echo $e($appCard['activationUrl']); ?>" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i></a>
                </div>
                <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/' . $cardCid . '/activation-code')); ?>" class="d-inline"
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
        <div class="mb-3 pb-3 border-bottom" id="mobile-company-identity">
            <div class="fw-semibold mb-2"><i class="fas fa-building"></i> <?php echo $e(__('mobile_identity_title')); ?></div>
            <p class="small text-muted"><?php echo $e(__('mobile_identity_intro')); ?></p>
            <div class="row g-3 align-items-start mb-2">
                <div class="col-md-3 text-center">
                    <?php if ($identityLogo !== '') { ?>
                        <img src="<?php echo $e($identityLogo); ?>" alt="" width="96" height="96" class="rounded border bg-white p-1" style="object-fit:contain">
                    <?php } else { ?>
                        <div class="rounded border bg-light d-flex align-items-center justify-content-center mx-auto" style="width:96px;height:96px">
                            <i class="fas fa-image text-muted fa-2x"></i>
                        </div>
                        <div class="small text-warning mt-1"><?php echo $e(__('mobile_identity_no_logo')); ?></div>
                    <?php } ?>
                </div>
                <div class="col-md-9">
                    <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/' . $cardCid . '/names')); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                        <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
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
                    <?php if ($showHrBranding) { ?>
                        <a class="btn btn-sm btn-outline-primary mt-2" href="#mobile-hr-branding"><i class="fas fa-palette"></i> <?php echo $e(__('mobile_identity_logo_link')); ?></a>
                    <?php } else { ?>
                        <div class="form-text mt-2"><?php echo $e(__('mobile_identity_logo_company')); ?></div>
                    <?php } ?>
                </div>
            </div>
            <?php if ($distribution === 'branded' && is_array($brand) && !empty($brand['requested'])) { ?>
                <div class="alert alert-warning py-2 small mb-0">
                    <?php echo $e(__('mobile_branded_unified_conflict')); ?>
                    <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/' . $cardCid . '/use-shared')); ?>" class="d-inline ms-1">
                        <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                        <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
                        <button type="submit" class="btn btn-sm btn-warning"><?php echo $e(__('mobile_use_shared_btn')); ?></button>
                    </form>
                </div>
            <?php } ?>
        </div>
        <?php } ?>

        <div class="mb-3 pb-3 border-bottom" id="mobile-distribution">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <span class="fw-semibold"><i class="fas fa-cloud-arrow-down"></i> <?php echo $e(__('mobile_distribution_title')); ?></span>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $e(rateb_url('admin/mobile-apps/updates') . '?app=' . $cardApp); ?>">
                    <i class="fas fa-cloud-arrow-up"></i> <?php echo $e(__('mobile_apps_updates_title')); ?>
                </a>
            </div>
            <p class="small text-muted"><?php echo $e(__('mobile_distribution_intro')); ?></p>
            <?php if ($distribution === 'missing' && $cardActive) { ?>
                <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/' . $cardCid . '/use-shared')); ?>" class="mb-2">
                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                    <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-link"></i> <?php echo $e(__('mobile_use_shared_btn')); ?></button>
                </form>
            <?php } elseif ($distribution === 'own_upload' && is_array($brand) && empty($brand['requested'])) { ?>
                <form method="post" action="<?php echo $e(rateb_url('admin/mobile-apps/' . $cardCid . '/use-shared')); ?>" class="mb-2">
                    <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
                    <input type="hidden" name="app" value="<?php echo $e($cardApp); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="fas fa-share-nodes"></i> <?php echo $e(__('mobile_use_shared_btn')); ?></button>
                </form>
            <?php } ?>
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
            <details class="small mt-2">
                <summary class="text-muted"><?php echo $e(__('mobile_distribution_tech')); ?></summary>
                <div class="mt-2">
                    <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_apps_server')); ?></label>
                    <input class="form-control form-control-sm mb-2" dir="ltr" readonly value="<?php echo $e($appCard['server']); ?>">
                    <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_apps_build_command_in')); ?> <code dir="ltr"><?php echo $e($appCard['buildDir']); ?></code></label>
                    <input class="form-control form-control-sm font-monospace" dir="ltr" readonly onclick="this.select()" value="<?php echo $e($appCard['buildCommand']); ?>">
                    <div class="form-text"><?php echo $e(__('mobile_apps_build_hint')); ?></div>
                </div>
            </details>
        </div>

        <?php if (is_array($brand)) {
            require __DIR__ . '/_company-app-branded-advanced.php';
        } ?>
    </div>
</div>
<script>
(function () {
    document.querySelectorAll('[data-rateb-branded-build-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('[data-rateb-branded-build-btn]');
            if (!btn || btn.disabled) return;
            btn.disabled = true;
            btn.innerHTML = <?php echo json_encode('<i class="fas fa-spinner fa-spin"></i> ' . __('mobile_branded_build_working'), JSON_UNESCAPED_UNICODE); ?>;
        });
    });
})();
</script>
