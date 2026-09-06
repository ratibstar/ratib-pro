<?php
/** @var array<int, array<string, mixed>> $footerColumns */
use Rateb\App\Services\CmsService;

$columns = $footerColumns ?? [];
if ($columns === []) {
    $columns = [[
        'title_en' => 'Quick Links',
        'title_ar' => 'روابط سريعة',
        'links_json' => null,
        '_fallback_menu' => true,
    ]];
}
?>
<footer class="rateb-mkt-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <h3 class="rateb-mkt-footer-title"><?php echo Rateb\App\Core\View::escape(function_exists('rateb_erp_brand_display_name') ? rateb_erp_brand_display_name() : __('rateb_erp')); ?></h3>
                <p class="rateb-mkt-footer-text"><?php echo Rateb\App\Core\View::escape(function_exists('rateb_mkt_tenant_copy') ? rateb_mkt_tenant_copy(__('cms_footer_tagline')) : __('cms_footer_tagline')); ?></p>
            </div>
            <?php
            $footerLinkItems = $footerMenu ?? ($menuItems ?? []);
            if (empty($footerLinkItems)) {
                $footerLinkItems = [
                    ['label_en' => 'Home', 'label_ar' => 'الرئيسية', 'url' => 'site'],
                    ['label_en' => 'Features', 'label_ar' => 'المميزات', 'url' => 'site/features'],
                    ['label_en' => 'Pricing', 'label_ar' => 'الأسعار', 'url' => 'site/pricing'],
                    ['label_en' => 'Request Demo', 'label_ar' => 'اطلب عرضاً', 'url' => 'site/request-demo'],
                    ['label_en' => 'Contact', 'label_ar' => 'تواصل معنا', 'url' => 'site/contact'],
                ];
            }
            $footerColsRendered = 0;
            foreach (array_slice($columns, 0, 2) as $col) {
                $links = [];
                if (!empty($col['links_json'])) {
                    $decoded = json_decode((string) $col['links_json'], true);
                    if (is_array($decoded)) {
                        $links = $decoded;
                    }
                }
                $useMenuFallback = !empty($col['_fallback_menu']) || $links === [];
                if (!$useMenuFallback && $links === []) {
                    continue;
                }
                $footerColsRendered++;
                ?>
            <div class="col-lg-4">
                <h4 class="rateb-mkt-footer-heading"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($col, 'title')); ?></h4>
                <ul class="rateb-mkt-footer-links">
                    <?php if ($useMenuFallback) {
                        foreach ($footerLinkItems as $item) {
                            $label = CmsService::pickLocale($item, 'label');
                            ?>
                    <li><a href="<?php echo Rateb\App\Core\View::escape(rateb_url((string) ($item['url'] ?? 'site'))); ?>"><?php echo Rateb\App\Core\View::escape($label); ?></a></li>
                    <?php }
                    } else {
                        foreach ($links as $link) {
                            if (!is_array($link)) {
                                continue;
                            }
                            $label = CmsService::pickLocale($link, 'label');
                            if ($label === '' && !empty($link['label_en'])) {
                                $label = (string) $link['label_en'];
                            }
                            ?>
                    <li><a href="<?php echo Rateb\App\Core\View::escape(rateb_url((string) ($link['url'] ?? 'site'))); ?>"><?php echo Rateb\App\Core\View::escape($label); ?></a></li>
                    <?php }
                    } ?>
                </ul>
            </div>
            <?php }
            if ($footerColsRendered === 0 && $footerLinkItems !== []) { ?>
            <div class="col-lg-4">
                <h4 class="rateb-mkt-footer-heading"><?php echo __('cms_footer_quick_links'); ?></h4>
                <ul class="rateb-mkt-footer-links">
                    <?php foreach ($footerLinkItems as $item) {
                        $label = CmsService::pickLocale($item, 'label');
                        ?>
                    <li><a href="<?php echo Rateb\App\Core\View::escape(rateb_url((string) ($item['url'] ?? 'site'))); ?>"><?php echo Rateb\App\Core\View::escape($label); ?></a></li>
                    <?php } ?>
                </ul>
            </div>
            <?php } ?>
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
            <span>&copy; <?php echo date('Y'); ?> <?php echo Rateb\App\Core\View::escape(function_exists('rateb_erp_brand_display_name') ? rateb_erp_brand_display_name() : __('rateb_erp')); ?></span>
            <div class="rateb-mkt-footer-legal">
                <a href="<?php echo rateb_url('site/privacy'); ?>"><?php echo __('cms_privacy'); ?></a>
                <a href="<?php echo rateb_url('site/terms'); ?>"><?php echo __('cms_terms'); ?></a>
                <a href="<?php echo rateb_url('site/cookies'); ?>"><?php echo __('cms_cookies'); ?></a>
            </div>
        </div>
    </div>
</footer>
