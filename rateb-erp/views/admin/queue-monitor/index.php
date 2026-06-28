<?php
/** @var array<int,array<string,mixed>> $items */
/** @var string $status */
/** @var string $csrf */
?>
<h1><?php echo __('queue_monitor'); ?></h1>
<p>
    <a href="<?php echo rateb_url('admin/queue-monitor?status=pending'); ?>">pending</a> |
    <a href="<?php echo rateb_url('admin/queue-monitor?status=failed'); ?>">failed</a> |
    <a href="<?php echo rateb_url('admin/queue-monitor?status=dead'); ?>">dead letter</a>
</p>
<form method="post" action="<?php echo rateb_url('admin/queue-monitor/retry'); ?>" class="mb-3">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <button type="submit" class="btn btn-sm btn-warning"><?php echo __('retry_failed'); ?></button>
</form>
<div class="rateb-table-wrap">
<table class="table table-sm rateb-table mb-0">
    <thead><tr><th><?php echo __('id'); ?></th><th><?php echo __('channel'); ?></th><th><?php echo __('to'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('attempts'); ?></th></tr></thead>
    <tbody>
    <?php foreach ($items as $row) { ?>
        <tr>
            <td><?php echo (int) ($row['id'] ?? 0); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['channel'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['recipient'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape(rateb_enum_label((string) ($row['status'] ?? ''))); ?></td>
            <td><?php echo (int) ($row['attempt_count'] ?? 0); ?></td>
        </tr>
    <?php } ?>
    </tbody>
</table>
</div>
