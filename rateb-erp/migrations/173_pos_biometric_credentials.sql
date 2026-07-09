-- ERP biometric credentials for POS gate (WebAuthn + face templates)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_webauthn_credentials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    credential_id VARBINARY(512) NOT NULL,
    public_key TEXT NULL,
    sign_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_webauthn_cred (user_id, credential_id(255)),
    INDEX idx_webauthn_user (user_id),
    CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES rateb_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_face_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    template_data MEDIUMTEXT NOT NULL,
    confidence_threshold DECIMAL(5,4) NOT NULL DEFAULT 0.7500,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_face_user (user_id),
    CONSTRAINT fk_face_user FOREIGN KEY (user_id) REFERENCES rateb_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
