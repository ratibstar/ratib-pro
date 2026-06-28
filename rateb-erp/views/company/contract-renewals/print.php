<?php
/** @var array<string, mixed> $item */
$item = $item ?? [];
$approval = (string) ($item['manager_approval_raw'] ?? 'pending');
$status = (string) ($item['status'] ?? 'planned');
?>
<div class="rateb-po-print-header">
    <h1 class="h4 mb-1"><?php echo __('contract_renewals'); ?></h1>
    <div class="text-muted small"><?php echo Rateb\App\Core\View::escape((string) ($item['renewal_no'] ?? '')); ?></div>
</div>
<table class="table table-bordered table-sm mb-3">
    <tbody>
    <tr><th style="width:32%"><?php echo __('contract_no'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($item['contract_no'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('title'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($item['contract_title'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('suppliers'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($item['supplier_name'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('renewal_date'); ?></th><td><?php echo Rateb\App\Core\View::formatDate((string) ($item['renewal_date'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('new_end_date'); ?></th><td><?php echo Rateb\App\Core\View::formatDate((string) ($item['new_end_date'] ?? '—')); ?></td></tr>
    <tr><th><?php echo __('new_value'); ?></th><td><?php echo number_format((float) ($item['new_value'] ?? 0), 2); ?></td></tr>
    <tr><th><?php echo __('status'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($item['status_label'] ?? __($status))); ?></td></tr>
    <tr><th><?php echo __('manager_approval'); ?></th><td><?php echo Rateb\App\Core\View::escape((string) ($item['manager_approval_label'] ?? __('manager_approval_' . $approval))); ?></td></tr>
    <?php if (!empty($item['approved_by_name'])) { ?>
    <tr><th><?php echo __('approved_by'); ?></th><td><?php echo Rateb\App\Core\View::formatDate((string) $item['approved_by_name']); ?><?php if (!empty($item['approved_at'])) { ?> — <?php echo Rateb\App\Core\View::formatDate((string) $item['approved_at']); ?><?php } ?></td></tr>
    <?php } ?>
    </tbody>
</table>
<?php if (trim((string) ($item['notes'] ?? '')) !== '') { ?>
<p class="mb-0"><strong><?php echo __('notes'); ?>:</strong><br><?php echo nl2br(Rateb\App\Core\View::escape((string) $item['notes'])); ?></p>
<?php } ?>
