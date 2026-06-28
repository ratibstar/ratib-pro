<?php
/** @var array<string, mixed> $item */
$item = $item ?? [];
$st = (string) ($item['comm_status'] ?? 'new');
?>
<div class="rateb-po-print-header">
    <h1 class="h4 mb-1"><?php echo __('supplier_comms'); ?></h1>
    <div class="text-muted small"><?php echo Rateb\App\Core\View::escape((string) ($item['subject'] ?? '')); ?></div>
</div>
<table class="table table-bordered table-sm mb-3">
    <tbody>
    <tr><th style="width:30%"><?php echo __('comm_date'); ?></th><td><?php echo Rateb\App\Core\View::formatDate((string) ($item['comm_date'] ?? '')); ?> <?php echo Rateb\App\Core\View::formatDate(substr((string) ($item['comm_time'] ?? ''), 0, 5)); ?></td></tr>
    <tr><th><?php echo __('comm_channel'); ?></th><td><?php echo Rateb\App\Core\View::escape(__('comm_channel_' . (string) ($item['channel'] ?? ''))); ?></td></tr>
    <tr><th><?php echo __('comm_status'); ?></th><td><?php echo Rateb\App\Core\View::escape(__('comm_status_' . $st)); ?></td></tr>
    <tr><th><?php echo __('comm_responsible'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($item['responsible_name'] ?? '')); ?></td></tr>
    <tr><th><?php echo __('comm_supplier_contact'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($item['supplier_contact'] ?? '')); ?></td></tr>
    <tr><th><?php echo __('comm_supplier_phone'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($item['supplier_phone'] ?? '')); ?></td></tr>
    <tr><th><?php echo __('comm_supplier_email'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($item['supplier_email'] ?? '')); ?></td></tr>
    <tr><th><?php echo __('follow_up_date'); ?></th><td><?php echo Rateb\App\Core\View::formatDate((string) ($item['follow_up_date'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('follow_up_priority'); ?></th><td><?php echo Rateb\App\Core\View::escape(__('comm_priority_' . (string) ($item['follow_up_priority'] ?? 'medium'))); ?></td></tr>
    </tbody>
</table>
<?php if (!empty($item['details'])) { ?>
<p><strong><?php echo __('comm_details'); ?>:</strong><br><?php echo nl2br(Rateb\App\Core\View::escape((string) $item['details'])); ?></p>
<?php } ?>
<p><strong><?php echo __('comm_message'); ?>:</strong></p>
<div class="border p-3 mb-0"><?php echo nl2br(Rateb\App\Core\View::escape((string) ($item['body'] ?? ''))); ?></div>
