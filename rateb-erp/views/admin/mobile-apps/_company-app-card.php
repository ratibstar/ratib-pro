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
