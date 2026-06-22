<?php
/** @var array<string, string> $mailCfg */
/** @var bool $mailReady */
/** @var bool $mailPassSet */
$mailCfg = $mailCfg ?? [];
$mailReady = !empty($mailReady);
$mailPassSet = !empty($mailPassSet);
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-envelope text-primary"></i> <?php echo __('mail_settings_title'); ?></span>
        <?php if ($mailReady) { ?>
        <span class="badge bg-success"><?php echo __('mail_settings_ready'); ?></span>
        <?php } else { ?>
        <span class="badge bg-warning text-dark"><?php echo __('mail_settings_incomplete'); ?></span>
        <?php } ?>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted small"><?php echo __('mail_settings_hint'); ?></p>
        <div class="row g-2 mb-3 small">
            <div class="col-md-4"><strong><?php echo __('mail_smtp_host'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) ($mailCfg['host'] ?? '')); ?></div>
            <div class="col-md-2"><strong><?php echo __('mail_smtp_port'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) ($mailCfg['port'] ?? '')); ?></div>
            <div class="col-md-2"><strong>TLS:</strong> <?php echo Rateb\App\Core\View::escape((string) ($mailCfg['encryption'] ?? '')); ?></div>
            <div class="col-md-4"><strong><?php echo __('mail_smtp_user'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) ($mailCfg['user'] ?? '')); ?></div>
            <div class="col-md-4"><strong><?php echo __('mail_from'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) ($mailCfg['from_email'] ?? '')); ?></div>
            <div class="col-md-4"><strong><?php echo __('mail_password'); ?>:</strong>
                <?php echo $mailPassSet ? __('mail_password_set') : __('mail_password_missing'); ?>
            </div>
        </div>
        <form method="post" action="<?php echo rateb_url('admin/settings/test-mail'); ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('mail_test_to'); ?></label>
                <input class="form-control" type="email" name="test_to" required
                    value="<?php echo Rateb\App\Core\View::escape((string) ($testEmailDefault ?? 'info@rateb.sa')); ?>"
                    placeholder="info@rateb.sa">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-outline-primary" <?php echo $mailReady ? '' : 'disabled'; ?>>
                    <i class="fas fa-paper-plane"></i> <?php echo __('mail_test_send'); ?>
                </button>
            </div>
        </form>
        <?php if (!$mailReady) { ?>
        <p class="text-warning small mt-2 mb-0"><?php echo __('mail_password_env_hint'); ?></p>
        <?php } ?>
    </div>
</div>
