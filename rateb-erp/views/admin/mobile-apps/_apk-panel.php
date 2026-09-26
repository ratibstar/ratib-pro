<?php
declare(strict_types=1);

/**
 * Uploaded APK details + public link/QR + chunked upload widget.
 *
 * @var array<string,mixed>|null $apk        own uploaded build
 * @var array<string,mixed>|null $sharedApk  shared build this company falls back to (no own build)
 * @var string $apkUrl
 * @var string $apkQr
 * @var string $apkChunkUrl
 * @var string $apkDeleteUrl
 * @var array<string,string> $apkDeleteFields extra hidden fields for the delete form
 * @var bool $apkInactive   link disabled (app switched off for the company)
 * @var string $csrf
 */
$apk = is_array($apk ?? null) ? $apk : null;
$sharedApk = is_array($sharedApk ?? null) ? $sharedApk : null;
$apkUrl = (string) ($apkUrl ?? '');
$shown = $apk ?? $sharedApk;
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
?>
<?php if ($shown !== null) { ?>
<div class="row g-3 align-items-center mb-3">
    <div class="col-md-8">
        <?php if ($apk === null) { ?>
            <div class="alert alert-secondary py-2 small mb-2"><i class="fas fa-share-nodes"></i> <?php echo $e(__('mobile_apps_uses_shared_hint')); ?></div>
        <?php } ?>
        <dl class="row small mb-2">
            <dt class="col-sm-4"><?php echo $e(__('mobile_apps_apk_file')); ?></dt>
            <dd class="col-sm-8" dir="ltr"><?php echo $e($shown['original_name'] ?? ''); ?> — <?php echo $e(number_format(((int) ($shown['size'] ?? 0)) / 1048576, 1)); ?> MB</dd>
            <dt class="col-sm-4"><?php echo $e(__('mobile_apps_apk_uploaded_at')); ?></dt>
            <dd class="col-sm-8" dir="ltr"><?php echo $e($shown['uploaded_at'] ?? ''); ?></dd>
            <?php $builtFor = (string) ($shown['server'] ?? $shown['erp_base_url'] ?? ''); ?>
            <?php if ($builtFor !== '') { ?>
            <dt class="col-sm-4"><?php echo $e(__('mobile_apps_built_for')); ?></dt>
            <dd class="col-sm-8 text-break" dir="ltr"><?php echo $e($builtFor); ?></dd>
            <?php } ?>
            <dt class="col-sm-4">SHA-256</dt>
            <dd class="col-sm-8 text-break font-monospace" dir="ltr" style="font-size:.75rem"><?php echo $e($shown['sha256'] ?? ''); ?></dd>
        </dl>
        <?php if (!empty($apkInactive)) { ?>
            <div class="alert alert-warning py-2 small mb-2"><?php echo $e(__('mobile_apps_apk_inactive_hint')); ?></div>
        <?php } ?>
        <label class="form-label small text-muted mb-1"><?php echo $e(__('mobile_apps_apk_link')); ?></label>
        <div class="input-group input-group-sm mb-2">
            <input class="form-control" dir="ltr" readonly onclick="this.select()" value="<?php echo $e($apkUrl); ?>">
            <a class="btn btn-outline-primary" href="<?php echo $e($apkUrl); ?>" download><i class="fas fa-download"></i></a>
        </div>
        <?php if ($apk !== null) { ?>
        <form method="post" action="<?php echo $e($apkDeleteUrl ?? ''); ?>"
              onsubmit="return confirm(<?php echo $e((string) json_encode(__('mobile_apps_apk_delete_confirm'), JSON_UNESCAPED_UNICODE)); ?>);">
            <input type="hidden" name="_csrf" value="<?php echo $e($csrf ?? ''); ?>">
            <?php foreach (($apkDeleteFields ?? []) as $fName => $fValue) { ?>
                <input type="hidden" name="<?php echo $e($fName); ?>" value="<?php echo $e($fValue); ?>">
            <?php } ?>
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> <?php echo $e(__('mobile_apps_apk_delete')); ?></button>
        </form>
        <?php } ?>
    </div>
    <?php if (($apkQr ?? '') !== '') { ?>
    <div class="col-md-4 text-center">
        <img src="<?php echo $e($apkQr); ?>" alt="QR" width="200" height="200" class="img-fluid rounded border bg-white p-2">
        <div class="small text-muted mt-1"><?php echo $e(__('mobile_apps_apk_qr_hint')); ?></div>
    </div>
    <?php } ?>
</div>
<?php } else { ?>
    <div class="alert alert-info py-2 small"><?php echo $e(__($apkNoneKey ?? 'mobile_apps_apk_none_yet')); ?></div>
<?php } ?>

<div data-rateb-apk-upload
     data-url="<?php echo $e($apkChunkUrl ?? ''); ?>"
     data-csrf="<?php echo $e($csrf ?? ''); ?>"
     data-chunk="<?php echo (int) ($apkChunkBytes ?? 2097152); ?>"
     data-max="<?php echo (int) ($apkMaxBytes ?? 0); ?>"
     data-msg-not-apk="<?php echo $e(__('mobile_apps_apk_not_apk')); ?>"
     data-msg-too-large="<?php echo $e(__('mobile_apps_apk_upload_too_large')); ?>"
     data-msg-failed="<?php echo $e(__('mobile_apps_apk_upload_failed')); ?>">
    <label class="form-label"><?php echo $e(__($apk !== null ? 'mobile_apps_apk_replace' : 'mobile_apps_apk_upload')); ?></label>
    <div class="input-group">
        <input type="file" class="form-control" accept=".apk,application/vnd.android.package-archive" data-rateb-apk-file>
        <button type="button" class="btn btn-primary" data-rateb-apk-start><i class="fas fa-upload"></i> <?php echo $e(__('mobile_apps_apk_upload_btn')); ?></button>
    </div>
    <div class="progress mt-2 d-none" role="progressbar" data-rateb-apk-progress-wrap>
        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%" data-rateb-apk-progress>0%</div>
    </div>
    <div class="small text-danger mt-2 d-none" data-rateb-apk-error></div>
</div>
<script src="<?php echo $e(rateb_asset('js/mobile-apps-apk-upload.js')); ?>"></script>
