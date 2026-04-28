<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/ensure-agency-partnerships-schema.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/ensure-agency-partnerships-schema.php`.
 */
/**
 * Ensures agency_partnerships table and workers.agency_partnership_id exist.
 * Safe to call multiple times (idempotent).
 */
function ratibEnsureAgencyPartnershipsSchema(PDO $conn) {
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `agency_partnerships` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `office_agency_id` INT NOT NULL DEFAULT 0,
            `partner_arrival_agency_id` INT UNSIGNED NOT NULL,
            `notes` TEXT NULL,
            `status` VARCHAR(32) NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_office_partner` (`office_agency_id`, `partner_arrival_agency_id`),
            KEY `idx_office_agency` (`office_agency_id`),
            KEY `idx_partner_aa` (`partner_arrival_agency_id`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        $chk = $conn->query("SHOW COLUMNS FROM `workers` LIKE 'agency_partnership_id'");
        if ($chk && !$chk->fetch(PDO::FETCH_ASSOC)) {
            $conn->exec(
                "ALTER TABLE `workers` ADD COLUMN `agency_partnership_id` INT UNSIGNED NULL DEFAULT NULL AFTER `subagent_id`"
            );
        }
    } catch (Throwable $e) {
        try {
            $chk = $conn->query("SHOW COLUMNS FROM `workers` LIKE 'agency_partnership_id'");
            if ($chk && !$chk->fetch(PDO::FETCH_ASSOC)) {
                $conn->exec(
                    "ALTER TABLE `workers` ADD COLUMN `agency_partnership_id` INT UNSIGNED NULL DEFAULT NULL"
                );
            }
        } catch (Throwable $e2) {
            error_log('ratibEnsureAgencyPartnershipsSchema: workers column: ' . $e2->getMessage());
        }
    }
}
