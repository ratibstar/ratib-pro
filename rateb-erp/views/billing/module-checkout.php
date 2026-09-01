<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$esc = static fn ($v): string => View::escape((string) $v);
$fmt = static function (float $n): string {
    return number_format($n, 2);
};
$locale = function_exists('rateb_locale') ? strtolower((string) rateb_locale()) : '';
$ar = str_starts_with($locale, 'ar');
$display = is_array($display ?? null) ? $display : [];
$quotes = is_array($quotes ?? null) ? $quotes : [];
$quote = is_array($quote ?? null) ? $quote : [];
$savings = is_array($savings ?? null) ? $savings : null;
$cycles = is_array($cycles ?? null) ? $cycles : [];
$moduleName = (string) ($display['name'] ?? $moduleName ?? $slug ?? '');
$description = (string) ($display['description'] ?? '');
$features = is_array($display['features'] ?? null) ? $display['features'] : [];
$promo = (string) ($display['promo_label'] ?? '');
$icon = (string) ($display['icon'] ?? 'crm');
$currency = (string) ($quote['currency'] ?? 'SAR');
$selected = (string) ($quote['cycle'] ?? 'monthly');
$t = static function (string $en, string $arText) use ($ar): string {
    return $ar ? $arText : $en;
};
?>
<link rel="stylesheet" href="<?php echo $esc(rateb_asset('css/module-addon-checkout.css')); ?>">
<div class="rateb-addon">
    <form class="rateb-addon-card" method="post" action="<?php echo $esc($action ?? ''); ?>" id="rateb-addon-form">
        <input type="hidden" name="_csrf" value="<?php echo $esc($csrf ?? ''); ?>">
        <header class="rateb-addon-header">
            <span class="rateb-addon-icon" aria-hidden="true"><?php $icon = $icon; require RATEB_ROOT . '/views/billing/partials/addon-svg.php'; ?></span>
            <div class="rateb-addon-heading">
                <p class="rateb-addon-kicker"><?php echo $esc($t('RATIB ERP Add-on', 'إضافة راتب ERP')); ?></p>
                <h1 class="rateb-addon-title"><?php echo $esc($moduleName); ?></h1>
                <?php if ($description !== '') { ?>
                <p class="rateb-addon-lead"><?php echo $esc($description); ?></p>
                <?php } ?>
            </div>
            <?php if ($promo !== '') { ?>
            <span class="rateb-addon-badge"><?php echo $esc($promo); ?></span>
            <?php } ?>
        </header>
        <?php if ($features !== []) { ?>
        <ul class="rateb-addon-features">
            <?php foreach ($features as $feature) { ?>
            <li>
                <?php $icon = 'feature'; require RATEB_ROOT . '/views/billing/partials/addon-svg.php'; ?>
                <span><?php echo $esc((string) $feature); ?></span>
            </li>
            <?php } ?>
        </ul>
        <?php } ?>
        <?php if (count($cycles) > 1) { ?>
        <fieldset class="rateb-addon-cycles">
            <legend class="visually-hidden"><?php echo $esc($t('Billing cycle', 'دورة الفوترة')); ?></legend>
            <?php foreach ($cycles as $cycle) {
                $q = $quotes[$cycle] ?? null;
                if (!is_array($q)) {
                    continue;
                }
                $isYearly = $cycle === 'yearly';
                ?>
            <label class="rateb-addon-cycle<?php echo $isYearly && $savings ? ' rateb-addon-cycle--save' : ''; ?>">
                <input type="radio" name="cycle" id="cycle-<?php echo $esc((string) $cycle); ?>" value="<?php echo $esc((string) $cycle); ?>"<?php echo $cycle === $selected ? ' checked' : ''; ?>>
                <span class="rateb-addon-cycle-body">
                    <span class="rateb-addon-cycle-icon" aria-hidden="true"><?php $icon = $isYearly ? 'yearly' : 'monthly'; require RATEB_ROOT . '/views/billing/partials/addon-svg.php'; ?></span>
                    <span class="rateb-addon-cycle-label"><?php echo $esc($isYearly ? $t('Yearly', 'سنوي') : $t('Monthly', 'شهري')); ?></span>
                    <span class="rateb-addon-cycle-price"><?php echo $esc($fmt((float) $q['unit_price'])); ?> <?php echo $esc($currency); ?></span>
                    <span class="rateb-addon-cycle-period"><?php echo $esc($isYearly ? $t('/ year', '/ سنة') : $t('/ month', '/ شهر')); ?></span>
                    <?php if ($isYearly && is_array($savings)) { ?>
                    <span class="rateb-addon-save">
                        <?php $icon = 'savings'; require RATEB_ROOT . '/views/billing/partials/addon-svg.php'; ?>
                        <?php echo $esc($t('Save', 'وفّر') . ' ' . $fmt((float) $savings['percent']) . '%'); ?>
                    </span>
                    <?php } ?>
                </span>
            </label>
            <?php } ?>
        </fieldset>
        <?php } else { ?>
        <input type="hidden" name="cycle" value="<?php echo $esc($selected); ?>">
        <?php } ?>
        <?php foreach ($quotes as $cycle => $q) {
            if (!is_array($q)) {
                continue;
            } ?>
        <dl class="rateb-addon-totals<?php echo (string) $cycle === $selected ? ' is-selected' : ''; ?>" data-cycle="<?php echo $esc((string) $cycle); ?>">
            <div>
                <dt><?php echo $esc($t('Price', 'السعر')); ?></dt>
                <dd><?php echo $esc($fmt((float) $q['amount'])); ?> <?php echo $esc($q['currency'] ?? $currency); ?></dd>
            </div>
            <div>
                <dt><?php echo $esc($t('VAT', 'ضريبة القيمة المضافة')); ?> (<?php echo $esc((string) ($q['tax_rate'] ?? 15)); ?>%)</dt>
                <dd><?php echo $esc($fmt((float) $q['tax_amount'])); ?> <?php echo $esc($q['currency'] ?? $currency); ?></dd>
            </div>
            <div class="rateb-addon-total">
                <dt><?php echo $esc($t('Total', 'الإجمالي')); ?></dt>
                <dd><?php echo $esc($fmt((float) $q['total_amount'])); ?> <?php echo $esc($q['currency'] ?? $currency); ?></dd>
            </div>
        </dl>
        <?php } ?>
        <button type="submit" class="rateb-addon-cta"><?php echo $esc($t('Subscribe to', 'اشترك في') . ' ' . $moduleName); ?></button>
        <p class="rateb-addon-secure">
            <?php $icon = 'secure'; require RATEB_ROOT . '/views/billing/partials/addon-svg.php'; ?>
            <span><?php echo $esc($t('Secure payment via Moyasar', 'دفع آمن عبر ميسر')); ?></span>
        </p>
        <a class="rateb-addon-status-link" href="<?php echo $esc($statusUrl ?? '#'); ?>"><?php echo $esc($t('View status', 'عرض الحالة')); ?></a>
    </form>
</div>
