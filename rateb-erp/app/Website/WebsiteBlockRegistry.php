<?php
declare(strict_types=1);

namespace Rateb\App\Website;

/**
 * Phase WEBSITE-04 — Catalog of builder block types (single registry; no duplicates).
 */
final class WebsiteBlockRegistry
{
    /**
     * @return array<string, array{label_en:string,label_ar:string,category:string,icon:string}>
     */
    public static function all(): array
    {
        return [
            'hero' => ['label_en' => 'Hero', 'label_ar' => 'بطل', 'category' => 'layout', 'icon' => 'fa-panorama'],
            'about' => ['label_en' => 'About', 'label_ar' => 'من نحن', 'category' => 'content', 'icon' => 'fa-circle-info'],
            'services' => ['label_en' => 'Services', 'label_ar' => 'خدمات', 'category' => 'content', 'icon' => 'fa-briefcase'],
            'features' => ['label_en' => 'Features', 'label_ar' => 'مميزات', 'category' => 'content', 'icon' => 'fa-stars'],
            'counters' => ['label_en' => 'Counters', 'label_ar' => 'عدادات', 'category' => 'content', 'icon' => 'fa-hashtag'],
            'cta' => ['label_en' => 'CTA', 'label_ar' => 'دعوة لاتخاذ إجراء', 'category' => 'layout', 'icon' => 'fa-bullhorn'],
            'team' => ['label_en' => 'Team', 'label_ar' => 'الفريق', 'category' => 'content', 'icon' => 'fa-users'],
            'testimonials' => ['label_en' => 'Testimonials', 'label_ar' => 'شهادات', 'category' => 'content', 'icon' => 'fa-quote-left'],
            'faq' => ['label_en' => 'FAQ', 'label_ar' => 'أسئلة شائعة', 'category' => 'content', 'icon' => 'fa-circle-question'],
            'pricing' => ['label_en' => 'Pricing', 'label_ar' => 'أسعار', 'category' => 'commerce', 'icon' => 'fa-tags'],
            'blog' => ['label_en' => 'Blog', 'label_ar' => 'مدونة', 'category' => 'content', 'icon' => 'fa-newspaper'],
            'news' => ['label_en' => 'News', 'label_ar' => 'أخبار', 'category' => 'content', 'icon' => 'fa-rss'],
            'careers' => ['label_en' => 'Careers', 'label_ar' => 'وظائف', 'category' => 'content', 'icon' => 'fa-user-tie'],
            'jobs' => ['label_en' => 'Jobs List', 'label_ar' => 'قائمة الوظائف', 'category' => 'careers', 'icon' => 'fa-list'],
            'featured_jobs' => ['label_en' => 'Featured Jobs', 'label_ar' => 'وظائف مميزة', 'category' => 'careers', 'icon' => 'fa-star'],
            'job_categories' => ['label_en' => 'Job Categories', 'label_ar' => 'تصنيفات الوظائف', 'category' => 'careers', 'icon' => 'fa-folder'],
            'job_search' => ['label_en' => 'Job Search', 'label_ar' => 'بحث الوظائف', 'category' => 'careers', 'icon' => 'fa-magnifying-glass'],
            'cta_apply' => ['label_en' => 'CTA Apply', 'label_ar' => 'تقديم الآن', 'category' => 'careers', 'icon' => 'fa-paper-plane'],
            'recruiter_team' => ['label_en' => 'Recruiter Team', 'label_ar' => 'فريق التوظيف', 'category' => 'careers', 'icon' => 'fa-people-group'],
            'employer_dashboard' => ['label_en' => 'Employer Dashboard', 'label_ar' => 'لوحة صاحب العمل', 'category' => 'portals', 'icon' => 'fa-building'],
            'customer_dashboard' => ['label_en' => 'Customer Dashboard', 'label_ar' => 'لوحة العميل', 'category' => 'portals', 'icon' => 'fa-user'],
            'outstanding_invoices' => ['label_en' => 'Outstanding Invoices', 'label_ar' => 'فواتير مستحقة', 'category' => 'portals', 'icon' => 'fa-file-invoice'],
            'active_contracts' => ['label_en' => 'Active Contracts', 'label_ar' => 'عقود نشطة', 'category' => 'portals', 'icon' => 'fa-file-contract'],
            'recent_requests' => ['label_en' => 'Recent Requests', 'label_ar' => 'طلبات حديثة', 'category' => 'portals', 'icon' => 'fa-inbox'],
            'recruitment_status' => ['label_en' => 'Recruitment Status', 'label_ar' => 'حالة التوظيف', 'category' => 'portals', 'icon' => 'fa-user-check'],
            'candidate_pipeline' => ['label_en' => 'Candidate Pipeline', 'label_ar' => 'مسار المرشحين', 'category' => 'portals', 'icon' => 'fa-stream'],
            'portal_documents' => ['label_en' => 'Documents', 'label_ar' => 'مستندات', 'category' => 'portals', 'icon' => 'fa-folder-open'],
            'portal_payments' => ['label_en' => 'Payments', 'label_ar' => 'مدفوعات', 'category' => 'portals', 'icon' => 'fa-credit-card'],
            'portal_support_tickets' => ['label_en' => 'Support Tickets', 'label_ar' => 'تذاكر الدعم', 'category' => 'portals', 'icon' => 'fa-headset'],
            'portal_notifications' => ['label_en' => 'Notifications', 'label_ar' => 'إشعارات', 'category' => 'portals', 'icon' => 'fa-bell'],
            'portal_calendar' => ['label_en' => 'Calendar', 'label_ar' => 'تقويم', 'category' => 'portals', 'icon' => 'fa-calendar'],
            'invoice_summary' => ['label_en' => 'Invoice Summary', 'label_ar' => 'ملخص الفواتير', 'category' => 'portals', 'icon' => 'fa-file-invoice-dollar'],
            'contract_summary' => ['label_en' => 'Contract Summary', 'label_ar' => 'ملخص العقود', 'category' => 'portals', 'icon' => 'fa-file-signature'],
            'recruitment_progress' => ['label_en' => 'Recruitment Progress', 'label_ar' => 'تقدم التوظيف', 'category' => 'portals', 'icon' => 'fa-chart-line'],
            'recent_candidates' => ['label_en' => 'Recent Candidates', 'label_ar' => 'مرشحون حديثاً', 'category' => 'portals', 'icon' => 'fa-user-plus'],
            'pending_approvals' => ['label_en' => 'Pending Approvals', 'label_ar' => 'موافقات معلقة', 'category' => 'portals', 'icon' => 'fa-clipboard-check'],
            'payment_status' => ['label_en' => 'Payment Status', 'label_ar' => 'حالة الدفع', 'category' => 'portals', 'icon' => 'fa-money-check'],
            'support_widget' => ['label_en' => 'Support Widget', 'label_ar' => 'ويدجت الدعم', 'category' => 'portals', 'icon' => 'fa-life-ring'],
            'documents_widget' => ['label_en' => 'Documents Widget', 'label_ar' => 'ويدجت المستندات', 'category' => 'portals', 'icon' => 'fa-folder'],
            'statistics_cards' => ['label_en' => 'Statistics Cards', 'label_ar' => 'بطاقات إحصائيات', 'category' => 'portals', 'icon' => 'fa-chart-simple'],
            'timeline' => ['label_en' => 'Timeline', 'label_ar' => 'الجدول الزمني', 'category' => 'portals', 'icon' => 'fa-timeline'],
            'quick_actions' => ['label_en' => 'Quick Actions', 'label_ar' => 'إجراءات سريعة', 'category' => 'portals', 'icon' => 'fa-bolt'],
            'service_packages' => ['label_en' => 'Service Packages', 'label_ar' => 'باقات الخدمات', 'category' => 'services', 'icon' => 'fa-box'],
            'online_booking' => ['label_en' => 'Online Booking', 'label_ar' => 'حجز إلكتروني', 'category' => 'services', 'icon' => 'fa-calendar-check'],
            'recruitment_wizard' => ['label_en' => 'Recruitment Wizard', 'label_ar' => 'معالج التوظيف', 'category' => 'services', 'icon' => 'fa-magic'],
            'pricing_cards' => ['label_en' => 'Pricing Cards', 'label_ar' => 'بطاقات الأسعار', 'category' => 'services', 'icon' => 'fa-tag'],
            'service_timeline' => ['label_en' => 'Service Timeline', 'label_ar' => 'الجدول الزمني للخدمة', 'category' => 'services', 'icon' => 'fa-hourglass'],
            'appointment_calendar' => ['label_en' => 'Appointment Calendar', 'label_ar' => 'تقويم المواعيد', 'category' => 'services', 'icon' => 'fa-calendar-days'],
            'customer_reviews' => ['label_en' => 'Customer Reviews', 'label_ar' => 'آراء العملاء', 'category' => 'services', 'icon' => 'fa-star-half-stroke'],
            'cta_banner' => ['label_en' => 'CTA Banner', 'label_ar' => 'شريط دعوة', 'category' => 'services', 'icon' => 'fa-flag'],
            'online_contact_form' => ['label_en' => 'Contact Form', 'label_ar' => 'نموذج تواصل', 'category' => 'services', 'icon' => 'fa-envelope-open-text'],
            'contact' => ['label_en' => 'Contact', 'label_ar' => 'تواصل', 'category' => 'forms', 'icon' => 'fa-envelope'],
            'gallery' => ['label_en' => 'Gallery', 'label_ar' => 'معرض', 'category' => 'media', 'icon' => 'fa-images'],
            'partners' => ['label_en' => 'Partners', 'label_ar' => 'شركاء', 'category' => 'content', 'icon' => 'fa-handshake'],
            'brands' => ['label_en' => 'Brands', 'label_ar' => 'علامات', 'category' => 'content', 'icon' => 'fa-copyright'],
            'map' => ['label_en' => 'Map', 'label_ar' => 'خريطة', 'category' => 'embed', 'icon' => 'fa-map-location-dot'],
            'whatsapp' => ['label_en' => 'WhatsApp', 'label_ar' => 'واتساب', 'category' => 'embed', 'icon' => 'fa-whatsapp'],
            'forms' => ['label_en' => 'Forms', 'label_ar' => 'نماذج', 'category' => 'forms', 'icon' => 'fa-list-check'],
            'custom_html' => ['label_en' => 'Custom HTML', 'label_ar' => 'HTML مخصص', 'category' => 'advanced', 'icon' => 'fa-code'],
            'spacer' => ['label_en' => 'Spacer', 'label_ar' => 'مسافة', 'category' => 'layout', 'icon' => 'fa-arrows-up-down'],
            'divider' => ['label_en' => 'Divider', 'label_ar' => 'فاصل', 'category' => 'layout', 'icon' => 'fa-minus'],
            'video' => ['label_en' => 'Video', 'label_ar' => 'فيديو', 'category' => 'media', 'icon' => 'fa-video'],
            'image' => ['label_en' => 'Image', 'label_ar' => 'صورة', 'category' => 'media', 'icon' => 'fa-image'],
            'slider' => ['label_en' => 'Slider', 'label_ar' => 'شريط صور', 'category' => 'media', 'icon' => 'fa-sliders'],
            'text' => ['label_en' => 'Text', 'label_ar' => 'نص', 'category' => 'content', 'icon' => 'fa-align-left'],
        ];
    }

    public static function isValid(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /** @return list<string> */
    public static function typeIds(): array
    {
        return array_keys(self::all());
    }

    /** @return array{title_en:string,title_ar:string,content_en:string,content_ar:string,settings:array<string,mixed>} */
    public static function defaults(string $type): array
    {
        $meta = self::all()[$type] ?? ['label_en' => $type, 'label_ar' => $type];

        return [
            'title_en' => (string) $meta['label_en'],
            'title_ar' => (string) $meta['label_ar'],
            'content_en' => '',
            'content_ar' => '',
            'settings' => match ($type) {
                'hero' => ['cta_label_en' => 'Get started', 'cta_label_ar' => 'ابدأ الآن', 'cta_url' => '#', 'overlay' => true],
                'counters' => ['items' => []],
                'pricing' => ['plans' => []],
                'faq' => ['items' => []],
                'gallery' => ['images' => []],
                'partners', 'brands' => ['logos' => []],
                'map' => ['embed_url' => '', 'height' => 360],
                'whatsapp' => ['phone' => '', 'message_en' => '', 'message_ar' => ''],
                'forms' => ['form_slug' => 'contact'],
                'spacer' => ['height' => 48],
                'divider' => ['style' => 'solid'],
                'video' => ['src' => '', 'poster' => '', 'autoplay' => false],
                'slider' => ['slides' => []],
                'custom_html' => ['allow_scripts' => false],
                'jobs' => ['limit' => 6],
                'featured_jobs' => ['limit' => 4],
                'job_search' => ['placeholder_en' => 'Search jobs…', 'placeholder_ar' => 'ابحث عن وظيفة…'],
                'cta_apply' => ['cta_url' => '/site/careers', 'cta_label_en' => 'View careers', 'cta_label_ar' => 'عرض الوظائف'],
                'recruiter_team' => ['members' => []],
                'employer_dashboard' => ['portal' => 'employer'],
                'customer_dashboard' => ['portal' => 'customer'],
                'outstanding_invoices' => ['limit' => 5],
                'active_contracts' => ['limit' => 5],
                'recent_requests' => ['limit' => 5],
                'recruitment_status' => [],
                'candidate_pipeline' => ['limit' => 5],
                'portal_documents' => [],
                'portal_payments' => [],
                'portal_support_tickets' => [],
                'portal_notifications' => [],
                'portal_calendar' => [],
                'invoice_summary' => [],
                'contract_summary' => [],
                'recruitment_progress' => [],
                'recent_candidates' => [],
                'pending_approvals' => [],
                'payment_status' => [],
                'support_widget' => [],
                'documents_widget' => [],
                'statistics_cards' => [],
                'timeline' => [],
                'quick_actions' => [],
                default => [],
            },
        ];
    }
}
