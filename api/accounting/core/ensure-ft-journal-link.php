<?php
/**
 * Ensure financial_transactions.journal_entry_id exists (schema migration helper).
 * Safe to call repeatedly; no-ops when column already present.
 */
if (!function_exists('rateb_ensure_ft_journal_entry_id_column')) {
    function rateb_ensure_ft_journal_entry_id_column($conn) {
        static $ensured = null;
        if ($ensured !== null) {
            return $ensured;
        }
        if (!$conn) {
            $ensured = false;
            return false;
        }
        try {
            $tableCheck = @$conn->query("SHOW TABLES LIKE 'financial_transactions'");
            if (!$tableCheck || $tableCheck->num_rows === 0) {
                if ($tableCheck) {
                    $tableCheck->free();
                }
                $ensured = false;
                return false;
            }
            $tableCheck->free();

            $colCheck = @$conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'journal_entry_id'");
            if ($colCheck && $colCheck->num_rows > 0) {
                $colCheck->free();
                $ensured = true;
                return true;
            }
            if ($colCheck) {
                $colCheck->free();
            }

            $ok = @$conn->query("ALTER TABLE financial_transactions ADD COLUMN journal_entry_id INT NULL");
            if ($ok) {
                @$conn->query("ALTER TABLE financial_transactions ADD INDEX idx_journal_entry_id (journal_entry_id)");
            }
            // Re-check in case another request added it concurrently
            $recheck = @$conn->query("SHOW COLUMNS FROM financial_transactions LIKE 'journal_entry_id'");
            $ensured = ($recheck && $recheck->num_rows > 0);
            if ($recheck) {
                $recheck->free();
            }
            return $ensured;
        } catch (Throwable $e) {
            error_log('rateb_ensure_ft_journal_entry_id_column: ' . $e->getMessage());
            $ensured = false;
            return false;
        }
    }
}
