-- RATEB ERP — branch code uniqueness (conditional; no silent renames)
-- Adds uq_branch_company_code only when zero duplicate (company_id, code) groups exist.
-- Reversible: ALTER TABLE rateb_branches DROP INDEX uq_branch_company_code;

SET NAMES utf8mb4;

SET @dup_groups = (
    SELECT COUNT(*) FROM (
        SELECT company_id, code
        FROM rateb_branches
        WHERE code IS NOT NULL AND TRIM(code) <> ''
        GROUP BY company_id, code
        HAVING COUNT(*) > 1
    ) AS branch_code_dups
);

SET @idx = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'rateb_branches'
      AND INDEX_NAME = 'uq_branch_company_code'
);

SET @sql = IF(
    @dup_groups = 0 AND @idx = 0,
    'ALTER TABLE rateb_branches ADD UNIQUE KEY uq_branch_company_code (company_id, code)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
