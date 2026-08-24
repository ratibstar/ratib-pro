-- RATEB ERP — Help Assistant analytics + article metadata for chatbot
SET NAMES utf8mb4;

-- Extend help articles for chatbot knowledge fields (idempotent).
SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_help_articles' AND COLUMN_NAME = 'route_hint');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_help_articles ADD COLUMN route_hint VARCHAR(255) NULL AFTER audience', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_help_articles' AND COLUMN_NAME = 'keywords_json');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_help_articles ADD COLUMN keywords_json TEXT NULL AFTER route_hint', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_help_articles' AND COLUMN_NAME = 'related_json');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_help_articles ADD COLUMN related_json TEXT NULL AFTER keywords_json', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rateb_help_articles' AND COLUMN_NAME = 'module_slug');
SET @sql = IF(@col = 0, 'ALTER TABLE rateb_help_articles ADD COLUMN module_slug VARCHAR(80) NULL AFTER category_id, ADD INDEX idx_help_article_module (module_slug)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS rateb_help_chat_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    user_id INT UNSIGNED NOT NULL DEFAULT 0,
    event_type ENUM('ask','open_article','quick','unanswered','lang','clear') NOT NULL,
    locale VARCHAR(8) NOT NULL DEFAULT 'ar',
    module_slug VARCHAR(80) NULL,
    route_hint VARCHAR(255) NULL,
    query_text VARCHAR(500) NULL,
    article_slug VARCHAR(160) NULL,
    has_answer TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_help_chat_company_created (company_id, created_at),
    KEY idx_help_chat_type_created (event_type, created_at),
    KEY idx_help_chat_article (article_slug),
    KEY idx_help_chat_unanswered (has_answer, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_help_unanswered (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL DEFAULT 0,
    user_id INT UNSIGNED NOT NULL DEFAULT 0,
    locale VARCHAR(8) NOT NULL DEFAULT 'ar',
    module_slug VARCHAR(80) NULL,
    route_hint VARCHAR(255) NULL,
    question VARCHAR(500) NOT NULL,
    normalized_question VARCHAR(500) NOT NULL,
    hit_count INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('open','reviewed','resolved','ignored') NOT NULL DEFAULT 'open',
    last_seen_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_help_unanswered_norm (company_id, normalized_question(191)),
    KEY idx_help_unanswered_status (status, hit_count),
    KEY idx_help_unanswered_module (module_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
