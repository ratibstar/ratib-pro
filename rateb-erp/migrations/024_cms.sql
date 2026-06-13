-- RATEB ERP — Marketing Website + CMS
SET NAMES utf8mb4;

-- Pages & content builder
CREATE TABLE IF NOT EXISTS rateb_cms_pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(120) NOT NULL,
    title_en VARCHAR(255) NOT NULL DEFAULT '',
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    content_en MEDIUMTEXT NULL,
    content_ar MEDIUMTEXT NULL,
    template VARCHAR(60) NOT NULL DEFAULT 'default',
    status ENUM('draft','published','scheduled') NOT NULL DEFAULT 'published',
    published_at DATETIME NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cms_page_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_slug VARCHAR(120) NOT NULL,
    section_key VARCHAR(80) NOT NULL,
    title_en VARCHAR(255) NOT NULL DEFAULT '',
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    body_en MEDIUMTEXT NULL,
    body_ar MEDIUMTEXT NULL,
    settings_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cms_section_page (page_slug, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_id INT UNSIGNED NOT NULL,
    block_type VARCHAR(60) NOT NULL DEFAULT 'text',
    title_en VARCHAR(255) NOT NULL DEFAULT '',
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    content_en MEDIUMTEXT NULL,
    content_ar MEDIUMTEXT NULL,
    icon VARCHAR(80) NULL,
    image_path VARCHAR(500) NULL,
    link_url VARCHAR(500) NULL,
    settings_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cms_block_section (section_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_menus (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(60) NOT NULL,
    name_en VARCHAR(120) NOT NULL DEFAULT '',
    name_ar VARCHAR(120) NOT NULL DEFAULT '',
    location VARCHAR(40) NOT NULL DEFAULT 'header',
    UNIQUE KEY uq_cms_menu_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_menu_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    menu_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED NULL,
    label_en VARCHAR(120) NOT NULL DEFAULT '',
    label_ar VARCHAR(120) NOT NULL DEFAULT '',
    url VARCHAR(500) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_cms_menu_item (menu_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_footer_columns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title_en VARCHAR(120) NOT NULL DEFAULT '',
    title_ar VARCHAR(120) NOT NULL DEFAULT '',
    links_json JSON NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- About us
CREATE TABLE IF NOT EXISTS rateb_cms_about (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    story_en MEDIUMTEXT NULL,
    story_ar MEDIUMTEXT NULL,
    vision_en TEXT NULL,
    vision_ar TEXT NULL,
    mission_en TEXT NULL,
    mission_ar TEXT NULL,
    values_json JSON NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_team_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL DEFAULT '',
    position_en VARCHAR(120) NOT NULL DEFAULT '',
    position_ar VARCHAR(120) NOT NULL DEFAULT '',
    bio_en TEXT NULL,
    bio_ar TEXT NULL,
    photo_path VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_timeline (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    year_label VARCHAR(20) NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    body_en TEXT NULL,
    body_ar TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Services
CREATE TABLE IF NOT EXISTS rateb_cms_service_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL DEFAULT '',
    icon VARCHAR(80) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_cms_svc_cat (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    slug VARCHAR(120) NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    summary_en TEXT NULL,
    summary_ar TEXT NULL,
    content_en MEDIUMTEXT NULL,
    content_ar MEDIUMTEXT NULL,
    icon VARCHAR(80) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('draft','published') NOT NULL DEFAULT 'published',
    UNIQUE KEY uq_cms_service_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Blog
CREATE TABLE IF NOT EXISTS rateb_cms_blog_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL DEFAULT '',
    UNIQUE KEY uq_cms_blog_cat (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_blog_tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    name_en VARCHAR(80) NOT NULL,
    name_ar VARCHAR(80) NOT NULL DEFAULT '',
    UNIQUE KEY uq_cms_blog_tag (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_blog_authors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL DEFAULT '',
    email VARCHAR(180) NULL,
    bio_en TEXT NULL,
    bio_ar TEXT NULL,
    photo_path VARCHAR(500) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_blog_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    author_id INT UNSIGNED NULL,
    slug VARCHAR(160) NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    excerpt_en TEXT NULL,
    excerpt_ar TEXT NULL,
    content_en MEDIUMTEXT NULL,
    content_ar MEDIUMTEXT NULL,
    featured_image VARCHAR(500) NULL,
    status ENUM('draft','published','scheduled') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    meta_title_en VARCHAR(255) NULL,
    meta_title_ar VARCHAR(255) NULL,
    meta_description_en VARCHAR(500) NULL,
    meta_description_ar VARCHAR(500) NULL,
    views_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cms_article_slug (slug),
    KEY idx_cms_article_status (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_article_tags (
    article_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (article_id, tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- FAQ
CREATE TABLE IF NOT EXISTS rateb_cms_faq_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_cms_faq_cat (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_faqs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    question_en VARCHAR(500) NOT NULL,
    question_ar VARCHAR(500) NOT NULL DEFAULT '',
    answer_en MEDIUMTEXT NULL,
    answer_ar MEDIUMTEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Testimonials & sliders
CREATE TABLE IF NOT EXISTS rateb_cms_testimonials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name_en VARCHAR(120) NOT NULL,
    customer_name_ar VARCHAR(120) NOT NULL DEFAULT '',
    position_en VARCHAR(120) NOT NULL DEFAULT '',
    position_ar VARCHAR(120) NOT NULL DEFAULT '',
    company_en VARCHAR(120) NOT NULL DEFAULT '',
    company_ar VARCHAR(120) NOT NULL DEFAULT '',
    quote_en TEXT NOT NULL,
    quote_ar TEXT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    photo_path VARCHAR(500) NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_slides (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title_en VARCHAR(255) NOT NULL DEFAULT '',
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    subtitle_en VARCHAR(500) NULL,
    subtitle_ar VARCHAR(500) NULL,
    image_path VARCHAR(500) NULL,
    video_url VARCHAR(500) NULL,
    cta_label_en VARCHAR(80) NULL,
    cta_label_ar VARCHAR(80) NULL,
    cta_url VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact & leads
CREATE TABLE IF NOT EXISTS rateb_cms_contact_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) NULL,
    phone VARCHAR(60) NULL,
    address_en TEXT NULL,
    address_ar TEXT NULL,
    working_hours_en TEXT NULL,
    working_hours_ar TEXT NULL,
    social_json JSON NULL,
    map_embed TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_offices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL DEFAULT '',
    address_en TEXT NULL,
    address_ar TEXT NULL,
    phone VARCHAR(60) NULL,
    map_url VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_type ENUM('contact','demo','quote','newsletter') NOT NULL DEFAULT 'contact',
    name VARCHAR(180) NOT NULL,
    email VARCHAR(180) NOT NULL,
    phone VARCHAR(60) NULL,
    company VARCHAR(180) NULL,
    message TEXT NULL,
    status ENUM('new','contacted','qualified','won','lost') NOT NULL DEFAULT 'new',
    assigned_user_id INT UNSIGNED NULL,
    source_page VARCHAR(120) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cms_lead_type_status (lead_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_lead_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lead_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    note TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cms_lead_note (lead_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_newsletter_subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(180) NOT NULL,
    name VARCHAR(180) NULL,
    segment VARCHAR(80) NULL DEFAULT 'general',
    status ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cms_newsletter_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_newsletter_segments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL DEFAULT '',
    description_en TEXT NULL,
    description_ar TEXT NULL,
    UNIQUE KEY uq_cms_segment (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SEO & analytics
CREATE TABLE IF NOT EXISTS rateb_cms_seo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_slug VARCHAR(120) NOT NULL,
    meta_title_en VARCHAR(255) NULL,
    meta_title_ar VARCHAR(255) NULL,
    meta_description_en VARCHAR(500) NULL,
    meta_description_ar VARCHAR(500) NULL,
    og_title_en VARCHAR(255) NULL,
    og_title_ar VARCHAR(255) NULL,
    og_description_en VARCHAR(500) NULL,
    og_description_ar VARCHAR(500) NULL,
    og_image VARCHAR(500) NULL,
    twitter_card VARCHAR(40) NULL DEFAULT 'summary_large_image',
    canonical_url VARCHAR(500) NULL,
    UNIQUE KEY uq_cms_seo_slug (page_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_path VARCHAR(500) NOT NULL,
    to_path VARCHAR(500) NOT NULL,
    status_code SMALLINT NOT NULL DEFAULT 301,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_cms_redirect_from (from_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_analytics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    google_analytics_id VARCHAR(60) NULL,
    google_tag_manager_id VARCHAR(60) NULL,
    meta_pixel_id VARCHAR(60) NULL,
    tiktok_pixel_id VARCHAR(60) NULL,
    custom_head_code TEXT NULL,
    custom_body_code TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_robots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Media library
CREATE TABLE IF NOT EXISTS rateb_cms_media_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    name_en VARCHAR(120) NOT NULL,
    name_ar VARCHAR(120) NOT NULL DEFAULT '',
    UNIQUE KEY uq_cms_media_cat (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    alt_en VARCHAR(255) NULL,
    alt_ar VARCHAR(255) NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cms_media_cat (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Theme & visitors
CREATE TABLE IF NOT EXISTS rateb_cms_theme (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    primary_color VARCHAR(20) NULL DEFAULT '#1a5fb4',
    secondary_color VARCHAR(20) NULL DEFAULT '#3584e4',
    font_family VARCHAR(120) NULL DEFAULT 'Tajawal',
    logo_path VARCHAR(500) NULL,
    favicon_path VARCHAR(500) NULL,
    custom_css MEDIUMTEXT NULL,
    custom_js MEDIUMTEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_visitors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visit_date DATE NOT NULL,
    page_views INT UNSIGNED NOT NULL DEFAULT 0,
    unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_cms_visitor_date (visit_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Knowledge base, help, partners, careers, status
CREATE TABLE IF NOT EXISTS rateb_cms_kb_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(160) NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    content_en MEDIUMTEXT NULL,
    content_ar MEDIUMTEXT NULL,
    category VARCHAR(80) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('draft','published') NOT NULL DEFAULT 'published',
    UNIQUE KEY uq_cms_kb_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_help_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(160) NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    content_en MEDIUMTEXT NULL,
    content_ar MEDIUMTEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('draft','published') NOT NULL DEFAULT 'published',
    UNIQUE KEY uq_cms_help_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_partners (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name_en VARCHAR(180) NOT NULL,
    name_ar VARCHAR(180) NOT NULL DEFAULT '',
    logo_path VARCHAR(500) NULL,
    website_url VARCHAR(500) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_careers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(160) NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    department_en VARCHAR(120) NULL,
    department_ar VARCHAR(120) NULL,
    location_en VARCHAR(120) NULL,
    location_ar VARCHAR(120) NULL,
    description_en MEDIUMTEXT NULL,
    description_ar MEDIUMTEXT NULL,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    UNIQUE KEY uq_cms_career_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_cms_system_status (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    component_en VARCHAR(120) NOT NULL,
    component_ar VARCHAR(120) NOT NULL DEFAULT '',
    status ENUM('operational','degraded','outage') NOT NULL DEFAULT 'operational',
    message_en TEXT NULL,
    message_ar TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions
INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View CMS', 'عرض نظام المحتوى', 'cms.view', 'cms', 'View marketing CMS', 'عرض نظام إدارة المحتوى'),
('Manage CMS', 'إدارة نظام المحتوى', 'cms.manage', 'cms', 'Manage marketing CMS content', 'إدارة محتوى الموقع التسويقي'),
('Manage CMS Leads', 'إدارة العملاء المحتملين', 'cms.leads', 'cms', 'Manage contact and demo leads', 'إدارة طلبات التواصل والعروض'),
('Manage CMS SEO', 'إدارة تحسين محركات البحث', 'cms.seo', 'cms', 'Manage SEO settings', 'إدارة إعدادات SEO'),
('Manage CMS Media', 'إدارة مكتبة الوسائط', 'cms.media', 'cms', 'Upload and manage media', 'رفع وإدارة الوسائط')
ON DUPLICATE KEY UPDATE name = VALUES(name), name_ar = VALUES(name_ar), module = VALUES(module);

INSERT INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM rateb_roles r
JOIN rateb_permissions p ON p.slug IN ('cms.view', 'cms.manage', 'cms.leads', 'cms.seo', 'cms.media')
WHERE r.slug = 'super-admin'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Seed pages
INSERT INTO rateb_cms_pages (slug, title_en, title_ar, template, status) VALUES
('home', 'Home', 'الرئيسية', 'home', 'published'),
('features', 'Features', 'المميزات', 'features', 'published'),
('solutions', 'Solutions', 'الحلول', 'solutions', 'published'),
('industries', 'Industries', 'القطاعات', 'industries', 'published'),
('pricing', 'Pricing', 'الأسعار', 'pricing', 'published'),
('request-demo', 'Request Demo', 'طلب عرض', 'form', 'published'),
('contact', 'Contact Us', 'اتصل بنا', 'contact', 'published'),
('about', 'About Us', 'من نحن', 'about', 'published'),
('faq', 'FAQ', 'الأسئلة الشائعة', 'faq', 'published'),
('blog', 'Blog', 'المدونة', 'blog', 'published'),
('services', 'Services', 'الخدمات', 'services', 'published'),
('reviews', 'Customer Reviews', 'آراء العملاء', 'reviews', 'published'),
('partners', 'Partners', 'الشركاء', 'partners', 'published'),
('careers', 'Careers', 'الوظائف', 'careers', 'published'),
('privacy', 'Privacy Policy', 'سياسة الخصوصية', 'legal', 'published'),
('terms', 'Terms & Conditions', 'الشروط والأحكام', 'legal', 'published'),
('cookies', 'Cookies Policy', 'سياسة ملفات تعريف الارتباط', 'legal', 'published'),
('system-status', 'System Status', 'حالة النظام', 'status', 'published'),
('help-center', 'Help Center', 'مركز المساعدة', 'help', 'published'),
('knowledge-base', 'Knowledge Base', 'قاعدة المعرفة', 'kb', 'published')
ON DUPLICATE KEY UPDATE title_en = VALUES(title_en), title_ar = VALUES(title_ar);

-- Seed menus
INSERT INTO rateb_cms_menus (slug, name_en, name_ar, location) VALUES
('main', 'Main Navigation', 'القائمة الرئيسية', 'header'),
('footer', 'Footer Navigation', 'قائمة التذييل', 'footer')
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en);

INSERT INTO rateb_cms_menu_items (menu_id, label_en, label_ar, url, sort_order) VALUES
((SELECT id FROM rateb_cms_menus WHERE slug='main' LIMIT 1), 'Home', 'الرئيسية', 'site', 1),
((SELECT id FROM rateb_cms_menus WHERE slug='main' LIMIT 1), 'Features', 'المميزات', 'site/features', 2),
((SELECT id FROM rateb_cms_menus WHERE slug='main' LIMIT 1), 'Solutions', 'الحلول', 'site/solutions', 3),
((SELECT id FROM rateb_cms_menus WHERE slug='main' LIMIT 1), 'Pricing', 'الأسعار', 'site/pricing', 4),
((SELECT id FROM rateb_cms_menus WHERE slug='main' LIMIT 1), 'Blog', 'المدونة', 'site/blog', 5),
((SELECT id FROM rateb_cms_menus WHERE slug='main' LIMIT 1), 'Contact', 'اتصل بنا', 'site/contact', 6);

-- Seed home sections
INSERT INTO rateb_cms_sections (page_slug, section_key, title_en, title_ar, body_en, body_ar, sort_order) VALUES
('home', 'hero', 'RATEB ERP — Smart Operations Platform', 'رتب ERP — منصة العمليات الذكية',
 'Unified procurement, inventory, contracts, and medical device management for healthcare and enterprise.', 
 'منصة موحدة للمشتريات والمخزون والعقود وإدارة الأجهزة الطبية للقطاع الصحي والمؤسسات.', 1),
('home', 'erp_overview', 'Complete ERP Overview', 'نظرة شاملة على النظام',
 'End-to-end visibility across procurement, warehouses, suppliers, assets, and compliance workflows.',
 'رؤية شاملة للمشتريات والمستودعات والموردين والأصول وسير عمل الامتثال.', 2),
('home', 'why_rateb', 'Why RATEB ERP', 'لماذا رتب ERP',
 'Built for Saudi healthcare and enterprise with multi-tenant SaaS, Arabic-first UX, and robust security.',
 'مصمم للقطاع الصحي السعودي والمؤسسات مع SaaS متعدد المستأجرين وواجهة عربية وأمان قوي.', 3),
('home', 'stats', 'Trusted by Growing Organizations', 'موثوق من مؤسسات نامية', '', '', 4),
('home', 'industries', 'Industries We Serve', 'القطاعات التي نخدمها', '', '', 5),
('home', 'testimonials', 'What Our Customers Say', 'ماذا يقول عملاؤنا', '', '', 6),
('home', 'pricing_preview', 'Flexible Plans', 'باقات مرنة', '', '', 7),
('home', 'latest_articles', 'Latest Insights', 'أحدث المقالات', '', '', 8),
('home', 'faq_preview', 'Common Questions', 'أسئلة شائعة', '', '', 9),
('home', 'contact_cta', 'Ready to Transform Operations?', 'جاهز لتحويل عملياتك؟',
 'Request a demo or talk to our team today.', 'اطلب عرضاً تجريبياً أو تحدث مع فريقنا اليوم.', 10);

-- Features page section
INSERT INTO rateb_cms_sections (page_slug, section_key, title_en, title_ar, sort_order) VALUES
('features', 'list', 'Platform Features', 'مميزات المنصة', 1)
ON DUPLICATE KEY UPDATE title_en = VALUES(title_en);

INSERT INTO rateb_cms_blocks (section_id, block_type, title_en, title_ar, content_en, content_ar, icon, sort_order)
SELECT s.id, 'feature', t.en, t.ar, t.desc_en, t.desc_ar, t.icon, t.ord
FROM rateb_cms_sections s
JOIN (
    SELECT 1 ord,'Procurement' en,'المشتريات' ar,'Purchase requests, RFQ, PO workflows.' desc_en,'طلبات الشراء وعروض الأسعار وأوامر الشراء.' desc_ar,'fa-cart-shopping' icon UNION ALL
    SELECT 2,'Inventory','المخزون','Warehouses, batches, FEFO/FIFO, transfers.','المستودعات والدفعات والتحويلات.','fa-warehouse' UNION ALL
    SELECT 3,'Suppliers','الموردين','Classification, KPI, evaluations, communications.','التصنيف ومؤشرات الأداء والتقييمات.','fa-truck' UNION ALL
    SELECT 4,'Contracts','العقود','Renewals, alerts, approval workflows.','التجديدات والتنبيهات وسير الموافقات.','fa-file-contract' UNION ALL
    SELECT 5,'Assets','الأصول','Lifecycle tracking and maintenance.','تتبع دورة الحياة والصيانة.','fa-boxes-stacked' UNION ALL
    SELECT 6,'Medical Devices','الأجهزة الطبية','Calibration, warranty, regulatory status.','المعايرة والضمان والامتثال.','fa-stethoscope' UNION ALL
    SELECT 7,'Reports','التقارير','Executive dashboards and exports.','لوحات تنفيذية وتصدير.','fa-chart-pie' UNION ALL
    SELECT 8,'Notifications','الإشعارات','Email, SMS, in-app alerts.','بريد ورسائل وتنبيهات داخلية.','fa-bell' UNION ALL
    SELECT 9,'Workflows','سير العمل','Configurable multi-step approvals.','موافقات متعددة الخطوات.','fa-diagram-project' UNION ALL
    SELECT 10,'Multi-Tenant SaaS','SaaS متعدد المستأجرين','Isolated tenants with plan limits.','عزل المستأجرين وحدود الباقات.','fa-cloud' UNION ALL
    SELECT 11,'Security','الأمان','RBAC, audit logs, 2FA, lockout.','صلاحيات وسجلات ومصادقة ثنائية.','fa-shield-halved' UNION ALL
    SELECT 12,'API','واجهة API','REST API for integrations.','REST API للتكامل.','fa-plug'
) t ON s.page_slug='features' AND s.section_key='list';

-- Solutions blocks
INSERT INTO rateb_cms_sections (page_slug, section_key, title_en, title_ar, sort_order) VALUES
('solutions', 'list', 'Solutions by Industry', 'حلول حسب القطاع', 1);

INSERT INTO rateb_cms_blocks (section_id, block_type, title_en, title_ar, content_en, content_ar, icon, sort_order)
SELECT s.id, 'solution', t.en, t.ar, t.desc_en, t.desc_ar, t.icon, t.ord
FROM rateb_cms_sections s
JOIN (
    SELECT 1 ord,'Healthcare' en,'الرعاية الصحية' ar,'Hospitals and clinics procurement compliance.' desc_en,'مشتريات ومخزون للمستشفيات والعيادات.' desc_ar,'fa-hospital' icon UNION ALL
    SELECT 2,'Medical Companies','شركات طبية','Device tracking and supplier governance.','تتبع الأجهزة وحوكمة الموردين.','fa-syringe' UNION ALL
    SELECT 3,'Warehouses','المستودعات','Multi-warehouse inventory control.','تحكم مخزون متعدد المستودعات.','fa-warehouse' UNION ALL
    SELECT 4,'Trading Companies','شركات تجارية','Purchase-to-pay automation.','أتمتة من الشراء للدفع.','fa-handshake' UNION ALL
    SELECT 5,'Contracting Companies','شركات مقاولات','Contract and asset lifecycle.','دورة حياة العقود والأصول.','fa-hard-hat' UNION ALL
    SELECT 6,'Government Entities','جهات حكومية','Audit-ready workflows and reporting.','سير عمل وتقارير جاهزة للتدقيق.','fa-landmark'
) t ON s.page_slug='solutions' AND s.section_key='list';

-- Stats counters on home
INSERT INTO rateb_cms_blocks (section_id, block_type, title_en, title_ar, content_en, content_ar, sort_order)
SELECT s.id, 'stat', t.en, t.ar, t.val, t.val, t.ord
FROM rateb_cms_sections s
JOIN (
    SELECT 1 ord,'Companies' en,'شركات' ar,'50+' val UNION ALL
    SELECT 2,'Warehouses','مستودعات','120+' UNION ALL
    SELECT 3,'Transactions','معاملات','1M+' UNION ALL
    SELECT 4,'Uptime','جاهزية','99.9%'
) t ON s.page_slug='home' AND s.section_key='stats';

-- Industry blocks on home
INSERT INTO rateb_cms_blocks (section_id, block_type, title_en, title_ar, icon, sort_order)
SELECT s.id, 'industry', t.en, t.ar, t.icon, t.ord
FROM rateb_cms_sections s
JOIN (
    SELECT 1 ord,'Healthcare' en,'الرعاية الصحية' ar,'fa-hospital' icon UNION ALL
    SELECT 2,'Medical','طبية','fa-syringe' UNION ALL
    SELECT 3,'Warehousing','مستودعات','fa-warehouse' UNION ALL
    SELECT 4,'Trading','تجارة','fa-handshake' UNION ALL
    SELECT 5,'Government','حكومي','fa-landmark'
) t ON s.page_slug='home' AND s.section_key='industries';

-- Default about, theme, analytics, robots, contact
INSERT INTO rateb_cms_about (story_en, story_ar, vision_en, vision_ar, mission_en, mission_ar, values_json) VALUES
('RATEB ERP was built to modernize procurement and inventory for Saudi organizations.',
 'تم بناء رتب ERP لتحديث المشتريات والمخزون للمؤسسات السعودية.',
 'To be the leading operations platform for healthcare and enterprise in the region.',
 'أن نكون المنصة الرائدة للعمليات في القطاع الصحي والمؤسسات بالمنطقة.',
 'Empower teams with transparent, automated, compliant operations.',
 'تمكين الفرق بعمليات شفافة ومؤتمتة ومتوافقة.',
 JSON_ARRAY(JSON_OBJECT('en','Innovation','ar','الابتكار'),JSON_OBJECT('en','Trust','ar','الثقة'),JSON_OBJECT('en','Excellence','ar','التميز')));

INSERT INTO rateb_cms_theme (primary_color, secondary_color) VALUES ('#1a5fb4', '#3584e4');
INSERT INTO rateb_cms_analytics (id) VALUES (1);
INSERT INTO rateb_cms_contact_settings (email, phone, address_en, address_ar, working_hours_en, working_hours_ar, social_json) VALUES
('info@ratib.sa', '+966 11 000 0000', 'Riyadh, Saudi Arabia', 'الرياض، المملكة العربية السعودية',
 'Sun–Thu 9:00–18:00', 'الأحد–الخميس ٩:٠٠–١٨:٠٠',
 JSON_OBJECT('linkedin','https://linkedin.com','twitter','https://twitter.com'));

INSERT INTO rateb_cms_robots (content) VALUES ('User-agent: *\nAllow: /\nSitemap: /rateb-erp/public/site/sitemap.xml');

INSERT INTO rateb_cms_system_status (component_en, component_ar, status) VALUES
('Web Application', 'تطبيق الويب', 'operational'),
('API', 'واجهة API', 'operational'),
('Database', 'قاعدة البيانات', 'operational');

INSERT INTO rateb_cms_newsletter_segments (slug, name_en, name_ar) VALUES
('general', 'General', 'عام'),
('product-updates', 'Product Updates', 'تحديثات المنتج')
ON DUPLICATE KEY UPDATE name_en = VALUES(name_en);

-- Sample FAQs
INSERT INTO rateb_cms_faq_categories (slug, name_en, name_ar, sort_order) VALUES
('general', 'General', 'عام', 1);

INSERT INTO rateb_cms_faqs (category_id, question_en, question_ar, answer_en, answer_ar, sort_order) VALUES
((SELECT id FROM rateb_cms_faq_categories WHERE slug='general' LIMIT 1),
 'What is RATEB ERP?', 'ما هو رتب ERP?',
 'A cloud ERP for procurement, inventory, contracts, and medical device management.',
 'نظام ERP سحابي للمشتريات والمخزون والعقود وإدارة الأجهزة الطبية.', 1),
((SELECT id FROM rateb_cms_faq_categories WHERE slug='general' LIMIT 1),
 'Is Arabic supported?', 'هل يدعم العربية?',
 'Yes — full Arabic RTL interface with English LTR support.',
 'نعم — واجهة عربية كاملة مع دعم الإنجليزية.', 2);

-- Sample testimonial
INSERT INTO rateb_cms_testimonials (customer_name_en, customer_name_ar, position_en, position_ar, company_en, company_ar, quote_en, quote_ar, rating, status, sort_order) VALUES
('Ahmed Al-Rashid', 'أحمد الراشد', 'Procurement Director', 'مدير المشتريات', 'Health Corp', 'شركة الصحة', 'RATEB ERP transformed our procurement cycle.', 'رتب ERP غيّر دورة المشتريات لدينا.', 5, 'approved', 1);

-- Sample slide
INSERT INTO rateb_cms_slides (title_en, title_ar, subtitle_en, subtitle_ar, cta_label_en, cta_label_ar, cta_url, sort_order, is_active) VALUES
('Smart ERP for Modern Operations', 'نظام ERP ذكي للعمليات الحديثة',
 'Procurement · Inventory · Contracts · Compliance', 'مشتريات · مخزون · عقود · امتثال',
 'Request Demo', 'اطلب عرضاً', 'site/request-demo', 1, 1);
