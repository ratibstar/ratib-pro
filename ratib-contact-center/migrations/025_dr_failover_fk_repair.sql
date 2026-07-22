-- RATEB Contact Center — 025 DR failover FK repair (idempotent)
-- Repairs partial 022 apply; safe when FK is already correct (no-op).

SET @drop_legacy = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rcc_failover_events'
      AND CONSTRAINT_NAME = 'fk_rcc_failover_cluster'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql_drop = IF(
    @drop_legacy > 0,
    'ALTER TABLE rcc_failover_events DROP FOREIGN KEY fk_rcc_failover_cluster',
    'SELECT 1'
);

PREPARE rcc_stmt_drop_failover_fk FROM @sql_drop;
EXECUTE rcc_stmt_drop_failover_fk;
DEALLOCATE PREPARE rcc_stmt_drop_failover_fk;

SET @has_canonical_fk = (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rcc_failover_events'
      AND CONSTRAINT_NAME = 'fk_rcc_failover_events_pbx_cluster'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql_add = IF(
    @has_canonical_fk = 0,
    'ALTER TABLE rcc_failover_events ADD CONSTRAINT fk_rcc_failover_events_pbx_cluster FOREIGN KEY (cluster_id) REFERENCES rcc_pbx_clusters (id) ON DELETE SET NULL',
    'SELECT 1'
);

PREPARE rcc_stmt_add_failover_fk FROM @sql_add;
EXECUTE rcc_stmt_add_failover_fk;
DEALLOCATE PREPARE rcc_stmt_add_failover_fk;
