<?php
$mailKeys = ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_user', 'smtp_pass', 'smtp_from_email', 'smtp_from_name'];
Rateb\App\Core\View::partial('admin/settings/mail-card', [
    'mailCfg' => $mailCfg ?? [],
    'mailPassSet' => !empty($mailPassSet),
    'mailReady' => !empty($mailReady),
    'csrf' => $csrf ?? '',
    'testEmailDefault' => $testEmailDefault ?? 'info@rateb.sa',
]); ?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('settings'); ?> — <?php echo __('all'); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url('admin/settings'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php foreach ($items ?? [] as $item) {
                if (in_array((string) ($item['setting_key'] ?? ''), $mailKeys, true)) {
                    continue;
                } ?>
            <div class="row g-2 mb-2 align-items-center">
                <div class="col-md-4"><input class="form-control" name="setting_key[]" value="<?php echo Rateb\App\Core\View::escape($item['setting_key']); ?>" readonly></div>
                <div class="col-md-8"><input class="form-control" name="setting_value[]" value="<?php echo Rateb\App\Core\View::escape($item['setting_value']); ?>" <?php echo ($item['setting_key'] ?? '') === 'smtp_pass' ? 'type="password" autocomplete="new-password"' : ''; ?>></div>
            </div>
            <?php } ?>
            <button type="submit" class="btn btn-primary mt-3"><?php echo __('save'); ?></button>
        </form>
    </div>
</div>
