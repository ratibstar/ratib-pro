-- RATEB Contact Center — 024 migration reconcile (idempotent)
-- Retires legacy migration filenames on servers where fast-deploy left orphan SQL files.

INSERT IGNORE INTO rcc_migration_log (migration, batch) VALUES
('001_core_tenancy.sql', 0),
('002_ivr_runtime_engine.sql', 0),
('003_queue_ticket_stub.sql', 0),
('004_ivr_example_flow.sql', 0),
('005_realtime_core.sql', 0),
('006_softphone.sql', 0),
('010_rcc_tickets_ai_columns.sql', 0),
('020_migration_reconcile.sql', 0);
