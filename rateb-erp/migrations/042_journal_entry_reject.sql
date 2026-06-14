-- Journal entry rejection workflow (reason + rejected status)
SET NAMES utf8mb4;

ALTER TABLE rateb_journal_entries
    MODIFY status ENUM('draft','posted','void','rejected') NOT NULL DEFAULT 'draft',
    ADD COLUMN reject_reason VARCHAR(500) NULL AFTER status,
    ADD COLUMN rejected_at DATETIME NULL AFTER reject_reason,
    ADD COLUMN rejected_by INT UNSIGNED NULL AFTER rejected_at;
