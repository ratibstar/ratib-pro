<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$esc = static fn ($v): string => View::escape($v);
$fmt = static function (float $n): string {
    return number_format($n, 2);
};
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo $esc($moduleName ?? $slug); ?></div>
    <div class="rateb-card-body">
        <p class="text-muted"><?php echo $esc('RATIB ERP module add-on'); ?> — <code><?php echo $esc($slug ?? ''); ?></code></p>
        <form method="post" action="<?php echo $esc($action ?? ''); ?>">
            <input type="hidden" name="_csrf" value="<?php echo $esc($csrf ?? ''); ?>">
            <?php
            $cycles = $cycles ?? [];
            $quotes = $quotes ?? [];
            if (count($cycles) > 1) { ?>
            <div class="mb-3">
                <label class="form-label"><?php echo $esc('Billing cycle'); ?></label>
                <?php foreach ($cycles as $cycle) {
                    $q = $quotes[$cycle] ?? null;
                    if (!is_array($q)) {
                        continue;
                    } ?>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="cycle" id="cycle-<?php echo $esc($cycle); ?>" value="<?php echo $esc($cycle); ?>"<?php echo $cycle === ($quote['cycle'] ?? 'monthly') ? ' checked' : ''; ?>>
                    <label class="form-check-label" for="cycle-<?php echo $esc($cycle); ?>">
                        <?php echo $esc(ucfirst((string) $cycle)); ?>
                        — <?php echo $esc($fmt((float) $q['unit_price'])); ?> <?php echo $esc($q['currency'] ?? 'SAR'); ?>
                    </label>
                </div>
                <?php } ?>
            </div>
            <?php } else { ?>
            <input type="hidden" name="cycle" value="<?php echo $esc($quote['cycle'] ?? 'monthly'); ?>">
            <p><?php echo $esc(ucfirst((string) ($quote['cycle'] ?? 'monthly'))); ?></p>
            <?php } ?>
            <?php $q = $quote ?? []; ?>
            <dl class="row mb-0">
                <dt class="col-sm-4"><?php echo $esc('Price'); ?></dt>
                <dd class="col-sm-8"><?php echo $esc($fmt((float) ($q['amount'] ?? 0))); ?> <?php echo $esc($q['currency'] ?? 'SAR'); ?></dd>
                <dt class="col-sm-4"><?php echo $esc('VAT'); ?> (<?php echo $esc((string) ($q['tax_rate'] ?? 15)); ?>%)</dt>
                <dd class="col-sm-8"><?php echo $esc($fmt((float) ($q['tax_amount'] ?? 0))); ?> <?php echo $esc($q['currency'] ?? 'SAR'); ?></dd>
                <dt class="col-sm-4"><?php echo $esc('Total'); ?></dt>
                <dd class="col-sm-8 fw-bold"><?php echo $esc($fmt((float) ($q['total_amount'] ?? 0))); ?> <?php echo $esc($q['currency'] ?? 'SAR'); ?></dd>
            </dl>
            <button type="submit" class="btn btn-primary mt-3"><?php echo $esc('Purchase'); ?></button>
            <a class="btn btn-link mt-3" href="<?php echo $esc($statusUrl ?? '#'); ?>"><?php echo $esc('Status'); ?></a>
        </form>
    </div>
</div>
