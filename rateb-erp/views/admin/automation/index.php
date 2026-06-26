<?php
/** @var array<string,mixed> $health */
/** @var string $csrf */
$m023 = $health['migration_023'] ?? [];
$warnings = $health['cron_warnings'] ?? [];
?>
<h1><?php echo __('automation_health'); ?></h1>

<?php if (!empty($warnings)) { ?>
<div class="alert alert-warning" role="alert">
    <strong><?php echo __('deployment_warnings'); ?></strong>
    <ul class="mb-0 mt-2">
        <?php foreach ($warnings as $w) { ?>
            <li><?php echo Rateb\App\Core\View::escape((string) $w); ?></li>
        <?php } ?>
    </ul>
</div>
<?php } ?>

<div class="card mb-3">
    <div class="card-body">
        <h5><?php echo __('migration_023_status'); ?></h5>
        <?php if (!empty($m023['schema_ok'])) { ?>
            <p class="text-success mb-0"><?php echo __('migration_023_ok'); ?></p>
        <?php } else { ?>
            <p class="text-danger mb-1"><?php echo __('migration_023_fail'); ?></p>
            <?php if (!empty($m023['missing'])) { ?>
                <ul class="small mb-0">
                    <?php foreach ($m023['missing'] as $m) { ?>
                        <li><code><?php echo Rateb\App\Core\View::escape((string) $m); ?></code></li>
                    <?php } ?>
                </ul>
            <?php } ?>
        <?php } ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <h5><?php echo __('queue_health'); ?></h5>
            <p><?php echo __('pending'); ?>: <?php echo (int) ($health['queue']['pending'] ?? 0); ?></p>
            <p><?php echo __('failed'); ?>: <?php echo (int) ($health['queue']['failed'] ?? 0); ?></p>
            <p>DLQ: <?php echo (int) ($health['queue']['dead_letter'] ?? 0); ?></p>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <h5><?php echo __('backup_health'); ?></h5>
            <p><?php echo Rateb\App\Core\View::escape((string) ($health['backup']['latest'] ?? '-')); ?></p>
            <p><?php echo __('count'); ?>: <?php echo (int) ($health['backup']['count'] ?? 0); ?></p>
            <?php if (!empty($health['backup']['verify_ok'])) { ?>
                <p class="text-success small mb-0"><?php echo __('backup_verify_ok'); ?></p>
            <?php } elseif (($health['backup']['count'] ?? 0) > 0) { ?>
                <p class="text-danger small mb-0"><?php echo __('backup_verify_fail'); ?>: <?php echo Rateb\App\Core\View::escape((string) ($health['backup']['verify_error'] ?? '')); ?></p>
            <?php } ?>
            <p class="small text-muted mb-2"><?php echo __('backup_download_hint'); ?></p>
            <a href="<?php echo rateb_url('admin/backup/download?fresh=1&format=b64'); ?>" class="btn btn-sm btn-primary">
                <i class="fas fa-shield-alt"></i> <?php echo __('backup_download_safe'); ?>
            </a>
            <a href="<?php echo rateb_url('admin/backup/download?fresh=1&format=zip'); ?>" class="btn btn-sm btn-outline-primary ms-1">
                <i class="fas fa-file-archive"></i> ZIP
            </a>
            <a href="<?php echo rateb_url('admin/backup/download?format=b64'); ?>" class="btn btn-sm btn-outline-secondary ms-1">
                <i class="fas fa-history"></i> <?php echo __('backup_download_latest'); ?>
            </a>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card"><div class="card-body">
            <h5><?php echo __('workflow_health'); ?></h5>
            <p><?php echo __('pending'); ?>: <?php echo (int) ($health['workflow']['pending'] ?? 0); ?></p>
            <p><?php echo __('overdue'); ?>: <?php echo (int) ($health['workflow']['overdue'] ?? 0); ?></p>
        </div></div>
    </div>
</div>
<h2 class="mt-4"><?php echo __('cron_health'); ?></h2>
<?php if (($health['cron'] ?? []) === []) { ?>
<p class="text-warning"><?php echo __('cron_warning_missing_erp_cron'); ?></p>
<?php } ?>
<div class="rateb-table-wrap">
<table class="table table-sm rateb-table mb-0">
    <thead><tr><th>Job</th><th>Last run</th><th>Next expected</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach (($health['cron'] ?? []) as $job) { ?>
        <tr class="<?php echo ($job['status'] ?? '') === 'late' ? 'table-warning' : ''; ?>">
            <td><?php echo Rateb\App\Core\View::escape((string) ($job['job_name'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($job['last_run_at'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($job['next_expected_at'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($job['status'] ?? '')); ?></td>
        </tr>
    <?php } ?>
    </tbody>
</table>
</div>
<p><?php echo __('email_health'); ?>: <?php echo Rateb\App\Core\View::escape((string) ($health['email_health'] ?? '')); ?>
 | SMS: <?php echo Rateb\App\Core\View::escape((string) ($health['sms_health'] ?? '')); ?>
 | <?php echo __('failed_logins_24h'); ?>: <?php echo (int) ($health['failed_logins_24h'] ?? 0); ?></p>
<p>
    <a href="<?php echo rateb_url('admin/login-activity'); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('login_activity'); ?></a>
    <a href="<?php echo rateb_url('admin/queue-monitor'); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('queue_monitor'); ?></a>
</p>
