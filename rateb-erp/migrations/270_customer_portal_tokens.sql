-- RATEB ERP — Customer Portal app tokens (separate from staff rateb_api_tokens)
-- Only SHA-256 token hashes are stored; plaintext tokens are returned once at login.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_customer_portal_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    portal_user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    revoke_reason ENUM('logout','password_change','account_disabled') NULL,
    UNIQUE KEY uq_cpt_token_hash (token_hash),
    INDEX idx_cpt_user_active (company_id, portal_user_id, revoked_at),
    INDEX idx_cpt_expires (expires_at),
    CONSTRAINT fk_cpt_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_cpt_portal_user FOREIGN KEY (portal_user_id) REFERENCES rateb_website_portal_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- App access requires explicit approval (NULL = not approved). No self-service path sets this.
ALTER TABLE rateb_website_portal_users ADD COLUMN app_access_approved_at DATETIME NULL;
