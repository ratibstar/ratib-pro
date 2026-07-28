<?php
/** @var array<string, array<string, mixed>> $content */
use Rateb\App\Services\CmsService;
$hero = $content['hero']['section'] ?? null;
$slides = $slides ?? [];

// Fallback Arabic content for homepage when CMS data is empty
$ratebHomeFallback = [
    'erp_overview' => [
        'section' => [
            'title_en' => 'Unified ERP for Healthcare & Institutions',
            'title_ar' => 'نظام ERP موحد للقطاع الصحي والمؤسسات',
            'body_en' => 'Rateb connects procurement, inventory, contracts, HR, finance, and compliance in one platform built for Saudi Arabia.',
            'body_ar' => 'يربط رتب المشتريات والمخزون والعقود والموارد البشرية والمالية والامتثال في منصة واحدة مبنية للسعودية.',
        ],
    ],
    'why_rateb' => [
        'section' => [
            'title_en' => 'Why Rateb?',
            'title_ar' => 'لماذا رتب؟',
            'body_en' => 'Arabic-first interface, ZATCA e-invoicing readiness, tenant isolation, and partner-friendly pricing.',
            'body_ar' => 'واجهة عربية أولاً، جاهزية الفوترة الإلكترونية لـ ZATCA، عزل المستأجرين، وأسعار مناسبة للشركاء.',
        ],
    ],
    'trust' => [
        'section' => [
            'title_en' => 'Trusted by Institutions',
            'title_ar' => 'ثقة المؤسسات',
            'body_en' => 'Security, compliance, and reliability are built into every layer of the platform.',
            'body_ar' => 'الأمان والامتثال والموثوقية مدمجة في كل طبقة من المنصة.',
        ],
        'blocks' => [
            ['icon' => 'fa-shield-alt', 'title_en' => 'TLS 1.3 & RBAC', 'title_ar' => 'TLS 1.3 والتحكم بالوصول', 'content_en' => 'Enterprise-grade security and role-based access control.', 'content_ar' => 'أمان على مستوى المؤسسات مع تحكم بالوصول المبني على الأدوار.'],
            ['icon' => 'fa-lock', 'title_en' => 'Tenant Isolation', 'title_ar' => 'عزل المستأجرين', 'content_en' => 'Each agency data is isolated and protected.', 'content_ar' => 'بيانات كل جهة معزولة ومحمية.'],
            ['icon' => 'fa-headset', 'title_en' => 'Priority Support', 'title_ar' => 'دعم أولوية', 'content_en' => 'Dedicated support channels for institutions and partners.', 'content_ar' => 'قنوات دعم مخصصة للمؤسسات والشركاء.'],
            ['icon' => 'fa-server', 'title_en' => '99.9% Uptime', 'title_ar' => '99.9% توافر', 'content_en' => 'High-availability infrastructure with continuous monitoring.', 'content_ar' => 'بنية تحتية عالية التوافر مع مراقبة مستمرة.'],
        ],
    ],
    'stats' => [
        'section' => [
            'title_en' => 'Rateb in Numbers',
            'title_ar' => 'أرقام رتب',
        ],
        'blocks' => [
            ['content_en' => '500+', 'content_ar' => '500+', 'title_en' => 'Clients', 'title_ar' => 'عميل'],
            ['content_en' => '25K+', 'content_ar' => '25 ألف+', 'title_en' => 'Users', 'title_ar' => 'مستخدم'],
            ['content_en' => '50+', 'content_ar' => '50+', 'title_en' => 'Cities', 'title_ar' => 'مدينة'],
            ['content_en' => '99.9%', 'content_ar' => '99.9%', 'title_en' => 'Uptime', 'title_ar' => 'توافر'],
        ],
    ],
    'industries' => [
        'section' => [
            'title_en' => 'Industries We Serve',
            'title_ar' => 'القطاعات المدعومة',
        ],
        'blocks' => [
            ['icon' => 'fa-hospital', 'title_en' => 'Hospitals', 'title_ar' => 'المستشفيات'],
            ['icon' => 'fa-clinic-medical', 'title_en' => 'Clinics', 'title_ar' => 'العيادات'],
            ['icon' => 'fa-flask', 'title_en' => 'Medical Labs', 'title_ar' => 'المختبرات الطبية'],
            ['icon' => 'fa-building', 'title_en' => 'Institutions', 'title_ar' => 'المؤسسات'],
            ['icon' => 'fa-truck-medical', 'title_en' => 'Medical Supply', 'title_ar' => 'التوريد الطبي'],
        ],
    ],
    'contact_cta' => [
        'section' => [
            'title_en' => 'Start Your Digital Transformation',
            'title_ar' => 'ابدأ رحلة التحول الرقمي',
            'body_en' => 'Request a demo and see how Rateb can streamline your operations today.',
            'body_ar' => 'اطلب عرضاً وشاهد كيف يمكن لرتب تبسيط عملياتك اليوم.',
        ],
    ],
];

foreach ($ratebHomeFallback as $key => $fallback) {
    if (empty($content[$key])) {
        $content[$key] = $fallback;
    }
}

if (empty($testimonials)) {
    $testimonials = [
        [
            'quote_en' => 'Rateb reduced our procurement cycle by 40% and gave us full visibility over inventory across branches.',
            'quote_ar' => 'قلّص رتب دورة المشتريات لدينا بنسبة 40% وأعطانا رؤية كاملة للمخزون عبر الفروع.',
            'customer_name_en' => 'Dr. Ahmed Al-Rashid',
            'customer_name_ar' => 'د. أحمد الرشيد',
            'position_en' => 'Operations Director',
            'position_ar' => 'مدير العمليات',
            'company_en' => 'Al-Rashid Medical Group',
            'company_ar' => 'مجموعة الرشيد الطبية',
        ],
        [
            'quote_en' => 'The e-invoicing integration and Arabic interface made compliance effortless for our team.',
            'quote_ar' => 'جعلت لنا تكامل الفوترة الإلكترونية والواجهة العربية الامتثال سهلاً وسريعاً.',
            'customer_name_en' => 'Sarah Al-Otaibi',
            'customer_name_ar' => 'سارة العتيبي',
            'position_en' => 'Finance Manager',
            'position_ar' => 'مديرة المالية',
            'company_en' => 'Otaibi Healthcare',
            'company_ar' => 'رعاية العتيبي الصحية',
        ],
        [
            'quote_en' => 'We moved from spreadsheets to a unified ERP in weeks. Support has been outstanding.',
            'quote_ar' => 'انتقلنا من جداول البيانات إلى نظام ERP موحد في أسابيع. الدعم كان ممتازاً.',
            'customer_name_en' => 'Khalid Al-Mutairi',
            'customer_name_ar' => 'خالد المطيري',
            'position_en' => 'CEO',
            'position_ar' => 'المدير التنفيذي',
            'company_en' => 'Mutairi Medical Supplies',
            'company_ar' => 'توريد المطيري الطبي',
        ],
    ];
}

if (empty($articles)) {
    $articles = [
        [
            'slug' => 'zatca-e-invoicing-guide',
            'title_en' => 'ZATCA E-Invoicing Guide',
            'title_ar' => 'دليل الفوترة الإلكترونية لـ ZATCA',
            'excerpt_en' => 'Everything you need to know about Phase 2 compliance and integration.',
            'excerpt_ar' => 'كل ما تحتاج معرفته عن الامتثال للمرحلة الثانية والتكامل.',
        ],
        [
            'slug' => 'inventory-optimization',
            'title_en' => 'Inventory Optimization for Healthcare',
            'title_ar' => 'تحسين المخزون للقطاع الصحي',
            'excerpt_en' => 'How clinics and hospitals can reduce waste and avoid stockouts.',
            'excerpt_ar' => 'كيف تقلّص العيادات والمستشفيات الهدر وتتجنب نفاد المخزون.',
        ],
        [
            'slug' => 'procurement-best-practices',
            'title_en' => 'Procurement Best Practices',
            'title_ar' => 'أفضل ممارسات المشتريات',
            'excerpt_en' => 'Streamline vendor management, RFQs, and purchase orders.',
            'excerpt_ar' => 'بسّط إدارة الموردين وطلبات عروض الأسعار وأوامر الشراء.',
        ],
    ];
}

if (empty($faqs)) {
    $faqs = [
        [
            'question_en' => 'What modules does Rateb include?',
            'question_ar' => 'ما الوحدات الموجودة في رتب؟',
            'answer_en' => 'Rateb includes procurement, inventory, contracts, HR, payroll, accounting, manufacturing, CRM, and more under one unified platform.',
            'answer_ar' => 'تشمل رتب المشتريات والمخزون والعقود والموارد البشرية والرواتب والمحاسبة والتصنيع وإدارة العملاء وغيرها في منصة موحدة.',
        ],
        [
            'question_en' => 'Is Rateb compliant with ZATCA e-invoicing?',
            'question_ar' => 'هل رتب متوافق مع الفوترة الإلكترونية لـ ZATCA؟',
            'answer_en' => 'Yes. Rateb supports ZATCA Phase 2 e-invoicing with QR codes, UUIDs, and compliant XML generation.',
            'answer_ar' => 'نعم. يدعم رتب الفوترة الإلكترونية للمرحلة الثانية من ZATCA مع رموز QR ومعرّفات UUID وملفات XML متوافقة.',
        ],
        [
            'question_en' => 'Can I manage multiple branches?',
            'question_ar' => 'هل يمكن إدارة فروع متعددة؟',
            'answer_en' => 'Yes. Rateb supports multi-branch inventory, transfers, and consolidated reporting with tenant isolation.',
            'answer_ar' => 'نعم. يدعم رتب مخزون متعدد الفروع والتحويلات والتقارير المركبة مع عزل المستأجرين.',
        ],
        [
            'question_en' => 'Do you offer implementation support?',
            'question_ar' => 'هل تقدمون دعم التنفيذ؟',
            'answer_en' => 'Yes. We provide onboarding, training, and priority support for institutions and professional plans.',
            'answer_ar' => 'نعم. نقدم التهيئة والتدريب والدعم الأولوي للمؤسسات وباقات الاحترافية.',
        ],
        [
            'question_en' => 'How do I request a demo?',
            'question_ar' => 'كيف أطلب عرضاً؟',
            'answer_en' => 'Click "Request a Quote" or "Request Demo" anywhere on the site and our team will contact you.',
            'answer_ar' => 'اضغط على "اطلب عرضاً" أو "اطلب عرضاً توضيحياً" في أي مكان في الموقع وسيتواصل معك فريقنا.',
        ],
    ];
}
?>
<section class="rateb-mkt-hero">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <?php if ($hero) { ?>
                <h1 class="rateb-mkt-hero-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($hero, 'title')); ?></h1>
                <p class="rateb-mkt-hero-subtitle"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($hero, 'body')); ?></p>
                <?php } elseif (!empty($slides[0])) {
                    $s = $slides[0]; ?>
                <h1 class="rateb-mkt-hero-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'title')); ?></h1>
                <p class="rateb-mkt-hero-subtitle"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'subtitle')); ?></p>
                <?php } ?>
                <div class="rateb-mkt-hero-cta">
                    <a href="<?php echo rateb_url('site/request-demo'); ?>" class="btn btn-primary btn-lg"><?php echo __('cms_request_demo'); ?></a>
                    <a href="<?php echo rateb_url('site/features'); ?>" class="btn btn-outline-primary btn-lg"><?php echo __('cms_explore_features'); ?></a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="rateb-mkt-hero-card">
                    <i class="fas fa-chart-line"></i>
                    <span><?php
                    $heroCardText = __('cms_erp_overview_short');
                    if ($hero) {
                        $heroSettings = $hero['settings_json'] ?? null;
                        if (is_string($heroSettings) && $heroSettings !== '') {
                            $heroSettings = json_decode($heroSettings, true);
                        }
                        if (is_array($heroSettings)) {
                            $cardKey = rateb_locale() === 'ar' ? 'hero_card_ar' : 'hero_card_en';
                            $fromCms = trim((string) ($heroSettings[$cardKey] ?? ''));
                            if ($fromCms !== '') {
                                $heroCardText = $fromCms;
                            }
                        }
                    }
                    echo Rateb\App\Core\View::escape($heroCardText);
                    ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($content['erp_overview']['section'])) {
    $s = $content['erp_overview']['section']; ?>
<section class="rateb-mkt-section rateb-mkt-intro">
    <div class="container">
        <div class="rateb-mkt-intro-card">
            <i class="fas fa-hospital rateb-mkt-intro-icon" aria-hidden="true"></i>
            <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'title')); ?></h2>
            <p class="rateb-mkt-section-lead"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'body')); ?></p>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($content['why_rateb']['section'])) {
    $s = $content['why_rateb']['section']; ?>
<section class="rateb-mkt-section rateb-mkt-section-alt rateb-mkt-intro">
    <div class="container">
        <div class="rateb-mkt-intro-card">
            <i class="fas fa-check-circle rateb-mkt-intro-icon" aria-hidden="true"></i>
            <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'title')); ?></h2>
            <p class="rateb-mkt-section-lead"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'body')); ?></p>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($content['trust']['blocks'])) {
    $s = $content['trust']['section'] ?? []; ?>
<section class="rateb-mkt-section rateb-mkt-trust">
    <div class="container">
        <?php if (!empty($s)) { ?>
        <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'title')); ?></h2>
        <?php if (trim(CmsService::pickLocale($s, 'body')) !== '') { ?>
        <p class="rateb-mkt-section-lead text-center mb-4"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($s, 'body')); ?></p>
        <?php } ?>
        <?php } ?>
        <div class="row g-3">
            <?php foreach ($content['trust']['blocks'] as $block) { ?>
            <div class="col-md-6 col-lg-3">
                <div class="rateb-mkt-trust-card">
                    <?php if (!empty($block['icon'])) { ?><i class="fas <?php echo Rateb\App\Core\View::escape($block['icon']); ?>"></i><?php } ?>
                    <h3><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'title')); ?></h3>
                    <p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'content')); ?></p>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($content['stats']['blocks'])) {
    $statsSection = $content['stats']['section'] ?? [];
    $statsTitle = CmsService::pickLocale($statsSection, 'title');
    ?>
<section class="rateb-mkt-stats">
    <div class="container">
        <?php if ($statsTitle !== '') { ?>
        <h2 class="rateb-mkt-section-title text-center text-white mb-4"><?php echo Rateb\App\Core\View::escape($statsTitle); ?></h2>
        <?php } ?>
        <div class="row g-3">
            <?php foreach ($content['stats']['blocks'] as $block) { ?>
            <div class="col-6 col-md-3">
                <div class="rateb-mkt-stat-card">
                    <div class="rateb-mkt-stat-value"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'content')); ?></div>
                    <div class="rateb-mkt-stat-label"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'title')); ?></div>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($content['industries']['blocks'])) { ?>
<section class="rateb-mkt-section">
    <div class="container">
        <h2 class="rateb-mkt-section-title"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($content['industries']['section'] ?? [], 'title')); ?></h2>
        <div class="row g-3">
            <?php foreach ($content['industries']['blocks'] as $block) { ?>
            <div class="col-md-4 col-lg">
                <div class="rateb-mkt-icon-card">
                    <?php if (!empty($block['icon'])) { ?><i class="fas <?php echo Rateb\App\Core\View::escape($block['icon']); ?>"></i><?php } ?>
                    <h3><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($block, 'title')); ?></h3>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($plans)) {
    $pricingSection = $content['pricing_preview']['section'] ?? [];
    $sectionTitle = CmsService::pickLocale($pricingSection, 'title') ?: __('cms_pricing_preview');
    $sectionLead = CmsService::pickLocale($pricingSection, 'body');
    $compact = true;
    require RATEB_ROOT . '/views/marketing/partials/plans-grid.php';
} ?>

<?php if (!empty($testimonials)) { ?>
<section class="rateb-mkt-section rateb-mkt-section-alt">
    <div class="container">
        <?php
        $sectionTitle = CmsService::pickLocale($content['testimonials']['section'] ?? [], 'title') ?: __('cms_testimonials');
        $moreUrl = rateb_url('site/reviews');
        require RATEB_ROOT . '/views/marketing/partials/section-head-more.php';
        ?>
        <div class="row g-3">
            <?php foreach (array_slice($testimonials, 0, 3) as $t) { ?>
            <div class="col-md-4">
                <blockquote class="rateb-mkt-testimonial">
                    <p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($t, 'quote')); ?></p>
                    <footer>
                        <strong><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($t, 'customer_name')); ?></strong>
                        <?php
                        $tPos = CmsService::pickLocale($t, 'position');
                        $tCo = CmsService::pickLocale($t, 'company');
                        $tMeta = $tPos !== '' && $tCo !== '' ? $tPos . ' — ' . $tCo : ($tPos !== '' ? $tPos : $tCo);
                        if ($tMeta !== '') { ?>
                        <span><?php echo Rateb\App\Core\View::escape($tMeta); ?></span>
                        <?php } ?>
                    </footer>
                </blockquote>
            </div>
            <?php } ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo rateb_url('site/reviews'); ?>" class="btn btn-outline-primary rateb-mkt-more-btn">
                <i class="fas fa-circle-plus ms-1"></i><?php echo __('cms_view_all_reviews'); ?>
            </a>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($articles)) { ?>
<section class="rateb-mkt-section rateb-mkt-section-alt">
    <div class="container">
        <h2 class="rateb-mkt-section-title"><?php echo __('cms_latest_articles'); ?></h2>
        <div class="row g-3">
            <?php foreach ($articles as $article) { ?>
            <div class="col-md-4">
                <article class="rateb-mkt-article-card">
                    <h3><a href="<?php echo rateb_url('site/blog/' . ($article['slug'] ?? '')); ?>"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($article, 'title')); ?></a></h3>
                    <p><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($article, 'excerpt')); ?></p>
                </article>
            </div>
            <?php } ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo rateb_url('site/blog'); ?>" class="btn btn-outline-primary rateb-mkt-more-btn">
                <i class="fas fa-circle-plus ms-1"></i><?php echo __('cms_view_all_blog'); ?>
            </a>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($faqs)) { ?>
<section class="rateb-mkt-section">
    <div class="container">
        <?php
        $sectionTitle = __('cms_faq_preview');
        $moreUrl = rateb_url('site/faq');
        require RATEB_ROOT . '/views/marketing/partials/section-head-more.php';
        ?>
        <div class="accordion rateb-mkt-faq" id="homeFaq">
            <?php foreach (array_slice($faqs, 0, 5) as $i => $faq) { ?>
            <div class="accordion-item">
                <h3 class="accordion-header">
                    <button class="accordion-button<?php echo $i > 0 ? ' collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?php echo $i; ?>">
                        <?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($faq, 'question')); ?>
                    </button>
                </h3>
                <div id="faq<?php echo $i; ?>" class="accordion-collapse collapse<?php echo $i === 0 ? ' show' : ''; ?>" data-bs-parent="#homeFaq">
                    <div class="accordion-body"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($faq, 'answer')); ?></div>
                </div>
            </div>
            <?php } ?>
        </div>
        <div class="text-center mt-4">
            <a href="<?php echo rateb_url('site/faq'); ?>" class="btn btn-outline-primary rateb-mkt-more-btn">
                <i class="fas fa-circle-plus ms-1"></i><?php echo __('cms_view_all_faq'); ?>
            </a>
        </div>
    </div>
</section>
<?php } ?>

<?php if (!empty($content['contact_cta']['section'])) {
    $cta = $content['contact_cta']['section']; ?>
<section class="rateb-mkt-cta">
    <div class="container text-center">
        <h2 class="rateb-mkt-section-title text-white mb-3"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($cta, 'title')); ?></h2>
        <?php if (trim(CmsService::pickLocale($cta, 'body')) !== '') { ?>
        <p class="mb-4 opacity-90"><?php echo Rateb\App\Core\View::escape(CmsService::pickLocale($cta, 'body')); ?></p>
        <?php } ?>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="<?php echo rateb_url('site/request-demo'); ?>" class="btn btn-light btn-lg"><?php echo __('cms_request_demo'); ?></a>
            <a href="<?php echo rateb_url('site/contact'); ?>" class="btn btn-outline-light btn-lg"><?php echo __('cms_contact_us'); ?></a>
        </div>
    </div>
</section>
<?php } ?>
