<?php
-- RATEB ERP — In-app Help Center schema (admin CMS ready; runtime content may use file catalog)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_help_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL,
    title_en VARCHAR(180) NOT NULL,
    title_ar VARCHAR(180) NOT NULL DEFAULT '',
    description_en VARCHAR(500) NULL,
    description_ar VARCHAR(500) NULL,
    icon VARCHAR(80) NOT NULL DEFAULT 'fa-circle-question',
    accent VARCHAR(40) NOT NULL DEFAULT 'sky',
    module_gate VARCHAR(80) NULL,
    audience ENUM('all','user','manager','admin') NOT NULL DEFAULT 'all',
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_help_cat_slug (slug),
    KEY idx_help_cat_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_help_articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    slug VARCHAR(160) NOT NULL,
    title_en VARCHAR(255) NOT NULL,
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    summary_en VARCHAR(500) NULL,
    summary_ar VARCHAR(500) NULL,
    body_json_en MEDIUMTEXT NULL,
    body_json_ar MEDIUMTEXT NULL,
    difficulty ENUM('beginner','intermediate','advanced') NOT NULL DEFAULT 'beginner',
    minutes SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    icon VARCHAR(80) NOT NULL DEFAULT 'fa-circle-question',
    audience ENUM('all','user','manager','admin') NOT NULL DEFAULT 'all',
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_help_article_slug (slug),
    KEY idx_help_article_cat (category_id),
    KEY idx_help_article_status_sort (status, sort_order),
    CONSTRAINT fk_help_article_category FOREIGN KEY (category_id) REFERENCES rateb_help_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_help_keywords (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NOT NULL,
    keyword VARCHAR(120) NOT NULL,
    locale ENUM('ar','en','any') NOT NULL DEFAULT 'any',
    KEY idx_help_kw_article (article_id),
    KEY idx_help_kw_keyword (keyword),
    CONSTRAINT fk_help_kw_article FOREIGN KEY (article_id) REFERENCES rateb_help_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_help_related (
    article_id INT UNSIGNED NOT NULL,
    related_article_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (article_id, related_article_id),
    CONSTRAINT fk_help_rel_article FOREIGN KEY (article_id) REFERENCES rateb_help_articles (id) ON DELETE CASCADE,
    CONSTRAINT fk_help_rel_related FOREIGN KEY (related_article_id) REFERENCES rateb_help_articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_help_faqs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    question_en VARCHAR(500) NOT NULL,
    question_ar VARCHAR(500) NOT NULL DEFAULT '',
    answer_en MEDIUMTEXT NULL,
    answer_ar MEDIUMTEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
    KEY idx_help_faq_cat (category_id),
    CONSTRAINT fk_help_faq_category FOREIGN KEY (category_id) REFERENCES rateb_help_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rateb_permissions (name, name_ar, slug, module, description, description_ar) VALUES
('View Help Center', 'عرض مركز المساعدة', 'help.view', 'help', 'Access in-app Help Center', 'الوصول لمركز المساعدة داخل النظام'),
('Manage Help Center', 'إدارة مركز المساعدة', 'help.manage', 'help', 'Manage Help Center content', 'إدارة محتوى مركز المساعدة')
ON DUPLICATE KEY UPDATE name_ar = VALUES(name_ar), description_ar = VALUES(description_ar);

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
CROSS JOIN rateb_permissions p
WHERE r.slug = 'super-admin'
  AND p.slug IN ('help.view', 'help.manage');

INSERT IGNORE INTO rateb_role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM rateb_roles r
JOIN rateb_permissions p ON p.slug = 'help.view'
WHERE r.slug IN ('company-full-access', 'company-admin', 'company-user');
