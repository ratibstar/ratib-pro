-- Phase 3 state-machine normalization.
-- Converts lowercase/legacy status values to strict uppercase lifecycle values.

UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'QUEUED' WHERE UPPER(`status`) IN ('QUEUED', 'PENDING');
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'RUNNING' WHERE UPPER(`status`) IN ('RUNNING', 'PROCESSING');
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'RETRYING' WHERE UPPER(`status`) IN ('RETRYING', 'RETRY_SCHEDULED');
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'COMPLETED' WHERE UPPER(`status`) = 'COMPLETED';
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'DEAD_LETTER' WHERE UPPER(`status`) IN ('DEAD_LETTER', 'DEADLETTER');
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'FAILED' WHERE UPPER(`status`) = 'FAILED';
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'RECONCILING' WHERE UPPER(`status`) = 'RECONCILING';
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'CANCELLED' WHERE UPPER(`status`) = 'CANCELLED';
UPDATE `rateb_infra_provisioning_jobs` SET `status` = 'WAITING_EXTERNAL' WHERE UPPER(`status`) IN ('WAITING_EXTERNAL', 'WAITING');

