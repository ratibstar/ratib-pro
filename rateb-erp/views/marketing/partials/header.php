<?php
/** @var array<int, array<string, mixed>> $menuItems */
use Rateb\App\Core\Auth;
use Rateb\App\Services\CmsService;

$portalUser = Auth::user();
$isCompanyCustomer = $portalUser && !rateb_is_super_admin() && (int) ($_SESSION['rateb_company_id'] ?? 0) > 0;
$isPortalPage = !empty($isPortalPage);
$portalSection = (string) ($portalSection ?? '');
$portalNav = [
    'home' => ['label' => __('portal_dashboard'), 'url' => rateb_url('site/portal')],
    'profile' => ['label' => __('profile'), 'url' => rateb_url('site/portal/profile')],
    'notifications' => ['label' => __('notifications'), 'url' => rateb_url('site/portal/notifications')],
];
?>
<header class="rateb-mkt-header<?php echo $isPortalPage ? ' rateb-mkt-header-portal' : ''; ?>">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand rateb-mkt-brand" href="<?php echo rateb_url($isCompanyCustomer ? 'site/portal' : 'site'); ?>">
                <i class="fas fa-hospital"></i>
                <span><?php echo __('rateb_erp'); ?></span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ratebMktNav" aria-controls="ratebMktNav" aria-expanded="false" aria-label="Menu">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="ratebMktNav">
                <?php if ($isCompanyCustomer) { ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 rateb-portal-nav">
                    <?php foreach ($portalNav as $key => $nav) { ?>
                    <li class="nav-item">
                        <a class="nav-link<?php echo $portalSection === $key ? ' active fw-semibold' : ''; ?>" href="<?php echo Rateb\App\Core\View::escape($nav['url']); ?>">
                            <?php echo Rateb\App\Core\View::escape($nav['label']); ?>
                        </a>
                    </li>
                    <?php } ?>
                </ul>
                <?php } elseif (!$isPortalPage) { ?>
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
                <div class="d-flex align-items-center gap-2 rateb-mkt-actions">
                    <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo __('theme_dark'); ?>">
                        <button type="button" class="btn btn-outline-secondary" data-mkt-theme="light" title="<?php echo __('theme_light'); ?>"><i class="fas fa-sun"></i></button>
                        <button type="button" class="btn btn-outline-secondary" data-mkt-theme="dark" title="<?php echo __('theme_dark'); ?>"><i class="fas fa-moon"></i></button>
                    </div>
                    <a href="<?php echo rateb_url('locale/en'); ?>" class="btn btn-sm btn-outline-secondary<?php echo rateb_locale() === 'en' ? ' active' : ''; ?>">EN</a>
                    <a href="<?php echo rateb_url('locale/ar'); ?>" class="btn btn-sm btn-outline-secondary<?php echo rateb_locale() === 'ar' ? ' active' : ''; ?>">عربي</a>
                    <?php if ($isCompanyCustomer) { ?>
                    <span class="rateb-mkt-user-chip d-none d-md-inline"><?php echo Rateb\App\Core\View::escape((string) ($portalUser['name'] ?? '')); ?></span>
                    <?php if (!$isPortalPage) { ?>
                    <a href="<?php echo rateb_url('site/portal'); ?>" class="btn btn-sm btn-primary"><?php echo __('portal_my_account'); ?></a>
                    <?php } ?>
                    <a href="<?php echo rateb_url('site/portal/logout'); ?>" class="btn btn-sm btn-outline-danger"><?php echo __('logout'); ?></a>
                    <?php } else { ?>
                    <a href="<?php echo rateb_url('site/login'); ?>" class="btn btn-sm btn-outline-primary"><?php echo __('cms_customer_login'); ?></a>
                    <a href="<?php echo rateb_url('site/register'); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('cms_register'); ?></a>
                    <a href="<?php echo rateb_url('site/request-demo'); ?>" class="btn btn-sm btn-primary rateb-mkt-cta"><?php echo __('cms_request_demo'); ?></a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </nav>
</header>
