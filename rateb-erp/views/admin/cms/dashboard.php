<?php /** @var array<string, int> $stats */ ?>
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg-2"><div class="rateb-card"><div class="rateb-card-body text-center"><div class="h3 mb-0"><?php echo (int)($stats['visitors_today'] ?? 0); ?></div><small><?php echo __('cms_visitors_today'); ?></small></div></div></div>
    <div class="col-md-4 col-lg-2"><div class="rateb-card"><div class="rateb-card-body text-center"><div class="h3 mb-0"><?php echo (int)($stats['leads_new'] ?? 0); ?></div><small><?php echo __('cms_leads_new'); ?></small></div></div></div>
    <div class="col-md-4 col-lg-2"><div class="rateb-card"><div class="rateb-card-body text-center"><div class="h3 mb-0"><?php echo (int)($stats['contact_requests'] ?? 0); ?></div><small><?php echo __('cms_contact_requests'); ?></small></div></div></div>
    <div class="col-md-4 col-lg-2"><div class="rateb-card"><div class="rateb-card-body text-center"><div class="h3 mb-0"><?php echo (int)($stats['demo_requests'] ?? 0); ?></div><small><?php echo __('cms_demo_requests'); ?></small></div></div></div>
    <div class="col-md-4 col-lg-2"><div class="rateb-card"><div class="rateb-card-body text-center"><div class="h3 mb-0"><?php echo (int)($stats['newsletter'] ?? 0); ?></div><small><?php echo __('cms_newsletter'); ?></small></div></div></div>
    <div class="col-md-4 col-lg-2"><div class="rateb-card"><div class="rateb-card-body text-center"><div class="h3 mb-0"><?php echo (int)($stats['blog_published'] ?? 0); ?></div><small><?php echo __('cms_blog_stats'); ?></small></div></div></div>
</div>
<div class="rateb-card"><div class="rateb-card-header"><?php echo __('cms_modules'); ?></div><div class="rateb-card-body">
<div class="row g-2">
<?php
$links = [
    ['admin/cms/pages', 'cms_pages'], ['admin/cms/page-builder', 'cms_page_builder'], ['admin/cms/sections', 'cms_sections'], ['admin/cms/blocks', 'cms_blocks'],
    ['admin/cms/menu-items', 'cms_menu'], ['admin/cms/footer-columns', 'cms_footer_columns'], ['admin/cms/offices', 'cms_offices'],
    ['admin/cms/blog-articles', 'cms_blog'], ['admin/cms/faqs', 'cms_faqs'],
    ['admin/cms/testimonials', 'cms_testimonials'], ['admin/cms/slides', 'cms_slides'], ['admin/cms/leads', 'cms_leads'],
    ['admin/cms/newsletter', 'cms_newsletter'], ['admin/cms/seo', 'cms_seo'], ['admin/cms/media', 'cms_media'],
    ['admin/cms/theme', 'cms_theme'], ['admin/cms/analytics', 'cms_analytics'], ['admin/cms/about', 'cms_about'],
    ['admin/cms/contact', 'cms_contact'], ['admin/site', 'cms_view_site', true],
];
foreach ($links as $link) {
    $external = !empty($link[2]);
    $href = $external ? rateb_url($link[0]) : rateb_url($link[0]);
    echo '<div class="col-md-4"><a class="btn btn-outline-primary w-100" href="' . Rateb\App\Core\View::escape($href) . '"';
    if ($external) echo ' target="_blank" rel="noopener"';
    echo '>' . __($link[1]) . '</a></div>';
}
?>
</div></div></div>
