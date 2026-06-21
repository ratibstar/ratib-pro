<?php
/** @var array<int, array<string, mixed>> $menuItems */
use Rateb\App\Core\Auth;
use Rateb\App\Services\CmsService;

$portalUser = Auth::user();
$isCompanyCustomer = $portalUser && !rateb_is_super_admin() && (int) ($_SESSION['rateb_company_id'] ?? 0) > 0;
$headerContext = (string) ($headerContext ?? 'marketing');
$portalSection = (string) ($portalSection ?? '');
$portalNav = [
    'home' => ['label' => __('portal_dashboard'), 'url' => rateb_url('site/portal')],
    'profile' => ['label' => __('profile'), 'url' => rateb_url('site/portal/profile')],
    'notifications' => ['label' => __('notifications'), 'url' => rateb_url('site/portal/notifications')],
];
$brandUrl = $headerContext === 'portal' ? rateb_url('site/portal') : rateb_url('site');
$localeEnUrl = function_exists('rateb_locale_switch_url') ? rateb_locale_switch_url('en') : rateb_url('locale/en');
$localeArUrl = function_exists('rateb_locale_switch_url') ? rateb_locale_switch_url('ar') : rateb_url('locale/ar');
?>
<header class="rateb-mkt-header rateb-mkt-header-<?php echo Rateb\App\Core\View::escape($headerContext); ?>">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand rateb-mkt-brand" href="<?php echo $brandUrl; ?>">
                <i class="fas fa-hospital"></i>
                <span><?php echo __('rateb_erp'); ?></span>
                <?php if ($headerContext === 'portal') { ?>
                <small class="rateb-mkt-brand-badge"><?php echo __('portal_customer_area'); ?></small>
                <?php } ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ratebMktNav" aria-controls="ratebMktNav" aria-expanded="false" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="ratebMktNav">
                <?php if ($headerContext === 'portal') { ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 rateb-portal-nav">
                    <?php foreach ($portalNav as $key => $nav) { ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $portalSection === $key ? ' active fw-semibold' : ''; ?>" href="<?php echo Rateb\App\Core\View::escape($nav['url']); ?>">
                            <?php echo Rateb\App\Core\View::escape($nav['label']); ?>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
                <?php } elseif ($headerContext === 'auth') { ?>
                <div class="me-auto mb-2 mb-lg-0">
                    <a href="<?php echo rateb_url('site'); ?>" class="nav-link d-inline-block px-0">
                        <i class="fas fa-arrow-right ms-1"></i><?php echo __('cms_back_home'); ?>
                    </a>
                </div>
                <?php } else { ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php foreach ($menuItems ?? [] as $item) {
                        $label = CmsService::pickLocale($item, 'label');
                        $url = rateb_url((string) ($item['url'] ?? 'site'));
                        ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo Rateb\App\Core\View::escape($url); ?>"><?php echo Rateb\App\Core\View::escape($label); ?></a>
                    </li>
                    <?php } ?>
                </ul>
                <?php } ?>
                <div class="d-flex align-items-center gap-2 rateb-mkt-actions flex-wrap">
                    <?php if ($headerContext !== 'auth') { ?>
                    <div class="btn-group btn-group-sm d-none d-md-inline-flex" role="group" aria-label="<?php echo __('theme_dark'); ?>">
                        <button type="button" class="btn btn-outline-secondary" data-mkt-theme="light" title="<?php echo __('theme_light'); ?>"><i class="fas fa-sun"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-mkt-theme="dark" title="<?php echo __('theme_dark'); ?>"><i class="fas fa-moon"></i></button>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo __('language'); ?>">
                        <a href="<?php echo Rateb\App\Core\View::escape($localeArUrl); ?>" class="btn btn-outline-secondary<?php echo rateb_locale() === 'ar' ? ' active' : ''; ?>" lang="ar">عربي</a>
                        <a href="<?php echo Rateb\App\Core\View::escape($localeEnUrl); ?>" class="btn btn-outline-secondary<?php echo rateb_locale() === 'en' ? ' active' : ''; ?>" lang="en">EN</a>
                    </div>
                    <?php if ($headerContext === 'marketing') { ?>
                    <a href="<?php echo Rateb\App\Core\View::escape(rateb_marketing_partner_login_url()); ?>" class="btn btn-sm btn-outline-secondary d-none d-lg-inline-flex"><?php echo __('cms_partner_login'); ?></a>
                    <?php } ?>
                    <?php } ?>
                    <?php if ($isCompanyCustomer && $headerContext === 'marketing') { ?>
                    <a href="<?php echo rateb_url('site/portal'); ?>" class="btn btn-sm btn-primary"><?php echo __('portal_my_account'); ?></a>
                    <a href="<?php echo rateb_url('site/portal/logout'); ?>" class="btn btn-sm btn-outline-danger"><?php echo __('logout'); ?></a>
                    <?php } elseif ($isCompanyCustomer && $headerContext === 'portal') { ?>
                    <span class="rateb-mkt-user-chip d-none d-md-inline"><?php echo Rateb\App\Core\View::escape((string) ($portalUser['name'] ?? '')); ?></span>
                    <a href="<?php echo rateb_url('site/portal/logout'); ?>" class="btn btn-sm btn-outline-danger"><?php echo __('logout'); ?></a>
                    <?php } elseif ($headerContext !== 'auth') { ?>
                    <a href="<?php echo rateb_url('site/login'); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('cms_customer_login'); ?></a>
                    <a href="<?php echo Rateb\App\Core\View::escape(rateb_marketing_register_url()); ?>" class="btn btn-sm btn-primary"><?php echo __('cms_register'); ?></a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </nav>
</header>
