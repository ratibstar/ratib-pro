<section class="rateb-portal-section rateb-portal-payment-success">
    <div class="container">
        <h1><?php echo __('payment_success') ?: 'Payment Successful'; ?></h1>
        <p class="rateb-portal-lead"><?php echo __('payment_success_message') ?: 'Your payment has been received and your invoice has been updated.'; ?></p>
        <?php if (!empty($invoice)) { ?>
        <div class="rateb-portal-card">
            <p><strong><?php echo __('invoice_no') ?: 'Invoice'; ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) ($invoice['invoice_no'] ?? '')); ?></p>
            <p><strong><?php echo __('amount') ?: 'Amount'; ?>:</strong> <?php echo number_format((float) ($invoice['total_amount'] ?? 0), 2); ?> <?php echo Rateb\App\Core\View::escape((string) ($invoice['currency'] ?? 'SAR')); ?></p>
            <p><strong><?php echo __('status') ?: 'Status'; ?>:</strong> <?php echo Rateb\App\Core\View::escape((string) ($invoice['payment_status'] ?? $invoice['status'] ?? '')); ?></p>
        </div>
        <?php } ?>
        <p><a class="rateb-portal-btn rateb-portal-btn--primary" href="<?php echo rateb_url('site/customer/finance'); ?>"><?php echo __('back_to_finance') ?: 'Back to Finance'; ?></a></p>
    </div>
</section>
