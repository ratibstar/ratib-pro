<?php
/** @var array<string, array<string, mixed>> $content */
use Rateb\App\Services\CmsService;
$blocks = $content['list']['blocks'] ?? [];

if (empty($blocks)) {
    $blocks = [
        ['icon' => 'fa-shopping-cart', 'title_en' => 'Procurement', 'title_ar' => 'المشتريات', 'content_en' => 'Manage RFQs, purchase orders, and suppliers in one place.', 'content_ar' => 'إدارة طلبات عروض الأسعار وأوامر الشراء والموردين في مكان واحد.'],
        ['icon' => 'fa-boxes', 'title_en' => 'Inventory', 'title_ar' => 'المخزون', 'content_en' => 'Multi-branch inventory with transfers, batches, and alerts.', 'content_ar' => 'مخزون متعدد الفروع مع التحويلات والدفعات والتنبيهات.'],
        ['icon' => 'fa-file-contract', 'title_en' => 'Contracts', 'title_ar' => 'العقود', 'content_en' => 'Track contracts, renewals, and vendor agreements.', 'content_ar' => 'متابعة العقود والتجديدات واتفاقيات الموردين.'],
        ['icon' => 'fa-file-invoice', 'title_en' => 'E-Invoicing', 'title_ar' => 'الفوترة الإلكترونية', 'content_en' => 'ZATCA Phase 2 ready with QR codes and XML generation.', 'content_ar' => 'جاهز لمرحلة ZATCA الثانية مع رموز QR وتوليد XML.'],
        ['icon' => 'fa-users', 'title_en' => 'HR & Payroll', 'title_ar' => 'الموارد البشرية والرواتب', 'content_en' => 'Employee records, attendance, and payroll processing.', 'content_ar' => 'سجلات الموظفين والحضور ومعالجة الرواتب.'],
        ['icon' => 'fa-chart-line', 'title_en' => 'Analytics', 'title_ar' => 'التحليلات', 'content_en' => 'Reports and dashboards for every department.', 'content_ar' => 'تقارير ولوحات معلومات لكل قسم.'],
    ];
}
?>
<section class="rateb-mkt-page-hero">
    <div class="container">
        <h1><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
    </div>
</section>
<section class="rateb-mkt-section">
    <div class="container">
        <div class="row g-4">
            <?php foreach ($blocks as $block) { ?>
            <div class="col-md-6 col-lg-4">
                <div class="rateb-mkt-feature-card">
                    <?php if (!empty($block['icon'])) { ?>
                    <div class="rateb-mkt-feature-icon"><i class="fas <?php echo Rateb\App\Core\View::escape($block['icon']); ?>"></i></div>
                    <?php } ?>
                    <h3><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'title')); ?></h3>
                    <p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'content')); ?></p>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
