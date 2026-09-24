<?php
declare(strict_types=1);

/** @var array<string,mixed> $company */
/** @var array<string,mixed>|null $config */
/** @var array<string,bool> $features */
/** @var list<string> $featureKeys */
/** @var bool $canManage */
/** @var bool $canToggleEnable */
/** @var string $csrf */
$company = $company ?? [];
$config = $config ?? null;
$features = $features ?? [];
$featureKeys = $featureKeys ?? [];
$canManage = !empty($canManage);
$canToggleEnable = !empty($canToggleEnable);
$cid = (int) ($company['id'] ?? 0);
$readonly = !$canManage ? 'readonly' : '';
$disabled = !$canManage ? 'disabled' : '';
$statusActive = is_array($config) && (string) ($config['status'] ?? '') === 'active';
?>
<div class="mb-3">
    <a href="<?php echo rateb_url('admin/mobile-apps'); ?>" class="btn btn-sm btn-outline-secondary">
        &larr; <?php echo Rateb\App\Core\View::escape(__('mobile_apps_title')); ?>
    </a>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_edit')); ?></div>
    <div class="rateb-card-body">
        <p class="mb-3">
            <strong><?php echo Rateb\App\Core\View::escape(__('company')); ?>:</strong>
            <?php echo Rateb\App\Core\View::escape((string) ($company['name'] ?? '')); ?>
            <span class="text-muted small">#<?php echo $cid; ?></span>
        </p>

        <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cid); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">

            <?php if ($canToggleEnable) { ?>
            <div class="form-check form-switch mb-3">
                <input type="hidden" name="status" value="inactive">
                <input class="form-check-input" type="checkbox" role="switch" id="mobile_status"
                       name="status" value="active" <?php echo $statusActive ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                <label class="form-check-label" for="mobile_status">
                    <?php echo Rateb\App\Core\View::escape(__('mobile_apps_enable')); ?>
                </label>
            </div>
            <?php } else { ?>
            <input type="hidden" name="status" value="<?php echo $statusActive ? 'active' : 'inactive'; ?>">
            <?php } ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_app_name')); ?></label>
                    <input class="form-control" name="app_name" <?php echo $readonly; ?>
                           value="<?php echo Rateb\App\Core\View::escape((string) ($config['app_name'] ?? ($company['name'] ?? ''))); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_theme')); ?></label>
                    <input class="form-control" name="theme_color" <?php echo $readonly; ?>
                           value="<?php echo Rateb\App\Core\View::escape((string) ($config['theme_color'] ?? '#0D6EFD')); ?>"
                           placeholder="#0D6EFD">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_logo')); ?></label>
                    <input class="form-control" name="logo_path" <?php echo $readonly; ?>
                           value="<?php echo Rateb\App\Core\View::escape((string) ($config['logo_path'] ?? '')); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_icon')); ?></label>
                    <input class="form-control" name="icon_path" <?php echo $readonly; ?>
                           value="<?php echo Rateb\App\Core\View::escape((string) ($config['icon_path'] ?? '')); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_splash')); ?></label>
                    <input class="form-control" name="splash_path" <?php echo $readonly; ?>
                           value="<?php echo Rateb\App\Core\View::escape((string) ($config['splash_path'] ?? '')); ?>">
                </div>
            </div>

            <h2 class="h6"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_features')); ?></h2>
            <div class="row g-2 mb-3">
                <?php foreach ($featureKeys as $fkey) {
                    $labelKey = 'mobile_apps_feature_' . $fkey;
                    $checked = !empty($features[$fkey]);
                    ?>
                    <div class="col-md-4">
                        <div class="form-check">
                            <input type="hidden" name="features[<?php echo Rateb\App\Core\View::escape($fkey); ?>]" value="0">
                            <input class="form-check-input" type="checkbox"
                                   id="feat_<?php echo Rateb\App\Core\View::escape($fkey); ?>"
                                   name="features[<?php echo Rateb\App\Core\View::escape($fkey); ?>]"
                                   value="1" <?php echo $checked ? 'checked' : ''; ?> <?php echo $disabled; ?>>
                            <label class="form-check-label" for="feat_<?php echo Rateb\App\Core\View::escape($fkey); ?>">
                                <?php echo Rateb\App\Core\View::escape(__($labelKey)); ?>
                            </label>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <?php if ($canManage) { ?>
                <button type="submit" class="btn btn-primary"><?php echo Rateb\App\Core\View::escape(__('save')); ?></button>
            <?php } ?>
        </form>
    </div>
</div>

<?php if ($canToggleEnable) {
    $apk = is_array($apk ?? null) ? $apk : null;
    $apkUrl = (string) ($apkUrl ?? '');
    ?>
<div class="rateb-card mb-3" id="rateb-hr-apk-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center gap-2">
        <span><i class="fas fa-mobile-screen-button"></i> <?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_card')); ?></span>
        <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cid . '/toggle'); ?>" class="d-inline">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <input type="hidden" name="back" value="edit">
            <input type="hidden" name="status" value="<?php echo $statusActive ? 'inactive' : 'active'; ?>">
            <?php if ($statusActive) { ?>
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-power-off"></i> <?php echo Rateb\App\Core\View::escape(__('mobile_apps_disable_btn')); ?></button>
            <?php } else { ?>
                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> <?php echo Rateb\App\Core\View::escape(__('mobile_apps_enable_btn')); ?></button>
            <?php } ?>
        </form>
    </div>
    <div class="rateb-card-body">
        <div class="mb-3">
            <label class="form-label small text-muted mb-1"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_server_url')); ?></label>
            <input class="form-control form-control-sm" dir="ltr" readonly value="<?php echo Rateb\App\Core\View::escape((string) ($erpBaseUrl ?? '')); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label small text-muted mb-1"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_build_command')); ?></label>
            <input class="form-control form-control-sm font-monospace" dir="ltr" readonly onclick="this.select()" value="<?php echo Rateb\App\Core\View::escape((string) ($buildCommand ?? '')); ?>">
            <div class="form-text"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_build_hint')); ?></div>
        </div>

        <?php if ($apk !== null) { ?>
        <div class="row g-3 align-items-center mb-3">
            <div class="col-md-8">
                <dl class="row small mb-2">
                    <dt class="col-sm-4"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_file')); ?></dt>
                    <dd class="col-sm-8" dir="ltr"><?php echo Rateb\App\Core\View::escape((string) ($apk['original_name'] ?? '')); ?> — <?php echo Rateb\App\Core\View::escape(number_format(((int) ($apk['size'] ?? 0)) / 1048576, 1)); ?> MB</dd>
                    <dt class="col-sm-4"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_uploaded_at')); ?></dt>
                    <dd class="col-sm-8" dir="ltr"><?php echo Rateb\App\Core\View::escape((string) ($apk['uploaded_at'] ?? '')); ?></dd>
                    <dt class="col-sm-4"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_server_url')); ?></dt>
                    <dd class="col-sm-8 text-break" dir="ltr"><?php echo Rateb\App\Core\View::escape((string) ($apk['erp_base_url'] ?? '')); ?></dd>
                    <dt class="col-sm-4">SHA-256</dt>
                    <dd class="col-sm-8 text-break font-monospace" dir="ltr" style="font-size:.75rem"><?php echo Rateb\App\Core\View::escape((string) ($apk['sha256'] ?? '')); ?></dd>
                </dl>
                <?php if (!$statusActive) { ?>
                    <div class="alert alert-warning py-2 small mb-2"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_inactive_hint')); ?></div>
                <?php } ?>
                <label class="form-label small text-muted mb-1"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_link')); ?></label>
                <div class="input-group input-group-sm mb-2">
                    <input class="form-control" dir="ltr" readonly onclick="this.select()" value="<?php echo Rateb\App\Core\View::escape($apkUrl); ?>">
                    <a class="btn btn-outline-primary" href="<?php echo Rateb\App\Core\View::escape($apkUrl); ?>" download><i class="fas fa-download"></i></a>
                </div>
                <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cid . '/apk-delete'); ?>"
                      onsubmit="return confirm(<?php echo Rateb\App\Core\View::escape((string) json_encode(__('mobile_apps_apk_delete_confirm'), JSON_UNESCAPED_UNICODE)); ?>);">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> <?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_delete')); ?></button>
                </form>
            </div>
            <div class="col-md-4 text-center">
                <img src="<?php echo Rateb\App\Core\View::escape((string) ($apkQr ?? '')); ?>" alt="QR" width="200" height="200" class="img-fluid rounded border bg-white p-2">
                <div class="small text-muted mt-1"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_qr_hint')); ?></div>
            </div>
        </div>
        <?php } else { ?>
            <div class="alert alert-info py-2 small"><?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_none_yet')); ?></div>
        <?php } ?>

        <div data-rateb-apk-upload
             data-url="<?php echo Rateb\App\Core\View::escape(rateb_url('admin/mobile-apps/' . $cid . '/apk-chunk')); ?>"
             data-csrf="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>"
             data-chunk="<?php echo (int) ($apkChunkBytes ?? 2097152); ?>"
             data-max="<?php echo (int) ($apkMaxBytes ?? 0); ?>"
             data-msg-not-apk="<?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_not_apk')); ?>"
             data-msg-too-large="<?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_upload_too_large')); ?>"
             data-msg-failed="<?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_upload_failed')); ?>">
            <label class="form-label"><?php echo Rateb\App\Core\View::escape(__($apk !== null ? 'mobile_apps_apk_replace' : 'mobile_apps_apk_upload')); ?></label>
            <div class="input-group">
                <input type="file" class="form-control" accept=".apk,application/vnd.android.package-archive" data-rateb-apk-file>
                <button type="button" class="btn btn-primary" data-rateb-apk-start><i class="fas fa-upload"></i> <?php echo Rateb\App\Core\View::escape(__('mobile_apps_apk_upload_btn')); ?></button>
            </div>
            <div class="progress mt-2 d-none" role="progressbar" data-rateb-apk-progress-wrap>
                <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%" data-rateb-apk-progress>0%</div>
            </div>
            <div class="small text-danger mt-2 d-none" data-rateb-apk-error></div>
        </div>
    </div>
</div>
<script src="<?php echo Rateb\App\Core\View::escape(rateb_asset('js/mobile-apps-apk-upload.js')); ?>"></script>
<?php } ?>
