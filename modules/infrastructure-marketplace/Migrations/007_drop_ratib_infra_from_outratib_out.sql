-- =============================================================================
-- ONE-TIME: Remove infrastructure marketplace tables from the main Ratib Pro DB
-- Target: outratib_out (or your former RATIB_PRO_DB_NAME / DB_NAME)
-- =============================================================================
--
-- Run ONLY after:
--   1. ratib_infra_* tables exist on outratib_control_panel_db (see ALL_for_outratib_control_panel_db.sql).
--   2. Application code uses CONTROL_PANEL_DB_NAME for infra PDO (DatabaseConnectionFactory default).
--   3. You have a backup if any app accidentally relied on ratib_infra_* on this database.
--
-- This does NOT drop anything on outratib_control_panel_db.
-- =============================================================================

USE `outratib_out`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `ratib_infra_job_logs`;
DROP TABLE IF EXISTS `ratib_infra_provisioning_jobs`;
DROP TABLE IF EXISTS `ratib_infra_provisioning_job`;
DROP TABLE IF EXISTS `ratib_infra_orders`;
DROP TABLE IF EXISTS `ratib_infra_services`;
DROP TABLE IF EXISTS `ratib_infra_provider_activations`;
DROP TABLE IF EXISTS `ratib_infra_provider_bindings`;
DROP TABLE IF EXISTS `ratib_infra_provider_binding`;
DROP TABLE IF EXISTS `ratib_infra_catalog_items`;
DROP TABLE IF EXISTS `ratib_infra_catalog_item`;
DROP TABLE IF EXISTS `ratib_infra_domain_search_cache`;
DROP TABLE IF EXISTS `ratib_infra_domain_search_rate`;
DROP TABLE IF EXISTS `ratib_infra_deployment_audits`;
DROP TABLE IF EXISTS `ratib_infra_audit_entries`;
DROP TABLE IF EXISTS `ratib_infra_secret_refs`;
DROP TABLE IF EXISTS `ratib_infra_worker_heartbeats`;

SET FOREIGN_KEY_CHECKS = 1;

-- Verify (should return empty):
-- SHOW TABLES LIKE 'ratib_infra_%';
