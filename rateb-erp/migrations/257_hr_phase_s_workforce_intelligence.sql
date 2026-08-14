-- Phase S — Workforce Intelligence & Planning (additive only).
-- Planning targets for headcount gap analysis. No Employee/Payroll SoT changes.

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE TABLE IF NOT EXISTS rateb_hr_workforce_plan_targets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  period_year SMALLINT UNSIGNED NOT NULL,
  period_month TINYINT UNSIGNED NOT NULL DEFAULT 0,
  department_id INT UNSIGNED NULL,
  job_title_id INT UNSIGNED NULL,
  scope_key VARCHAR(64) NOT NULL DEFAULT 'all',
  target_headcount INT UNSIGNED NOT NULL DEFAULT 0,
  planned_hires INT UNSIGNED NULL,
  notes VARCHAR(500) NULL,
  created_by INT UNSIGNED NULL,
  updated_by INT UNSIGNED NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hr_wf_plan_scope (company_id, period_year, period_month, scope_key),
  KEY idx_hr_wf_plan_company (company_id, period_year, period_month),
  KEY idx_hr_wf_plan_dept (company_id, department_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
