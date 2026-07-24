-- RATEB ERP — POS sync acceptance commit metadata (Phase 13)
-- Status remains VARCHAR(32). Lifecycle enforced in application code.
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

ALTER TABLE rateb_pos_sync_acceptances
    ADD COLUMN order_id INT UNSIGNED NULL AFTER status,
    ADD COLUMN committed_at DATETIME NULL AFTER accepted_at,
    ADD COLUMN failed_at DATETIME NULL AFTER committed_at,
    ADD COLUMN last_error TEXT NULL AFTER failed_at,
    ADD COLUMN error_code VARCHAR(64) NULL AFTER last_error,
    ADD COLUMN retry_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER error_code,
    ADD COLUMN committing_at DATETIME NULL AFTER retry_count,
    ADD COLUMN processing_ms INT NULL AFTER committing_at,
    ADD COLUMN commit_token VARCHAR(64) NULL AFTER processing_ms;

ALTER TABLE rateb_pos_sync_acceptances
    ADD INDEX idx_pos_accept_commit_scan (company_id, status, committing_at);
