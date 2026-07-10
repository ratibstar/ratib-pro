-- RATEB ERP — Enterprise offline entity cursors (Phase 2A)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_offline_entity_cursors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    entity_type VARCHAR(64) NOT NULL,
    cursor_token VARCHAR(128) NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_offline_cursor_scope (company_id, branch_id, entity_type),
    INDEX idx_offline_cursor_entity (company_id, entity_type),
    CONSTRAINT fk_offline_cursor_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
