-- RATEB ERP — Newsletter campaigns
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_cms_newsletter_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_en VARCHAR(255) NOT NULL DEFAULT '',
    subject_ar VARCHAR(255) NOT NULL DEFAULT '',
    body_html_en MEDIUMTEXT NULL,
    body_html_ar MEDIUMTEXT NULL,
    segment_slug VARCHAR(80) NULL DEFAULT 'general',
    status ENUM('draft','scheduled','sending','sent','failed') NOT NULL DEFAULT 'draft',
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    sent_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
