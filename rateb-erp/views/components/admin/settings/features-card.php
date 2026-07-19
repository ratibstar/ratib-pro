<?php
declare(strict_types=1);

/** @var bool $hrMobileConsoleEnabled */
/** @var string $csrf */
$hrMobileConsoleEnabled = !empty($hrMobileConsoleEnabled);
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape(__('settings_features')); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url('admin/settings/save-features'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
            <div class="form-check form-switch mb-2">
                <input type="hidden" name="hr_mobile_console_enabled" value="0">
                <input class="form-check-input" type="checkbox" role="switch"
                       id="hr_mobile_console_enabled"
                       name="hr_mobile_console_enabled"
                       value="1"
                       <?php echo $hrMobileConsoleEnabled ? 'checked' : ''; ?>>
                <label class="form-check-label" for="hr_mobile_console_enabled">
                    <?php echo Rateb\App\Core\View::escape(__('hr_mobile_console_setting_label')); ?>
                </label>
            </div>
            <p class="text-muted small mb-3"><?php echo Rateb\App\Core\View::escape(__('hr_mobile_console_setting_help')); ?></p>
            <button type="submit" class="btn btn-primary btn-sm"><?php echo Rateb\App\Core\View::escape(__('save')); ?></button>
        </form>
    </div>
</div>
