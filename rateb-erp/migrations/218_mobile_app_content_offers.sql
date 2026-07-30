-- RATEB Mobile App content pages + promotional offers (Agent Apps).
-- Logical: mobile_app_contents / mobile_app_offers

CREATE TABLE IF NOT EXISTS rateb_mobile_app_contents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    slug VARCHAR(64) NOT NULL,
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    title_en VARCHAR(255) NOT NULL DEFAULT '',
    body_ar MEDIUMTEXT NULL,
    body_en MEDIUMTEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mobile_content_company_slug (company_id, slug),
    KEY idx_mobile_content_active (company_id, is_active, sort_order),
    CONSTRAINT fk_mobile_content_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_mobile_app_offers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id INT UNSIGNED NOT NULL,
    title_ar VARCHAR(255) NOT NULL DEFAULT '',
    title_en VARCHAR(255) NOT NULL DEFAULT '',
    body_ar MEDIUMTEXT NULL,
    body_en MEDIUMTEXT NULL,
    image_path VARCHAR(500) NULL,
    discount_label VARCHAR(80) NOT NULL DEFAULT '',
    starts_at DATE NULL,
    ends_at DATE NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_mobile_offers_company (company_id, is_active, sort_order),
    KEY idx_mobile_offers_window (company_id, starts_at, ends_at),
    CONSTRAINT fk_mobile_offers_company
        FOREIGN KEY (company_id) REFERENCES rateb_companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
