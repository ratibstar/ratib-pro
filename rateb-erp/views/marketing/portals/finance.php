<section class="rateb-portal-section">
    <div class="container">
        <h1><?php echo __('finance') ?: 'Finance'; ?></h1>
        <p class="rateb-portal-lead"><?php echo __('outstanding_balance') ?: 'Outstanding balance'; ?>: <strong><?php echo number_format((float) ($balance ?? $outstanding ?? 0), 2); ?></strong></p>
        <h2><?php echo __('invoices') ?: 'Invoices'; ?></h2>
        <table class="rateb-portal-table">
            <thead><tr><th><?php echo __('invoice_no') ?: 'Invoice'; ?></th><th><?php echo __('amount') ?: 'Amount'; ?></th><th><?php echo __('status') ?: 'Status'; ?></th><th><?php echo __('due_date') ?: 'Due'; ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach ($invoices ?? [] as $inv) { ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape((string) ($inv['invoice_no'] ?? $inv['id'] ?? '')); ?></td>
                <td><?php echo number_format((float) ($inv['total_amount'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape((string) ($inv['currency'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($inv['payment_status'] ?? $inv['status'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($inv['due_date'] ?? '')); ?></td>
                <td>
                    <?php if (!empty($inv['document_path']) && ($portalType ?? '') === 'customer') { ?>
                    <a class="rateb-portal-btn rateb-portal-btn--ghost" href="<?php echo rateb_url('site/customer/finance/download?id=' . (int) ($inv['id'] ?? 0)); ?>"><?php echo __('download') ?: 'PDF'; ?></a>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
        <h2><?php echo __('payments') ?: 'Payments'; ?></h2>
        <table class="rateb-portal-table">
            <thead><tr><th>ID</th><th><?php echo __('amount') ?: 'Amount'; ?></th><th><?php echo __('status') ?: 'Status'; ?></th><th><?php echo __('date') ?: 'Date'; ?></th></tr></thead>
            <tbody>
            <?php foreach ($payments ?? [] as $pay) { ?>
            <tr>
                <td><?php echo (int) ($pay['id'] ?? 0); ?></td>
                <td><?php echo number_format((float) ($pay['amount'] ?? 0), 2); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($pay['status'] ?? '')); ?></td>
                <td><?php echo Rateb\App\Core\View::escape((string) ($pay['paid_at'] ?? $pay['created_at'] ?? '')); ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</section>
