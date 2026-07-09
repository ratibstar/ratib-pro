-- POS supervisor biometric approval requests + single-use grants
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_pos_approval_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    company_id INT UNSIGNED NOT NULL,
    register_session_id BIGINT UNSIGNED NULL,
    action_type VARCHAR(64) NOT NULL,
    payload_json JSON NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    consumed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pos_approval_company (company_id, status, created_at),
    KEY idx_pos_approval_requester (requested_by),
    CONSTRAINT fk_pos_approval_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_approval_requester FOREIGN KEY (requested_by) REFERENCES rateb_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_pos_approval_grants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    supervisor_user_id INT UNSIGNED NOT NULL,
    biometric_method VARCHAR(32) NOT NULL DEFAULT 'webauthn',
    token_hash CHAR(64) NOT NULL,
    verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pos_approval_token_hash (token_hash),
    KEY idx_pos_approval_grant_request (request_id),
    CONSTRAINT fk_pos_grant_request FOREIGN KEY (request_id) REFERENCES rateb_pos_approval_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_pos_grant_supervisor FOREIGN KEY (supervisor_user_id) REFERENCES rateb_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
