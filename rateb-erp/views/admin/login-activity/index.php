<?php
/** @var array<int,array<string,mixed>> $items */
/** @var int $page */
/** @var int $total */
/** @var int $failedOnly */
?>
<h1><?php echo __('login_activity'); ?></h1>
<p>
    <a href="<?php echo rateb_url('admin/login-activity'); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('all'); ?></a>
    <a href="<?php echo rateb_url('admin/login-activity?failed=1'); ?>" class="btn btn-sm btn-outline-danger"><?php echo __('failed_logins'); ?></a>
</p>
<div class="rateb-table-wrap">
<table class="table table-sm table-striped rateb-table mb-0">
    <thead><tr><th><?php echo __('id'); ?></th><th><?php echo __('users'); ?></th><th><?php echo __('email'); ?></th><th><?php echo __('ip'); ?></th><th><?php echo __('login_ok'); ?></th><th><?php echo __('time'); ?></th></tr></thead>
    <tbody>
    <?php foreach ($items as $row) { ?>
        <tr>
            <td><?php echo (int) ($row['id'] ?? 0); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['user_name'] ?? '-')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['email'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['ip_address'] ?? '')); ?></td>
            <td><?php echo !empty($row['success']) ? '✓' : '✗'; ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['created_at'] ?? '')); ?></td>
        </tr>
    <?php } ?>
    </tbody>
</table>
</div>
