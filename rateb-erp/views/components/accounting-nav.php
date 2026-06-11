<?php
/** @var string $accountingActive admin|company */
$accountingActive = $accountingActive ?? 'admin';
$prefix = $accountingActive === 'company' ? 'company' : 'admin';
$route = defined('RATEB_CP_ROUTE') ? (string) RATEB_CP_ROUTE : (string) ($_GET['route'] ?? '');
if (is_array($_GET['route'] ?? null)) {
    $route = (string) end($_GET['route']);
}

$tabs = [
    ['path' => $prefix . '/accounting', 'label' => __('accounting_overview'), 'match' => [$prefix . '/accounting']],
    ['path' => $prefix . '/chart-of-accounts', 'label' => __('chart_of_accounts'), 'match' => [$prefix . '/chart-of-accounts']],
    ['path' => $prefix . '/journal-entries', 'label' => __('journal_entries'), 'match' => [$prefix . '/journal-entries']],
];
if ($accountingActive === 'admin') {
    $tabs[] = ['path' => 'admin/invoices', 'label' => __('invoices'), 'match' => ['admin/invoices']];
    $tabs[] = ['path' => 'admin/payments', 'label' => __('payments'), 'match' => ['admin/payments']];
    $tabs[] = ['path' => 'admin/subscriptions', 'label' => __('subscriptions'), 'match' => ['admin/subscriptions']];
}

$isActive = static function (array $tab) use ($route): bool {
    foreach ($tab['match'] as $m) {
        if ($route === $m || strpos($route, $m . '/') === 0) {
            return true;
        }
    }
    return false;
};
?>
<nav class="rateb-accounting-nav mb-4" aria-label="<?php echo __('accounting'); ?>">
    <div class="rateb-accounting-nav-brand">
        <i class="fas fa-calculator"></i>
        <span><?php echo __('accounting_module'); ?></span>
    </div>
    <div class="rateb-accounting-nav-tabs">
        <?php foreach ($tabs as $tab) {
            $active = $isActive($tab) ? ' active' : '';
            ?>
        <a href="<?php echo rateb_url($tab['path']); ?>" class="rateb-accounting-tab<?php echo $active; ?>">
            <?php echo Rateb\App\Core\View::escape($tab['label']); ?>
        </a>
        <?php } ?>
    </div>
</nav>
