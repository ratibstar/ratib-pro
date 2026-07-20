-- Phase I.2 — Mobile push delivery outbox (ERP-owned)
-- Content remains rateb_notifications; this table is delivery only.

CREATE TABLE IF NOT EXISTS rateb_mobile_push_outbox (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL DEFAULT 0,
    client_app VARCHAR(32) NOT NULL,
    notification_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    data_json TEXT NULL,
    status ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(512) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    UNIQUE KEY uq_push_outbox_delivery (
        notification_id,
        client_app,
        user_id
    ),
    KEY idx_push_outbox_pending (status, created_at),
    KEY idx_push_outbox_company (company_id, status),
    KEY idx_push_outbox_user (company_id, user_id, status),
    CONSTRAINT fk_push_outbox_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
