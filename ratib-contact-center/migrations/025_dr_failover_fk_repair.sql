-- RATIB Contact Center — 025 DR failover FK repair (idempotent)
-- Fixes partial 022 apply where ALTER used fk_rcc_failover_cluster (errno 121 on re-run).

ALTER TABLE rcc_failover_events DROP FOREIGN KEY fk_rcc_failover_cluster;

ALTER TABLE rcc_failover_events
    ADD CONSTRAINT fk_rcc_failover_events_pbx_cluster
    FOREIGN KEY (cluster_id) REFERENCES rcc_pbx_clusters (id) ON DELETE SET NULL;
