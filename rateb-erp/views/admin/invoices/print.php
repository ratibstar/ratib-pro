<?php
/** @var array<string, mixed> $item */
/** @var array<string, mixed>|null $company */
$company = $company ?? [];
?>
<div class="rateb-po-print-header">
    <h2><?php echo Rateb\App\Core\View::escape(__('invoice_preview')); ?></h2>
    <p class="mb-0 text-muted"><?php echo Rateb\App\Core\View::escape((string) ($company['name'] ?? '')); ?></p>
</div>
<table class="table table-bordered mt-3">
    <tbody>
    <tr>
        <th><?php echo __('invoice_no'); ?></th>
        <td><?php echo Rateb\App\Core\View::escape((string) ($item['invoice_no'] ?? '')); ?></td>
        <th><?php echo __('issued_at'); ?></th>
        <td><?php echo Rateb\App\Core\View::escape((string) ($item['issued_at'] ?? '')); ?></td>
    </tr>
    <tr>
        <th><?php echo __('due_date'); ?></th>
        <td><?php echo Rateb\App\Core\View::escape((string) ($item['due_date'] ?? '')); ?></td>
        <th><?php echo __('status'); ?></th>
        <td><?php echo Rateb\App\Core\View::escape(__((string) ($item['status'] ?? 'draft'))); ?></td>
    </tr>
    </tbody>
</table>
<table class="table table-bordered mt-3 rateb-po-print-table">
    <thead>
    <tr>
        <th><?php echo __('description'); ?></th>
        <th class="text-end"><?php echo __('amount'); ?></th>
        <th class="text-end"><?php echo __('discount'); ?></th>
        <th class="text-end"><?php echo __('tax_amount'); ?></th>
        <th class="text-end"><?php echo __('total_amount'); ?></th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td><?php echo Rateb\App\Core\View::escape((string) ($item['notes'] ?? __('subscriptions'))); ?></td>
        <td class="text-end"><?php echo number_format((float) ($item['amount'] ?? 0), 2); ?></td>
        <td class="text-end"><?php echo number_format((float) ($item['discount_amount'] ?? 0), 2); ?></td>
        <td class="text-end"><?php echo number_format((float) ($item['tax_amount'] ?? 0), 2); ?></td>
        <td class="text-end"><?php echo number_format((float) ($item['total_amount'] ?? 0), 2); ?></td>
    </tr>
    </tbody>
    <tfoot>
    <tr class="rateb-po-print-total">
        <td colspan="4" class="text-end"><strong><?php echo __('total_after_tax'); ?></strong></td>
        <td class="text-end"><strong><?php echo number_format((float) ($item['total_amount'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape((string) ($item['currency'] ?? 'SAR')); ?></strong></td>
    </tr>
    </tfoot>
</table>
<?php if (!empty($item['notes'])) { ?>
<p class="mt-3"><strong><?php echo __('invoice_notes'); ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) $item['notes']); ?></p>
<?php } ?>
