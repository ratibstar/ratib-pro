-- PHASE WEBSITE-03 — Multi-tenant CMS isolation (additive catchup)
-- company_id = 0 → platform (rateb.sa). Agency rows use erp company id.
SET NAMES utf8mb4;

-- Helper pattern repeated per table: add company_id + index when missing.

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_pages' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_pages ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_pages_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_sections' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_sections ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_sections_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_blocks' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_blocks ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_blocks_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_menus' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_menus ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_menus_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_menu_items' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_menu_items ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_menu_items_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_footer_columns' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_footer_columns ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_footer_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_about' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_about ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_about_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_team_members' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_team_members ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_team_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_timeline' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_timeline ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_timeline_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_service_categories' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_service_categories ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_svc_cat_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_services' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_services ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_services_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_blog_categories' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_blog_categories ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_blog_cat_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_blog_tags' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_blog_tags ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_blog_tag_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_blog_authors' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_blog_authors ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_blog_author_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_blog_articles' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_blog_articles ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_blog_article_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_faq_categories' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_faq_categories ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_faq_cat_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_faqs' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_faqs ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_faqs_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_testimonials' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_testimonials ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_testimonials_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_slides' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_slides ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_slides_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_contact_settings' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_contact_settings ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_contact_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_offices' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_offices ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_offices_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_leads' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_leads ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_leads_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_newsletter_subscribers' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_newsletter_subscribers ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_newsletter_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_seo' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_seo ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_seo_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_redirects' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_redirects ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_redirects_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_analytics' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_analytics ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_analytics_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_robots' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_robots ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_robots_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_media' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_media ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_media_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_media_categories' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_media_categories ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_media_cat_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_theme' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_theme ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_theme_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_visitors' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_visitors ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_visitors_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_careers' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_careers ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_careers_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_partners' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_partners ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_partners_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_kb_articles' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_kb_articles ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_kb_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_help_articles' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_help_articles ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_help_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_cms_system_status' AND COLUMN_NAME = 'company_id');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_cms_system_status ADD COLUMN company_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER id, ADD INDEX idx_cms_status_company (company_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
