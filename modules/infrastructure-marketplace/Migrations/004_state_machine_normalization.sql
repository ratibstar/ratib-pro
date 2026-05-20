-- Phase 3 state-machine normalization.
-- Converts lowercase/legacy status values to strict uppercase lifecycle values.

UPDATE `ratib_infra_provisioning_jobs` SET `status` = 'QUEUED' WHERE UPPER(`status`) IN ('QUEUED', 'PENDING');
UPDATE `ratib_infra_provisioning_jobs` SET `status` = 'RUNNING' WHERE UPPER(`status`) IN ('RUNNING', 'PROCESSING');
UPDATE `ratib_infra_provisioning_jobs` SET `status` = 'RETRYING' WHERE UPPER(`status`) IN ('RETRYING', 'RETRY_SCHEDULED');
UPDATE `ratib_infra_provisioning_jobs` SET `status` = 'COMPLETED' WHERE UPPER(`status`) = 'COMPLETED';
UPDATE `ratib_infra_provisioning_jobs` SET `status` = 'DEAD_LETTER' WHERE UPPER(`status`) IN ('DEAD_LETTER', 'DEADLETTER');
UPDATE `ratib_infra_provisioning_jobs` SET `status` = 'FAILED' WHERE UPPER(`status`) = 'FAILED';
UPDATE `ratib_infra_provisioning_jobs` SET `status` = 'RECONCILING' WHERE UPPER(`status`) = 'RECONCILING';
UPDATE `ratib_infra_provisioning_jobs` SET `status` = 'CANCELLED' WHERE UPPER(`status`) = 'CANCELLED';
UPDATE `ratib_infra_provisioning_jobs` SET `status` = 'WAITING_EXTERNAL' WHERE UPPER(`status`) IN ('WAITING_EXTERNAL', 'WAITING');

