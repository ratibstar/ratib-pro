-- =============================================================================
-- ONE-TIME: Remove infrastructure marketplace tables from the main RATEB Pro DB
-- Target: admin_out (or your former RATEB_PRO_DB_NAME / DB_NAME)
-- =============================================================================
--
-- Run ONLY after:
--   1. rateb_infra_* tables exist on admin_control_panel_db (see ALL_for_admin_control_panel_db.sql).
--   2. Application code uses CONTROL_PANEL_DB_NAME for infra PDO (DatabaseConnectionFactory default).
--   3. You have a backup if any app accidentally relied on rateb_infra_* on this database.
--
-- This does NOT drop anything on admin_control_panel_db.
-- =============================================================================

USE `admin_out`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `rateb_infra_job_logs`;
DROP TABLE IF EXISTS `rateb_infra_provisioning_jobs`;
DROP TABLE IF EXISTS `rateb_infra_provisioning_job`;
DROP TABLE IF EXISTS `rateb_infra_orders`;
DROP TABLE IF EXISTS `rateb_infra_services`;
DROP TABLE IF EXISTS `rateb_infra_provider_activations`;
DROP TABLE IF EXISTS `rateb_infra_provider_bindings`;
DROP TABLE IF EXISTS `rateb_infra_provider_binding`;
DROP TABLE IF EXISTS `rateb_infra_catalog_items`;
DROP TABLE IF EXISTS `rateb_infra_catalog_item`;
DROP TABLE IF EXISTS `rateb_infra_domain_search_cache`;
DROP TABLE IF EXISTS `rateb_infra_domain_search_rate`;
DROP TABLE IF EXISTS `rateb_infra_deployment_audits`;
DROP TABLE IF EXISTS `rateb_infra_audit_entries`;
DROP TABLE IF EXISTS `rateb_infra_secret_refs`;
DROP TABLE IF EXISTS `rateb_infra_worker_heartbeats`;

SET FOREIGN_KEY_CHECKS = 1;

-- Verify (should return empty):
-- SHOW TABLES LIKE 'rateb_infra_%';
