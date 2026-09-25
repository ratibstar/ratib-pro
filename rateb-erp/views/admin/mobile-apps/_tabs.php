<?php
declare(strict_types=1);

/** @var string $activeApp hr|erp|customer ('' = the "all apps" tab) */
/** @var string|null $tabsRoute page the tabs filter (default: app distribution) */
/** @var bool|null $tabsWithAll prepend an "all apps" tab */
$activeApp = $activeApp ?? 'hr';
$tabsRoute = (string) ($tabsRoute ?? 'admin/mobile-apps');
$tabsWithAll = !empty($tabsWithAll);
$tabs = [
    'hr' => ['fa-id-badge', 'mobile_apps_tab_hr', 'mobile_apps_tab_hr_hint'],
    'erp' => ['fa-building', 'mobile_apps_tab_erp', 'mobile_apps_tab_erp_hint'],
    'customer' => ['fa-users', 'mobile_apps_tab_customer', 'mobile_apps_tab_customer_hint'],
];
if ($tabsWithAll) {
    $tabs = ['' => ['fa-layer-group', 'mobile_apps_tab_all', 'mobile_apps_tab_all_hint']] + $tabs;
}
$tabCol = $tabsWithAll ? 'col-6 col-md-3' : 'col-4';
?>
<div class="row g-2 mb-3" role="tablist">
    <?php foreach ($tabs as $key => [$icon, $label, $hint]) {
        $key = (string) $key;
        $isActive = $key === $activeApp;
        $plain = $key === '' || ($key === 'hr' && !$tabsWithAll);
        $href = rateb_url($tabsRoute) . ($plain ? '' : '?app=' . $key);
        ?>
    <div class="<?php echo $tabCol; ?>">
        <a href="<?php echo Rateb\App\Core\View::escape($href); ?>" role="tab" aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
           class="btn w-100 h-100 py-3 d-flex flex-column align-items-center gap-1 <?php echo $isActive ? 'btn-primary' : 'btn-outline-primary'; ?>">
            <i class="fas <?php echo $icon; ?> fa-lg"></i>
            <span class="fw-semibold"><?php echo Rateb\App\Core\View::escape(__($label)); ?></span>
            <span class="small <?php echo $isActive ? '' : 'text-muted'; ?> d-none d-md-inline"><?php echo Rateb\App\Core\View::escape(__($hint)); ?></span>
        </a>
    </div>
    <?php } ?>
</div>
