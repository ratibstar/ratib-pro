-- Browse indexes for source-native vehicle search. Does not change existing unique keys.
--
-- Rollback procedure. Do not run it as part of this migration.
-- 295 adds indexes only. It has no foreign keys and does not change Phase 1 or Phase 2 tables.
-- Execution order:
-- DROP INDEX IF EXISTS idx_auto_ref_open_market_code ON rateb_auto_ref_open_model_markets;
-- DROP INDEX IF EXISTS idx_auto_ref_open_model_browse ON rateb_auto_ref_open_models;

SET NAMES utf8mb4;

CREATE INDEX IF NOT EXISTS idx_auto_ref_open_model_browse
    ON rateb_auto_ref_open_models (release_id, kind, make_slug, model_slug);

CREATE INDEX IF NOT EXISTS idx_auto_ref_open_market_code
    ON rateb_auto_ref_open_model_markets (market_code, model_id);
