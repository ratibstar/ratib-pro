-- POS supervisor biometric approval requests + single-use grants
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_pos_approval_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    register_session_id INT UNSIGNED NULL,
    action_type VARCHAR(64) NOT NULL,
    payload_json JSON NULL,
    requested_by INT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    consumed_at DATETIME NULL,
    INDEX idx_pos_approval_company (company_id, created_at),
    INDEX idx_pos_approval_status (status, created_at),
    CONSTRAINT fk_pos_approval_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_approval_grants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id BIGINT UNSIGNED NOT NULL,
    supervisor_user_id INT UNSIGNED NOT NULL,
    biometric_method VARCHAR(32) NOT NULL DEFAULT 'webauthn',
    token_hash CHAR(64) NOT NULL,
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    UNIQUE KEY uq_pos_approval_grant_token (token_hash),
    INDEX idx_pos_approval_grant_request (request_id),
    CONSTRAINT fk_pos_approval_grant_request FOREIGN KEY (request_id) REFERENCES rateb_pos_approval_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
