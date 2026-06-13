DROP TABLE IF EXISTS rateb_cron_health;
DROP TABLE IF EXISTS rateb_warehouse_transfers;
DROP TABLE IF EXISTS rateb_two_factor_backup_codes;
DROP TABLE IF EXISTS rateb_remember_tokens;

ALTER TABLE rateb_users DROP COLUMN failed_attempts;
ALTER TABLE rateb_users DROP COLUMN locked_until;

ALTER TABLE rateb_approval_instances DROP COLUMN due_at;
ALTER TABLE rateb_approval_instances DROP COLUMN escalated_at;
ALTER TABLE rateb_approval_instances DROP COLUMN escalation_count;

ALTER TABLE rateb_approval_workflow_steps DROP COLUMN sla_hours;

ALTER TABLE rateb_notification_queue DROP COLUMN next_retry_at;
ALTER TABLE rateb_notification_queue DROP COLUMN dead_letter_at;

ALTER TABLE rateb_contracts DROP COLUMN signature_status;
ALTER TABLE rateb_contracts DROP COLUMN signature_trail;
