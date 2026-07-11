<?php
/** Phase 16A — Accounting platform hub */
$canCreate = !empty($canCreate);
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('accounting_platform')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('accounting')), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars(__('accounting_dashboard'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>
    <p class="text-muted"><?php echo htmlspecialchars(__('accounting_platform_intro'), ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="row g-3">
        <?php
        $cards = [
            ['accounting/currencies', 'accounting_currencies', 'fa-coins'],
            ['accounting/tax-codes', 'accounting_tax_codes', 'fa-percent'],
            ['accounting/profit-centers', 'accounting_profit_centers', 'fa-chart-pie'],
            ['accounting/recurring', 'accounting_recurring', 'fa-redo'],
            ['accounting/opening-balances/create', 'accounting_opening_balances', 'fa-balance-scale'],
            ['fiscal-periods', 'fiscal_periods', 'fa-calendar-alt'],
            ['chart-of-accounts', 'chart_of_accounts', 'fa-sitemap'],
            ['journal-entries', 'journal_entries', 'fa-book'],
        ];
        foreach ($cards as [$path, $label, $icon]):
        ?>
        <div class="col-md-4 col-lg-3">
            <a class="text-decoration-none" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route($path)), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="border rounded p-3 h-100 bg-white">
                    <i class="fas <?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?> text-primary mb-2"></i>
                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars(__($label), ENT_QUOTES, 'UTF-8'); ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
