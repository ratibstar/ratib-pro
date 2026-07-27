<?php
/** @var array<string, mixed> $data */
/** @var string $csrf */
/** @var ?string $testTo */
$data = $data ?? [];
$cfg = (array) ($data['config'] ?? []);
$sources = (array) ($data['sources'] ?? []);
$smtp = (array) ($data['smtp'] ?? []);
$queue = (array) ($data['queue'] ?? []);
$cron = (array) ($data['cron'] ?? []);
$errors = (array) ($data['errors'] ?? []);
$test = is_array($data['test'] ?? null) ? $data['test'] : null;
$overall = (array) ($data['overall'] ?? []);
$feature = (array) ($data['feature_flag'] ?? []);
?>
<h1><i class="fas fa-stethoscope text-primary"></i> <?php echo __('email_diagnostics_title'); ?></h1>
<p class="text-muted small"><?php echo __('email_diagnostics_read_only'); ?></p>

<?php if (!empty($feature['flag_on'])) { ?>
<div class="alert alert-info small py-2"><?php echo __('email_diagnostics_flag_enabled'); ?></div>
<?php } ?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <span><?php echo __('email_diagnostics_overall'); ?></span>
        <?php if (($overall['status'] ?? '') === 'PASS') { ?>
            <span class="badge bg-success">PASS</span>
        <?php } else { ?>
            <span class="badge bg-danger">FAIL</span>
        <?php } ?>
    </div>
    <div class="rateb-card-body">
        <?php $checks = (array) ($overall['checks'] ?? []); ?>
        <ul class="list-unstyled small mb-0">
            <li><i class="fas <?php echo !empty($checks['config_ready']) ? 'fa-check text-success' : 'fa-xmark text-danger'; ?>"></i> <?php echo __('email_diagnostics_check_config'); ?></li>
            <li><i class="fas <?php echo !empty($checks['smtp_connect']) ? 'fa-check text-success' : 'fa-xmark text-danger'; ?>"></i> <?php echo __('email_diagnostics_check_connect'); ?></li>
            <li><i class="fas <?php echo !empty($checks['smtp_auth']) ? 'fa-check text-success' : 'fa-xmark text-danger'; ?>"></i> <?php echo __('email_diagnostics_check_auth'); ?></li>
            <li><i class="fas <?php echo !empty($checks['cron_healthy']) ? 'fa-check text-success' : 'fa-xmark text-danger'; ?>"></i> <?php echo __('email_diagnostics_check_cron'); ?></li>
            <li><i class="fas <?php echo !empty($checks['queue_healthy']) ? 'fa-check text-success' : 'fa-xmark text-danger'; ?>"></i> <?php echo __('email_diagnostics_check_queue'); ?></li>
            <?php if ($test !== null) { ?>
            <li><i class="fas <?php echo !empty($checks['test_send']) ? 'fa-check text-success' : 'fa-xmark text-danger'; ?>"></i> <?php echo __('email_diagnostics_check_test'); ?></li>
            <?php } ?>
        </ul>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('email_diagnostics_config'); ?></div>
            <div class="rateb-card-body small">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td><?php echo __('mail_smtp_host'); ?></td><td><code><?php echo Rateb\App\Core\View::escape((string) ($cfg['host'] ?? '')); ?></code></td></tr>
                        <tr><td><?php echo __('mail_smtp_port'); ?></td><td><code><?php echo (int) ($cfg['port'] ?? 0); ?></code></td></tr>
                        <tr><td><?php echo __('tls_ssl'); ?></td><td><code><?php echo Rateb\App\Core\View::escape((string) ($cfg['encryption'] ?? '')); ?></code></td></tr>
                        <tr><td><?php echo __('mail_smtp_user'); ?></td><td><code><?php echo Rateb\App\Core\View::escape((string) ($cfg['user'] ?? '')); ?></code></td></tr>
                        <tr><td><?php echo __('mail_from'); ?></td><td><code><?php echo Rateb\App\Core\View::escape((string) ($cfg['from_email'] ?? '')); ?></code></td></tr>
                        <tr><td><?php echo __('mail_from_name'); ?></td><td><code><?php echo Rateb\App\Core\View::escape((string) ($cfg['from_name'] ?? '')); ?></code></td></tr>
                        <tr><td><?php echo __('email_diagnostics_password_loaded'); ?></td><td><strong><?php echo !empty($cfg['pass']) ? __('yes') : __('no'); ?></strong></td></tr>
                    </tbody>
                </table>
                <div class="mt-2 text-muted"><?php echo __('email_diagnostics_source'); ?></div>
                <table class="table table-sm mt-1 mb-0">
                    <tbody>
                        <?php foreach ($sources as $key => $src) { ?>
                        <tr><td><?php echo Rateb\App\Core\View::escape($key); ?></td><td><span class="badge bg-secondary"><?php echo Rateb\App\Core\View::escape((string) $src); ?></span></td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('email_diagnostics_smtp'); ?></div>
            <div class="rateb-card-body small">
                <?php if (!empty($smtp['error'])) { ?>
                    <div class="alert alert-danger small py-2"><?php echo Rateb\App\Core\View::escape((string) $smtp['error']); ?></div>
                <?php } ?>
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td><?php echo __('email_diagnostics_dns'); ?></td><td><?php echo !empty($smtp['dns']['ok']) ? '<span class="text-success">' . __('yes') . '</span>' : '<span class="text-danger">' . __('no') . '</span>'; ?></td></tr>
                        <tr><td><?php echo __('email_diagnostics_tcp'); ?></td><td><?php echo !empty($smtp['connect']['ok']) ? '<span class="text-success">' . __('yes') . '</span>' : '<span class="text-danger">' . __('no') . '</span>'; ?></td></tr>
                        <tr><td><?php echo __('email_diagnostics_ehlo'); ?></td><td><code><?php echo Rateb\App\Core\View::escape((string) ($smtp['ehlo']['code'] ?? '')); ?></code></td></tr>
                        <?php if ((string) ($cfg['encryption'] ?? '') === 'tls') { ?>
                        <tr><td>STARTTLS</td><td><?php echo !empty($smtp['starttls']['ok']) ? '<span class="text-success">' . __('yes') . '</span>' : '<span class="text-danger">' . __('no') . '</span>'; ?> <code><?php echo Rateb\App\Core\View::escape((string) ($smtp['starttls']['code'] ?? '')); ?></code></td></tr>
                        <?php } ?>
                        <tr><td><?php echo __('email_diagnostics_auth'); ?></td>
                            <td>
                                <?php if (!empty($smtp['auth_attempted'])) { ?>
                                    <?php echo !empty($smtp['auth']['ok']) ? '<span class="text-success">' . __('yes') . '</span>' : '<span class="text-danger">' . __('no') . '</span>'; ?>
                                    <code><?php echo Rateb\App\Core\View::escape((string) ($smtp['auth']['code'] ?? '')); ?></code>
                                <?php } else { ?>
                                    <span class="text-muted"><?php echo __('email_diagnostics_auth_skipped'); ?></span>
                                <?php } ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <?php if (!empty($smtp['auth']['response'])) { ?>
                    <div class="mt-2 text-muted small"><?php echo __('email_diagnostics_auth_response'); ?><br><code class="d-block text-break"><?php echo Rateb\App\Core\View::escape((string) $smtp['auth']['response']); ?></code></div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('email_diagnostics_queue'); ?></div>
            <div class="rateb-card-body small">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td><?php echo __('email_diagnostics_pending'); ?></td><td><strong><?php echo (int) ($queue['pending'] ?? 0); ?></strong></td></tr>
                        <tr><td><?php echo __('email_diagnostics_failed'); ?></td><td><strong><?php echo (int) ($queue['failed'] ?? 0); ?></strong></td></tr>
                        <tr><td><?php echo __('email_diagnostics_sent_today'); ?></td><td><strong><?php echo (int) ($queue['sent_today'] ?? 0); ?></strong></td></tr>
                        <tr><td><?php echo __('email_diagnostics_dead_letter'); ?></td><td><strong><?php echo (int) ($queue['dead_letter'] ?? 0); ?></strong></td></tr>
                        <tr><td><?php echo __('email_diagnostics_oldest_pending'); ?></td><td><?php echo Rateb\App\Core\View::escape((string) ($queue['oldest_pending'] ?? '—')); ?></td></tr>
                    </tbody>
                </table>
                <?php if (!empty($queue['last_failed'])) { ?>
                    <div class="mt-2 border-top pt-2">
                        <div class="text-muted"><?php echo __('email_diagnostics_last_failed'); ?></div>
                        <code class="small d-block">#<?php echo (int) ($queue['last_failed']['id'] ?? 0); ?> <?php echo Rateb\App\Core\View::escape((string) ($queue['last_failed']['recipient'] ?? '')); ?></code>
                        <div class="small text-truncate" title="<?php echo Rateb\App\Core\View::escape((string) ($queue['last_failed']['subject'] ?? '')); ?>"><?php echo Rateb\App\Core\View::escape((string) ($queue['last_failed']['subject'] ?? '')); ?></div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header d-flex justify-content-between align-items-center">
                <span><?php echo __('email_diagnostics_cron'); ?></span>
                <?php if (!empty($cron['healthy'])) { ?>
                    <span class="badge bg-success"><?php echo __('yes'); ?></span>
                <?php } else { ?>
                    <span class="badge bg-danger"><?php echo __('no'); ?></span>
                <?php } ?>
            </div>
            <div class="rateb-card-body small">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr><td><?php echo __('email_diagnostics_last_run'); ?></td><td><?php echo Rateb\App\Core\View::escape((string) ($cron['last_run'] ?? '—')); ?></td></tr>
                        <tr><td><?php echo __('email_diagnostics_next_expected'); ?></td><td><?php echo Rateb\App\Core\View::escape((string) ($cron['next_expected'] ?? '—')); ?></td></tr>
                        <?php if (isset($cron['delayed_minutes']) && (int) $cron['delayed_minutes'] > 0) { ?>
                        <tr><td><?php echo __('email_diagnostics_delayed'); ?></td><td><span class="text-danger"><?php echo (int) $cron['delayed_minutes']; ?> min</span></td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('email_diagnostics_test_email'); ?></div>
            <div class="rateb-card-body">
                <form method="post" action="<?php echo rateb_url('admin/email-diagnostics/test'); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <div class="mb-2">
                        <label class="form-label small"><?php echo __('mail_test_to'); ?></label>
                        <input class="form-control form-control-sm" type="email" name="test_to" value="<?php echo Rateb\App\Core\View::escape((string) ($testTo ?? 'info@rateb.sa')); ?>" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo __('email_diagnostics_send_test'); ?></button>
                </form>
                <?php if ($test !== null) { ?>
                    <div class="mt-2 small alert <?php echo ($test['level'] ?? 'error') === 'success' ? 'alert-success' : 'alert-warning'; ?> py-2">
                        <?php echo Rateb\App\Core\View::escape((string) ($test['message'] ?? '')); ?>
                    </div>
                    <?php if (!empty($test['detail'])) { ?>
                        <div class="small text-muted"><code><?php echo Rateb\App\Core\View::escape(json_encode($test['detail'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></code></div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="rateb-card mt-3">
    <div class="rateb-card-header"><?php echo __('email_diagnostics_errors'); ?> (<?php echo count($errors); ?>)</div>
    <div class="rateb-card-body small">
        <?php if ($errors === []) { ?>
            <p class="text-muted mb-0"><?php echo __('email_diagnostics_no_errors'); ?></p>
        <?php } else { ?>
            <div class="rateb-table-wrap">
                <table class="table table-sm rateb-table mb-0">
                    <thead><tr><th><?php echo __('time'); ?></th><th><?php echo __('level'); ?></th><th><?php echo __('message'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($errors as $err) { ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($err['time'] ?? '')); ?></td>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($err['level'] ?? '')); ?></td>
                            <td>
                                <?php echo Rateb\App\Core\View::escape((string) ($err['message'] ?? '')); ?>
                                <?php if (!empty($err['context'])) { ?>
                                    <br><code class="text-muted small"><?php echo Rateb\App\Core\View::escape(json_encode($err['context'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></code>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
