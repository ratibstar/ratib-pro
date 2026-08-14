-- Phase Q — HR Operations Automation (idempotent reminder ledger + settings).
-- No external connectors. No new notification/workflow engines.

CREATE TABLE IF NOT EXISTS rateb_hr_ops_reminder_ledger (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  company_id INT UNSIGNED NOT NULL,
  reminder_type VARCHAR(64) NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id INT UNSIGNED NOT NULL DEFAULT 0,
  period_key VARCHAR(32) NOT NULL,
  notification_id INT UNSIGNED NULL,
  meta_json TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_hr_ops_reminder (company_id, reminder_type, entity_type, entity_id, period_key),
  KEY idx_hr_ops_company_created (company_id, created_at),
  KEY idx_hr_ops_type (reminder_type, period_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rateb_hr_ops_automation_settings (
  company_id INT UNSIGNED NOT NULL,
  escalation_days SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  low_balance_days DECIMAL(5,1) NOT NULL DEFAULT 3.0,
  upcoming_leave_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  contract_milestones VARCHAR(32) NOT NULL DEFAULT '30,15,7',
  updated_at DATETIME NULL,
  PRIMARY KEY (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
