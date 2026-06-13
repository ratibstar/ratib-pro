<?php
/** @var array<string,mixed> $health */
/** @var string $csrf */
?>
<h1><?php echo __('automation_health'); ?></h1>
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
<table class="table table-sm">
    <thead><tr><th>Job</th><th>Last run</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach (($health['cron'] ?? []) as $job) { ?>
        <tr>
            <td><?php echo Rateb\App\Core\View::escape((string) ($job['job_name'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($job['last_run_at'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($job['status'] ?? '')); ?></td>
        </tr>
    <?php } ?>
    </tbody>
</table>
<p><?php echo __('email_health'); ?>: <?php echo Rateb\App\Core\View::escape((string) ($health['email_health'] ?? '')); ?>
 | SMS: <?php echo Rateb\App\Core\View::escape((string) ($health['sms_health'] ?? '')); ?>
 | <?php echo __('failed_logins_24h'); ?>: <?php echo (int) ($health['failed_logins_24h'] ?? 0); ?></p>
<p>
    <a href="<?php echo rateb_url('admin/login-activity'); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('login_activity'); ?></a>
    <a href="<?php echo rateb_url('admin/queue-monitor'); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('queue_monitor'); ?></a>
</p>
