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

<?php if ($canToggleEnable && isset($appCard)) {
    require __DIR__ . '/_company-app-card.php';
} ?>

<?php if (!$canToggleEnable && isset($share) && is_array($share)) {
    $e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
    ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><i class="fas fa-share-nodes"></i> <?php echo $e(__('mobile_share_title')); ?></div>
    <div class="rateb-card-body">
        <p class="small text-muted"><?php echo $e(__('mobile_share_intro')); ?></p>
        <?php if (($share['code'] ?? '') !== '') { ?>
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <div>
                    <div class="small text-muted"><?php echo $e(__('mobile_activation_code')); ?></div>
                    <div class="fs-4 fw-bold font-monospace" dir="ltr"><?php echo $e($share['code']); ?></div>
                    <a class="small" href="<?php echo $e($share['activationUrl']); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo $e($share['activationUrl']); ?></a>
                </div>
                <?php if (($share['activationQr'] ?? '') !== '') { ?>
                    <img src="<?php echo $e($share['activationQr']); ?>" alt="QR" width="120" height="120" class="bg-white p-1 rounded">
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="alert alert-info py-2 small"><?php echo $e(__('mobile_share_code_ask')); ?></div>
        <?php } ?>
        <?php if (empty($share['apps'])) { ?>
            <div class="alert alert-warning py-2 small mb-0"><?php echo $e(__('mobile_activation_no_apps')); ?></div>
        <?php } else { ?>
            <div class="row g-3">
                <?php foreach ($share['apps'] as $shareApp) { ?>
                    <div class="col-sm-6 col-lg-4">
                        <div class="border rounded p-2 h-100 text-center">
                            <div class="fw-semibold mb-2"><?php echo $e(__('mobile_apps_tab_' . $shareApp['app'])); ?></div>
                            <img src="<?php echo $e($shareApp['qr']); ?>" alt="QR" width="120" height="120" class="bg-white p-1 rounded mb-2">
                            <div><a class="btn btn-sm btn-outline-primary" href="<?php echo $e($shareApp['url']); ?>"><i class="fas fa-download"></i> <?php echo $e(__('mobile_share_download')); ?></a></div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
<?php } ?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape(__($canToggleEnable ? 'mobile_apps_hr_branding' : 'mobile_apps_edit')); ?></div>
    <div class="rateb-card-body">
        <p class="mb-3">
            <strong><?php echo Rateb\App\Core\View::escape(__('company')); ?>:</strong>
            <?php echo Rateb\App\Core\View::escape((string) ($company['name'] ?? '')); ?>
            <span class="text-muted small">#<?php echo $cid; ?></span>
        </p>

        <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cid); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">

            <input type="hidden" name="status" value="<?php echo $statusActive ? 'active' : 'inactive'; ?>">

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
