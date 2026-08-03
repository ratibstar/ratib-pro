-- Guest Menu (Waitr-style browse QR) — Phase F&B Layer 2 MVP
-- Public slug maps to company catalog (read-only browse mode).

CREATE TABLE IF NOT EXISTS rateb_guest_menu_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    branch_id INT UNSIGNED NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    public_slug VARCHAR(64) NOT NULL,
    mode ENUM('browse', 'order') NOT NULL DEFAULT 'browse',
    title_ar VARCHAR(255) NULL,
    title_en VARCHAR(255) NULL,
    welcome_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_guest_menu_company (company_id),
    UNIQUE KEY uq_guest_menu_slug (public_slug),
    KEY idx_guest_menu_enabled (is_enabled),
    CONSTRAINT fk_guest_menu_company FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
