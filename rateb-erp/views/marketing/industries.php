<?php
use Rateb\App\Services\CmsService;
$blocks = $content['list']['blocks'] ?? $content['industries']['blocks'] ?? [];

if (empty($blocks)) {
    $blocks = [
        ['icon' => 'fa-hospital', 'title_en' => 'Hospitals', 'title_ar' => 'المستشفيات', 'content_en' => 'Full ERP for hospitals and medical centers.', 'content_ar' => 'نظام ERP كامل للمستشفيات والمراكز الطبية.'],
        ['icon' => 'fa-clinic-medical', 'title_en' => 'Clinics', 'title_ar' => 'العيادات', 'content_en' => 'Streamlined operations for clinics of all sizes.', 'content_ar' => 'عمليات مبسّطة للعيادات بجميع الأحجام.'],
        ['icon' => 'fa-flask', 'title_en' => 'Medical Labs', 'title_ar' => 'المختبرات الطبية', 'content_en' => 'Inventory and compliance for medical laboratories.', 'content_ar' => 'المخزون والامتثال للمختبرات الطبية.'],
        ['icon' => 'fa-building', 'title_en' => 'Institutions', 'title_ar' => 'المؤسسات', 'content_en' => 'Multi-branch control for institutions.', 'content_ar' => 'تحكم متعدد الفروع للمؤسسات.'],
        ['icon' => 'fa-truck-medical', 'title_en' => 'Medical Supply', 'title_ar' => 'التوريد الطبي', 'content_en' => 'Procurement and distribution for medical suppliers.', 'content_ar' => 'المشتريات والتوزيع لموردي التوريد الطبي.'],
        ['icon' => 'fa-user-md', 'title_en' => 'Healthcare Groups', 'title_ar' => 'مجموعات الرعاية الصحية', 'content_en' => 'Unified platform for healthcare groups.', 'content_ar' => 'منصة موحدة لمجموعات الرعاية الصحية.'],
    ];
}
?>
<section class="rateb-mkt-page-hero"><div class="container"><h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div></section>
<section class="rateb-mkt-section"><div class="container"><div class="row g-4">
<?php foreach ($blocks as $block) { ?>
<div class="col-md-6 col-lg-4"><div class="rateb-mkt-icon-card">
<?php if (!empty($block['icon'])) { ?><i class="fas <?php echo Rateb\App\Core\View::escape($block['icon']); ?>"></i><?php } ?>
<h3><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'title')); ?></h3>
<p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'content')); ?></p>
</div></div>
<?php } ?>
</div></div></section>
