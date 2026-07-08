-- RATEB Platform Catalog — reference data (§4.1)
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS languages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    code VARCHAR(10) NOT NULL,
    name_native VARCHAR(100) NOT NULL,
    name_en VARCHAR(100) NOT NULL,
    direction ENUM('ltr', 'rtl') NOT NULL DEFAULT 'ltr',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME(6) NULL,
    deleted_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uk_languages_uuid (uuid),
    UNIQUE KEY uk_languages_code (code),
    KEY idx_languages_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS currencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    code CHAR(3) NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    decimal_places TINYINT NOT NULL DEFAULT 2,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME(6) NULL,
    deleted_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uk_currencies_uuid (uuid),
    UNIQUE KEY uk_currencies_code (code),
    KEY idx_currencies_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS units (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    code VARCHAR(20) NOT NULL,
    unit_type ENUM('quantity', 'weight', 'volume', 'length') NOT NULL DEFAULT 'quantity',
    decimal_places TINYINT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME(6) NULL,
    deleted_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uk_units_uuid (uuid),
    UNIQUE KEY uk_units_code (code),
    KEY idx_units_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS unit_translations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    unit_id BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10) NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at DATETIME(6) NULL,
    deleted_by BIGINT UNSIGNED NULL,
    UNIQUE KEY uk_unit_translations_uuid (uuid),
    UNIQUE KEY uk_unit_translations_unit_lang (unit_id, language_code),
    KEY idx_unit_translations_deleted (deleted_at),
    CONSTRAINT fk_unit_translations_unit FOREIGN KEY (unit_id) REFERENCES units (id),
    CONSTRAINT fk_unit_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO languages (uuid, code, name_native, name_en, direction, is_default, is_active)
SELECT 'a1000001-0000-4000-8000-000000000001', 'ar', 'العربية', 'Arabic', 'rtl', 1, 1
WHERE NOT EXISTS (SELECT 1 FROM languages WHERE code = 'ar' AND deleted_at IS NULL);

INSERT INTO languages (uuid, code, name_native, name_en, direction, is_default, is_active)
SELECT 'a1000001-0000-4000-8000-000000000002', 'en', 'English', 'English', 'ltr', 0, 1
WHERE NOT EXISTS (SELECT 1 FROM languages WHERE code = 'en' AND deleted_at IS NULL);

INSERT INTO currencies (uuid, code, symbol, decimal_places, is_default)
SELECT 'b1000001-0000-4000-8000-000000000001', 'SAR', 'ر.س', 2, 1
WHERE NOT EXISTS (SELECT 1 FROM currencies WHERE code = 'SAR' AND deleted_at IS NULL);

INSERT INTO units (uuid, code, unit_type, decimal_places, status)
SELECT 'c1000001-0000-4000-8000-000000000001', 'PCS', 'quantity', 0, 'active'
WHERE NOT EXISTS (SELECT 1 FROM units WHERE code = 'PCS' AND deleted_at IS NULL);

INSERT INTO unit_translations (uuid, unit_id, language_code, name)
SELECT 'd1000001-0000-4000-8000-000000000001', u.id, 'ar', 'قطعة'
FROM units u
WHERE u.code = 'PCS' AND u.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM unit_translations ut
      WHERE ut.unit_id = u.id AND ut.language_code = 'ar' AND ut.deleted_at IS NULL
  );

INSERT INTO unit_translations (uuid, unit_id, language_code, name)
SELECT 'd1000001-0000-4000-8000-000000000002', u.id, 'en', 'Piece'
FROM units u
WHERE u.code = 'PCS' AND u.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM unit_translations ut
      WHERE ut.unit_id = u.id AND ut.language_code = 'en' AND ut.deleted_at IS NULL
  );
