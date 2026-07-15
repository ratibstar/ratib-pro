<?php
$portalType = $portalType ?? 'customer';
$portalSection = $portalSection ?? '';
$base = rateb_url('site/' . $portalType);
$links = [
    'dashboard' => ['label' => __('portal_dashboard') ?: 'Dashboard', 'href' => $base],
    'requests' => ['label' => __('requests') ?: 'Requests', 'href' => $base . '/requests'],
    'finance' => ['label' => __('finance') ?: 'Finance', 'href' => $base . '/finance'],
    'documents' => ['label' => __('documents') ?: 'Documents', 'href' => $base . '/documents'],
    'appointments' => ['label' => __('appointments') ?: 'Calendar', 'href' => $base . '/appointments'],
    'support' => ['label' => __('support') ?: 'Support', 'href' => $base . '/support'],
    'approvals' => ['label' => __('approvals') ?: 'Approvals', 'href' => $base . '/approvals'],
    'notifications' => ['label' => __('notifications') ?: 'Notifications', 'href' => $base . '/notifications'],
    'profile' => ['label' => __('profile') ?: 'Profile', 'href' => $base . '/profile'],
];
if ($portalType === 'employer') {
    $links = array_merge(
        array_slice($links, 0, 1, true),
        ['recruitment' => ['label' => __('recruitment') ?: 'Recruitment', 'href' => $base . '/recruitment']],
        array_slice($links, 1, null, true)
    );
}
?>
<nav class="rateb-portal-nav" aria-label="Portal">
    <div class="container rateb-portal-nav__inner">
        <strong class="rateb-portal-nav__brand"><?php echo Rateb\App\Core\View::escape(ucfirst($portalType)); ?></strong>
        <?php foreach ($links as $key => $link) { ?>
        <a class="rateb-portal-nav__link<?php echo $portalSection === $key ? ' is-active' : ''; ?>" href="<?php echo Rateb\App\Core\View::escape($link['href']); ?>"><?php echo Rateb\App\Core\View::escape($link['label']); ?></a>
        <?php } ?>
        <a class="rateb-portal-nav__link rateb-portal-nav__link--out" href="<?php echo rateb_url('site/' . $portalType . '/logout'); ?>"><?php echo __('logout') ?: 'Logout'; ?></a>
    </div>
</nav>
