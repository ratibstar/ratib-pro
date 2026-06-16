<?php
/** @var string $hrActive */
$hrActive = $hrActive ?? '';
$tabs = [
    ['path' => rateb_app_route('hr'), 'label' => __('hr_overview'), 'match' => [rateb_app_route('hr')]],
    ['path' => rateb_app_route('hr/employees'), 'label' => __('hr_employees'), 'match' => [rateb_app_route('hr/employees')]],
    ['path' => rateb_app_route('hr/attendance'), 'label' => __('hr_attendance'), 'match' => [rateb_app_route('hr/attendance')]],
    ['path' => rateb_app_route('hr/leaves'), 'label' => __('hr_leaves'), 'match' => [rateb_app_route('hr/leaves')]],
    ['path' => rateb_app_route('hr/payroll'), 'label' => __('hr_payroll'), 'match' => [rateb_app_route('hr/payroll')]],
    ['path' => rateb_app_route('hr/reports'), 'label' => __('hr_reports'), 'match' => [rateb_app_route('hr/reports')]],
];
$route = defined('RATEB_CP_ROUTE') ? (string) RATEB_CP_ROUTE : rateb_current_public_path('admin/ops/hr');
$isActive = static function (array $tab) use ($route): bool {
    foreach ($tab['match'] as $m) {
        if ($route === $m || strpos($route, $m . '/') === 0) {
            return true;
        }
    }
    return false;
};
?>
<nav class="rateb-accounting-nav mb-4" aria-label="<?php echo __('human_resources'); ?>">
    <div class="rateb-accounting-nav-brand">
        <i class="fas fa-users-gear"></i>
        <span><?php echo __('human_resources'); ?></span>
    </div>
    <div class="rateb-accounting-nav-tabs">
        <?php foreach ($tabs as $tab) {
            $active = $isActive($tab) ? ' active' : '';
            ?>
        <a href="<?php echo rateb_url_with_ops_company($tab['path']); ?>" class="rateb-accounting-tab<?php echo $active; ?>">
            <?php echo Rateb\App\Core\View::escape($tab['label']); ?>
        </a>
        <?php } ?>
    </div>
</nav>
