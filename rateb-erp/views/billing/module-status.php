<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$esc = static fn ($v): string => View::escape($v);
$state = (string) ($state ?? 'unavailable');
$invoice = is_array($invoice ?? null) ? $invoice : null;
$labels = [
    'payment_pending' => 'Payment pending',
    'paid_pending_activation' => 'Payment received / activation pending',
    'active' => 'Active',
    'expired' => 'Expired',
    'failed' => 'Payment failed',
    'unavailable' => 'Unavailable',
];
$label = $labels[$state] ?? $state;
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo $esc($moduleName ?? $slug); ?></div>
    <div class="rateb-card-body">
        <dl class="row">
            <dt class="col-sm-4"><?php echo $esc('Module'); ?></dt>
            <dd class="col-sm-8"><?php echo $esc($moduleName ?? ''); ?> (<code><?php echo $esc($slug ?? ''); ?></code>)</dd>
            <dt class="col-sm-4"><?php echo $esc('Billing cycle'); ?></dt>
            <dd class="col-sm-8"><?php echo $esc($cycle !== '' ? $cycle : '—'); ?></dd>
            <dt class="col-sm-4"><?php echo $esc('Invoice'); ?></dt>
            <dd class="col-sm-8"><?php echo $esc($invoice['invoice_no'] ?? '—'); ?></dd>
            <dt class="col-sm-4"><?php echo $esc('Payment status'); ?></dt>
            <dd class="col-sm-8"><?php echo $esc($paymentStatus !== '' ? $paymentStatus : '—'); ?></dd>
            <dt class="col-sm-4"><?php echo $esc('State'); ?></dt>
            <dd class="col-sm-8"><strong><?php echo $esc($label); ?></strong></dd>
        </dl>
        <?php if ($state === 'paid_pending_activation') { ?>
        <div class="alert alert-info"><?php echo $esc('Payment received / activation pending'); ?></div>
        <?php } ?>
        <?php if ($state === 'payment_pending' || $state === 'failed' || $state === 'unavailable') { ?>
        <a class="btn btn-primary" href="<?php echo $esc($checkoutUrl ?? '#'); ?>"><?php echo $esc('Checkout'); ?></a>
        <?php } ?>
    </div>
</div>
