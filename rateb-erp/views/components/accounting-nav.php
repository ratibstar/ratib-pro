<?php
/** @var string $accountingActive admin|company (legacy; company ops use admin/ops paths) */
$accountingActive = $accountingActive ?? 'admin';

$tabs = [
    ['path' => rateb_app_route('accounting'), 'label' => __('accounting_overview'), 'match' => [rateb_app_route('accounting')]],
    ['path' => rateb_app_route('chart-of-accounts'), 'label' => __('chart_of_accounts'), 'match' => [rateb_app_route('chart-of-accounts')]],
    ['path' => rateb_app_route('journal-entries'), 'label' => __('journal_entries'), 'match' => [rateb_app_route('journal-entries')]],
    ['path' => rateb_app_route('accounting/entry-approval'), 'label' => __('entry_approval'), 'match' => [rateb_app_route('accounting/entry-approval')]],
    ['path' => rateb_app_route('accounting/accounts-payable'), 'label' => __('accounts_payable'), 'match' => [rateb_app_route('accounting/accounts-payable')]],
    ['path' => rateb_app_route('accounting/supplier-payments'), 'label' => __('supplier_payments'), 'match' => [rateb_app_route('accounting/supplier-payments')]],
    ['path' => rateb_app_route('accounting/accounts-receivable'), 'label' => __('accounts_receivable'), 'match' => [rateb_app_route('accounting/accounts-receivable')]],
    ['path' => rateb_app_route('accounting/profit-loss'), 'label' => __('profit_loss'), 'match' => [rateb_app_route('accounting/profit-loss')]],
    ['path' => rateb_app_route('accounting/balance-sheet'), 'label' => __('balance_sheet'), 'match' => [rateb_app_route('accounting/balance-sheet')]],
    ['path' => rateb_app_route('accounting/vat-report'), 'label' => __('vat_report'), 'match' => [rateb_app_route('accounting/vat-report')]],
    ['path' => rateb_app_route('cash-vouchers'), 'label' => __('cash_vouchers'), 'match' => [rateb_app_route('cash-vouchers')]],
    ['path' => rateb_app_route('fiscal-periods'), 'label' => __('fiscal_periods'), 'match' => [rateb_app_route('fiscal-periods')]],
    ['path' => rateb_app_route('cost-centers'), 'label' => __('cost_centers'), 'match' => [rateb_app_route('cost-centers')]],
    ['path' => rateb_app_route('accounting/cost-center-report'), 'label' => __('cost_center_report'), 'match' => [rateb_app_route('accounting/cost-center-report')]],
    ['path' => rateb_app_route('accounting/zatca-settings'), 'label' => __('zatca_settings'), 'match' => [rateb_app_route('accounting/zatca-settings')]],
    ['path' => rateb_app_route('bank-accounts'), 'label' => __('bank_accounts'), 'match' => [rateb_app_route('bank-accounts')]],
    ['path' => rateb_app_route('accounting/bank-reconciliation'), 'label' => __('bank_reconciliation'), 'match' => [rateb_app_route('accounting/bank-reconciliation')]],
    ['path' => rateb_app_route('accounting/budget-report'), 'label' => __('budget_report'), 'match' => [rateb_app_route('accounting/budget-report')]],
    ['path' => rateb_app_route('accounting/cfo-dashboard'), 'label' => __('cfo_dashboard'), 'match' => [rateb_app_route('accounting/cfo-dashboard')]],
];
if ($accountingActive === 'admin' || rateb_is_super_admin()) {
    $tabs[] = ['path' => 'admin/invoices', 'label' => __('invoices'), 'match' => ['admin/invoices']];
    $tabs[] = ['path' => 'admin/payments', 'label' => __('payments'), 'match' => ['admin/payments']];
    $tabs[] = ['path' => 'admin/subscriptions', 'label' => __('subscriptions'), 'match' => ['admin/subscriptions']];
}

$route = defined('RATEB_CP_ROUTE') ? (string) RATEB_CP_ROUTE : rateb_current_public_path('admin/ops/accounting');

$isActive = static function (array $tab) use ($route): bool {
    foreach ($tab['match'] as $m) {
        if ($route === $m || strpos($route, $m . '/') === 0) {
            return true;
        }
    }
    return false;
};
?>
<?php if (rateb_is_super_admin()) {
    Rateb\App\Core\View::partial('ops-company-select', [
        'companies' => (new \Rateb\App\Models\Company())->all(200, 0),
        'selectedCompanyId' => rateb_resolve_ops_company_id(),
    ]);
} ?>
<nav class="rateb-accounting-nav mb-4" aria-label="<?php echo __('accounting'); ?>">
    <div class="rateb-accounting-nav-brand">
        <i class="fas fa-calculator"></i>
        <span><?php echo __('accounting_module'); ?></span>
    </div>
    <div class="rateb-accounting-nav-tabs">
        <?php foreach ($tabs as $tab) {
            if (!rateb_is_super_admin() && $accountingActive !== 'admin') {
                $pathKey = str_replace('admin/ops/', '', $tab['path']);
                $pathKey = preg_replace('#^admin/#', '', $pathKey);
                if (strpos($pathKey, 'accounting/') === 0) {
                    $pathKey = substr($pathKey, strlen('accounting/'));
                }
                if (!rateb_can_view_entity($pathKey)) {
                    continue;
                }
            }
            $active = $isActive($tab) ? ' active' : '';
            ?>
        <a href="<?php echo rateb_url_with_ops_company($tab['path']); ?>" class="rateb-accounting-tab<?php echo $active; ?>">
            <?php echo Rateb\App\Core\View::escape($tab['label']); ?>
        </a>
        <?php } ?>
    </div>
</nav>
