<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => $accountingActive ?? 'admin']); ?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center">
        <h2 class="h5 mb-0"><?php echo __('failed_payments') ?: 'Failed Payments'; ?></h2>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo rateb_url('admin/payment-gateways/transactions'); ?>"><?php echo __('payment_transactions') ?: 'All Transactions'; ?></a>
    </div>
    <div class="table-responsive">
        <table class="table table-striped mb-0">
            <thead>
            <tr>
                <th>ID</th>
                <th><?php echo __('invoice_no') ?: 'Invoice'; ?></th>
                <th><?php echo __('amount') ?: 'Amount'; ?></th>
                <th><?php echo __('status') ?: 'Status'; ?></th>
                <th><?php echo __('error') ?: 'Error'; ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($transactions ?? [] as $tx) { ?>
            <tr>
                <td><?php echo (int) ($tx['id'] ?? 0); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($tx['invoice_no'] ?? $tx['invoice_id'] ?? '')); ?></td>
                <td><?php echo number_format((float) ($tx['amount'] ?? 0), 2); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($tx['status'] ?? '')); ?></td>
                <td class="small"><?php echo Rateb\App\Core\View::escape((string) ($tx['error_message'] ?? $tx['error_code'] ?? '')); ?></td>
                <td>
                    <form method="post" action="<?php echo rateb_url('admin/payment-gateways/retry'); ?>" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape((string) ($csrf ?? '')); ?>">
                        <input type="hidden" name="transaction_id" value="<?php echo (int) ($tx['id'] ?? 0); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo __('retry') ?: 'Retry'; ?></button>
                    </form>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
