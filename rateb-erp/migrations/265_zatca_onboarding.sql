-- ZATCA (Fatoora) e-invoicing device onboarding — compliance + production CSIDs (265)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_zatca_connections (
    company_id INT UNSIGNED NOT NULL PRIMARY KEY,
    environment ENUM('developer','simulation','production') NOT NULL DEFAULT 'developer',
    egs_serial VARCHAR(190) NULL,
    status ENUM('not_linked','pending','linked','failed') NOT NULL DEFAULT 'not_linked',
    compliance_status ENUM('none','active','failed') NOT NULL DEFAULT 'none',
    compliance_request_id VARCHAR(64) NULL,
    compliance_certificate TEXT NULL,
    compliance_secret VARCHAR(255) NULL,
    production_status ENUM('none','active','failed') NOT NULL DEFAULT 'none',
    production_request_id VARCHAR(64) NULL,
    production_certificate TEXT NULL,
    production_secret VARCHAR(255) NULL,
    last_error VARCHAR(500) NULL,
    linked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_zatca_conn_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;