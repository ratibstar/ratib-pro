<?php
declare(strict_types=1);

/** @var string $activeApp hr|erp|customer */
$activeApp = $activeApp ?? 'hr';
$tabs = [
    'hr' => ['fa-id-badge', 'mobile_apps_tab_hr', 'mobile_apps_tab_hr_hint'],
    'erp' => ['fa-building', 'mobile_apps_tab_erp', 'mobile_apps_tab_erp_hint'],
    'customer' => ['fa-users', 'mobile_apps_tab_customer', 'mobile_apps_tab_customer_hint'],
];
?>
<div class="row g-2 mb-3" role="tablist">
    <?php foreach ($tabs as $key => [$icon, $label, $hint]) {
        $isActive = $key === $activeApp;
        $href = $key === 'hr' ? rateb_url('admin/mobile-apps') : rateb_url('admin/mobile-apps') . '?app=' . $key;
        ?>
    <div class="col-4">
        <a href="<?php echo Rateb\App\Core\View::escape($href); ?>" role="tab" aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
           class="btn w-100 h-100 py-3 d-flex flex-column align-items-center gap-1 <?php echo $isActive ? 'btn-primary' : 'btn-outline-primary'; ?>">
            <i class="fas <?php echo $icon; ?> fa-lg"></i>
            <span class="fw-semibold"><?php echo Rateb\App\Core\View::escape(__($label)); ?></span>
            <span class="small <?php echo $isActive ? '' : 'text-muted'; ?> d-none d-md-inline"><?php echo Rateb\App\Core\View::escape(__($hint)); ?></span>
        </a>
    </div>
    <?php } ?>
</div>
