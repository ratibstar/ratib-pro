<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$esc = static fn ($v): string => View::escape((string) $v);
$locale = function_exists('rateb_locale') ? strtolower((string) rateb_locale()) : '';
$ar = str_starts_with($locale, 'ar');
$t = static function (string $en, string $arText) use ($ar): string {
    return $ar ? $arText : $en;
};
$display = is_array($display ?? null) ? $display : [];
$state = (string) ($state ?? 'unavailable');
$invoice = is_array($invoice ?? null) ? $invoice : null;
$moduleName = (string) ($display['name'] ?? $moduleName ?? $slug ?? '');
$icon = (string) ($display['icon'] ?? 'crm');
$labels = [
    'payment_pending' => $t('Payment pending', 'الدفع قيد الانتظار'),
    'paid_pending_activation' => $t('Payment received / activation pending', 'تم استلام الدفع / التفعيل قيد الانتظار'),
    'active' => $t('Active', 'مفعّل'),
    'expired' => $t('Expired', 'منتهٍ'),
    'failed' => $t('Payment failed', 'فشل الدفع'),
    'unavailable' => $t('Unavailable', 'غير متاح'),
];
$label = $labels[$state] ?? $state;
$stateIcon = match ($state) {
    'active' => 'active',
    'payment_pending' => 'pending',
    'paid_pending_activation' => 'activating',
    'failed' => 'failed',
    'expired' => 'expired',
    default => 'unavailable',
};
?>
<link rel="stylesheet" href="<?php echo $esc(rateb_asset('css/module-addon-checkout.css')); ?>">
<div class="rateb-addon">
    <div class="rateb-addon-card rateb-addon-status rateb-addon-status--<?php echo $esc($state); ?>">
        <header class="rateb-addon-header">
            <span class="rateb-addon-icon" aria-hidden="true"><?php require RATEB_ROOT . '/views/billing/partials/addon-svg.php'; ?></span>
            <div class="rateb-addon-heading">
                <p class="rateb-addon-kicker"><?php echo $esc($t('RATIB ERP Add-on', 'إضافة راتب ERP')); ?></p>
                <h1 class="rateb-addon-title"><?php echo $esc($moduleName); ?></h1>
            </div>
        </header>
        <p class="rateb-addon-state">
            <?php $icon = $stateIcon; require RATEB_ROOT . '/views/billing/partials/addon-svg.php'; ?>
            <strong><?php echo $esc($label); ?></strong>
        </p>
        <?php if ($state === 'active') { ?>
        <p class="rateb-addon-lead"><?php echo $esc($moduleName . ' ' . $t('is enabled for your company.', 'مفعّل لشركتك.')); ?></p>
        <a class="rateb-addon-cta" href="<?php echo $esc($openModuleUrl ?? '#'); ?>"><?php echo $esc($t('Open', 'فتح') . ' ' . $moduleName); ?></a>
        <?php } else { ?>
        <dl class="rateb-addon-meta">
            <div>
                <dt><?php echo $esc($t('Billing cycle', 'دورة الفوترة')); ?></dt>
                <dd><?php echo $esc($cycle !== '' ? $cycle : '—'); ?></dd>
            </div>
            <div>
                <dt><?php echo $esc($t('Invoice', 'الفاتورة')); ?></dt>
                <dd><?php echo $esc((string) ($invoice['invoice_no'] ?? '—')); ?></dd>
            </div>
            <div>
                <dt><?php echo $esc($t('Payment status', 'حالة الدفع')); ?></dt>
                <dd><?php echo $esc($paymentStatus !== '' ? $paymentStatus : '—'); ?></dd>
            </div>
        </dl>
        <?php if ($state === 'paid_pending_activation') { ?>
        <p class="rateb-addon-note"><?php echo $esc($t('Payment received. Activation is pending.', 'تم استلام الدفع. التفعيل قيد الانتظار.')); ?></p>
        <?php } ?>
        <?php if ($state === 'payment_pending' || $state === 'failed' || $state === 'unavailable') { ?>
        <a class="rateb-addon-cta" href="<?php echo $esc($checkoutUrl ?? '#'); ?>"><?php echo $esc($t('Checkout', 'إتمام الشراء')); ?></a>
        <?php } ?>
        <?php } ?>
    </div>
</div>
