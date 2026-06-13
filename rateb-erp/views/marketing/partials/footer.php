<?php use Rateb\App\Services\CmsService; ?>
<footer class="rateb-mkt-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h3 class="rateb-mkt-footer-title"><?php echo __('rateb_erp'); ?></h3>
                <p class="rateb-mkt-footer-text"><?php echo __('cms_footer_tagline'); ?></p>
            </div>
            <div class="col-lg-4">
                <h4 class="rateb-mkt-footer-heading"><?php echo __('cms_quick_links'); ?></h4>
                <ul class="rateb-mkt-footer-links">
                    <?php foreach ($footerMenu ?? ($menuItems ?? []) as $item) {
                        $label = CmsService::pickLocale($item, 'label');
                        ?>
                    <li><a href="<?php echo Rateb\App\Core\View::escape(rateb_url((string) ($item['url'] ?? 'site'))); ?>"><?php echo Rateb\App\Core\View::escape($label); ?></a></li>
                    <?php } ?>
                </ul>
            </div>
            <div class="col-lg-4">
                <h4 class="rateb-mkt-footer-heading"><?php echo __('cms_newsletter'); ?></h4>
                <form method="post" action="<?php echo rateb_url('site/newsletter'); ?>" class="rateb-mkt-newsletter-form">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
                    <div class="input-group">
                        <input type="email" name="email" class="form-control" placeholder="<?php echo __('email'); ?>" required>
                        <button class="btn btn-primary" type="submit"><?php echo __('cms_subscribe'); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="rateb-mkt-footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> <?php echo __('rateb_erp'); ?></span>
            <div class="rateb-mkt-footer-legal">
                <a href="<?php echo rateb_url('site/privacy'); ?>"><?php echo __('cms_privacy'); ?></a>
                <a href="<?php echo rateb_url('site/terms'); ?>"><?php echo __('cms_terms'); ?></a>
                <a href="<?php echo rateb_url('site/cookies'); ?>"><?php echo __('cms_cookies'); ?></a>
            </div>
        </div>
    </div>
</footer>
