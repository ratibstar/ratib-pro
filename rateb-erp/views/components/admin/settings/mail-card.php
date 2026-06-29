<?php
/** @var array<string, string> $mailCfg */
/** @var bool $mailReady */
/** @var bool $mailPassSet */
$mailCfg = $mailCfg ?? [];
$mailReady = !empty($mailReady);
$mailPassSet = !empty($mailPassSet);
/** @var bool $mailLocalhost */
$mailLocalhost = !empty($mailLocalhost);
/** @var bool $mailRelay */
$mailRelay = !empty($mailRelay);
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
        <p class="text-muted small mb-2"><?php echo __('mail_settings_hint'); ?></p>
        <p class="text-muted small mb-3"><?php echo __('mail_settings_ready_where'); ?></p>
        <form method="post" action="<?php echo rateb_url('admin/settings/save-mail'); ?>" class="row g-2 mb-3">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-4">
                <label class="form-label small"><?php echo __('mail_smtp_host'); ?></label>
                <input class="form-control form-control-sm" name="smtp_host" value="<?php echo Rateb\App\Core\View::escape((string) ($mailCfg['host'] ?? 'localhost')); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small"><?php echo __('mail_smtp_port'); ?></label>
                <input class="form-control form-control-sm" name="smtp_port" value="<?php echo Rateb\App\Core\View::escape((string) ($mailCfg['port'] ?? '587')); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small"><?php echo __('tls_ssl'); ?></label>
                <select class="form-select form-select-sm" name="smtp_encryption">
                    <?php foreach (['tls', 'ssl', 'none'] as $enc) { ?>
                    <option value="<?php echo $enc; ?>" <?php echo (($mailCfg['encryption'] ?? 'tls') === $enc) ? 'selected' : ''; ?>><?php echo $enc; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small"><?php echo __('mail_smtp_user'); ?></label>
                <input class="form-control form-control-sm" name="smtp_user" value="<?php echo Rateb\App\Core\View::escape((string) ($mailCfg['user'] ?? 'info@rateb.sa')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small"><?php echo __('mail_from'); ?></label>
                <input class="form-control form-control-sm" name="smtp_from_email" value="<?php echo Rateb\App\Core\View::escape((string) ($mailCfg['from_email'] ?? 'info@rateb.sa')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small"><?php echo __('mail_password'); ?></label>
                <input class="form-control form-control-sm" type="password" name="smtp_pass" autocomplete="new-password"
                    placeholder="<?php echo $mailPassSet ? __('mail_password_set') : __('mail_password_missing'); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small"><?php echo __('mail_from_name'); ?></label>
                <input class="form-control form-control-sm" name="smtp_from_name" value="<?php echo Rateb\App\Core\View::escape((string) ($mailCfg['from_name'] ?? 'Rateb ERP')); ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> <?php echo __('mail_save_settings'); ?></button>
            </div>
        </form>
        <?php if ($mailRelay) { ?>
        <p class="text-success small mb-2"><?php echo __('mail_relay_active', ['host' => Rateb\App\Core\View::escape((string) ($mailCfg['host'] ?? ''))]); ?></p>
        <?php } elseif ($mailLocalhost) { ?>
        <p class="text-muted small mb-2"><?php echo __('mail_localhost_warning'); ?></p>
        <div class="alert alert-warning small py-2 mb-2"><?php echo __('mail_relay_steps'); ?></div>
        <?php } ?>
        <?php Rateb\App\Core\View::partial('admin/mail-dns-panel', ['mailDns' => $mailDns ?? null]); ?>
        <form method="post" action="<?php echo rateb_url('admin/settings/test-mail'); ?>" class="row g-2 align-items-end border-top pt-3">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('mail_test_to'); ?></label>
                <input class="form-control" type="text" name="test_to" required inputmode="email" autocomplete="email"
                    value="<?php echo Rateb\App\Core\View::escape((string) ($testEmailDefault ?? 'info@rateb.sa')); ?>"
                    placeholder="supplier@example.com">
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-outline-primary" <?php echo $mailReady ? '' : 'disabled'; ?>>
                    <i class="fas fa-paper-plane"></i> <?php echo __('mail_test_send'); ?>
                </button>
            </div>
        </form>
        <?php if (!$mailReady) { ?>
        <p class="text-warning small mt-2 mb-0"><?php echo __('mail_password_env_hint'); ?></p>
        <?php } else { ?>
        <p class="text-muted small mt-2 mb-0"><?php echo __('mail_check_spam_hint'); ?></p>
        <p class="text-muted small mb-0"><?php echo __('mail_webmail_sent_note'); ?></p>
        <p class="text-muted small mb-0"><?php echo __('mail_external_dns_hint'); ?></p>
        <?php } ?>
    </div>
</div>
