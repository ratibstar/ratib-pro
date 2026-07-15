<?php use Rateb\App\Website\Career\CareerPortalAuthService; ?>
<nav class="rateb-career-nav" aria-label="Career portal">
    <div class="container rateb-career-nav__inner">
        <a class="rateb-career-nav__link" href="<?php echo rateb_url('site/careers'); ?>"><?php echo __('careers') ?: 'Careers'; ?></a>
        <a class="rateb-career-nav__link" href="<?php echo rateb_url('site/careers/search'); ?>"><?php echo __('job_search') ?: 'Search'; ?></a>
        <?php $portalUser = (new CareerPortalAuthService())->currentUser(); ?>
        <?php if ($portalUser) { ?>
        <a class="rateb-career-nav__link" href="<?php echo rateb_url('site/candidate'); ?>"><?php echo __('portal_dashboard') ?: 'My Portal'; ?></a>
        <a class="rateb-career-nav__link" href="<?php echo rateb_url('site/candidate/logout'); ?>"><?php echo __('logout') ?: 'Logout'; ?></a>
        <?php } else { ?>
        <a class="rateb-career-nav__link" href="<?php echo rateb_url('site/candidate/login'); ?>"><?php echo __('login') ?: 'Login'; ?></a>
        <a class="rateb-career-nav__link rateb-career-nav__link--cta" href="<?php echo rateb_url('site/candidate/register'); ?>"><?php echo __('register') ?: 'Register'; ?></a>
        <?php } ?>
    </div>
</nav>
