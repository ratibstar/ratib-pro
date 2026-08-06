-- RATEB ERP — Logistics Module Phase 2 status history
-- Additive only: CREATE TABLE IF NOT EXISTS.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_logistics_status_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NOT NULL,
    to_status VARCHAR(40) NOT NULL,
    reason VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_logistics_sh_entity (company_id, entity_type, entity_id, created_at),
    KEY idx_logistics_sh_company_created (company_id, created_at),
    CONSTRAINT fk_logistics_sh_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
