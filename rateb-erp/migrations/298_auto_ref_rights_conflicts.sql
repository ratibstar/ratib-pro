-- Records conflicting Gulf rights. Does not change Phase 1–5 tables.
-- Rollback after an approved backup:
-- DROP TABLE IF EXISTS rateb_auto_ref_rights_conflicts;
-- ALTER TABLE rateb_auto_ref_data_rights DROP COLUMN IF EXISTS data_category;

SET NAMES utf8mb4;

ALTER TABLE rateb_auto_ref_data_rights
    ADD COLUMN IF NOT EXISTS data_category VARCHAR(24) NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS rateb_auto_ref_rights_conflicts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id INT UNSIGNED NOT NULL,
    left_rights_id INT UNSIGNED NOT NULL,
    right_rights_id INT UNSIGNED NOT NULL,
    market_code VARCHAR(8) NOT NULL,
    data_category VARCHAR(24) NOT NULL,
    detected_on DATE NOT NULL,
    evidence VARCHAR(300) NOT NULL,
    UNIQUE KEY uq_auto_ref_rights_conflict (left_rights_id, right_rights_id, market_code, data_category),
    CONSTRAINT fk_auto_ref_conflict_provider FOREIGN KEY (provider_id) REFERENCES rateb_auto_ref_data_providers(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_conflict_left FOREIGN KEY (left_rights_id) REFERENCES rateb_auto_ref_data_rights(id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT fk_auto_ref_conflict_right FOREIGN KEY (right_rights_id) REFERENCES rateb_auto_ref_data_rights(id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
