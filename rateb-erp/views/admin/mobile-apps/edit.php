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
