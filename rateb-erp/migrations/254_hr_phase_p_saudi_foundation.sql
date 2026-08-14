-- Phase P — Saudi HR foundation ONLY (GOSI / WPS / employment fields).
-- NO external transmission. Connectors remain disabled until separate approval.

CREATE TABLE IF NOT EXISTS rateb_hr_saudi_employment_fields (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  employee_id INT UNSIGNED NOT NULL,
  gosi_number VARCHAR(64) NULL,
  gosi_subscription_status VARCHAR(32) NULL,
  wps_iban VARCHAR(64) NULL,
  wps_bank_code VARCHAR(32) NULL,
  nationality_code VARCHAR(8) NULL,
  iqama_number VARCHAR(64) NULL,
  iqama_expiry DATE NULL,
  mol_contract_number VARCHAR(64) NULL,
  saudi_notes TEXT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hr_saudi_emp (company_id, employee_id),
  KEY idx_hr_saudi_company (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_saudi_integration_audit (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  channel VARCHAR(16) NOT NULL,
  action VARCHAR(64) NOT NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'planned',
  payload_summary VARCHAR(500) NULL,
  external_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_hr_saudi_audit_company (company_id, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
