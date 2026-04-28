<?php
/**
 * EN: Handles API endpoint/business logic in `api/core/ensure-global-partnerships-schema.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/core/ensure-global-partnerships-schema.php`.
 */
/**
 * Ensures partnership agencies + worker deployments schema.
 * Safe to call multiple times.
 * (Live map / worker_locations / tracking_rate_limits removed — see database/migrate_remove_live_tracking.sql for legacy DB cleanup.)
 */
function ratibEnsureGlobalPartnershipsSchema(PDO $conn)
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `partner_agencies` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `country` VARCHAR(100) NOT NULL,
            `city` VARCHAR(100) DEFAULT NULL,
            `contact_person` VARCHAR(255) DEFAULT NULL,
            `email` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(50) DEFAULT NULL,
            `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_partner_agencies_country` (`country`),
            KEY `idx_partner_agencies_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `worker_deployments` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `worker_id` INT NOT NULL,
            `partner_agency_id` INT NOT NULL,
            `country` VARCHAR(100) NOT NULL,
            `job_title` VARCHAR(255) NOT NULL,
            `salary` DECIMAL(10,2) DEFAULT NULL,
            `contract_start` DATE DEFAULT NULL,
            `contract_end` DATE DEFAULT NULL,
            `status` ENUM('processing','deployed','returned','issue','transferred') NOT NULL DEFAULT 'processing',
            `notes` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_worker_deployments_worker` (`worker_id`),
            KEY `idx_worker_deployments_partner` (`partner_agency_id`),
            KEY `idx_worker_deployments_country` (`country`),
            KEY `idx_worker_deployments_status` (`status`),
            CONSTRAINT `fk_worker_deployments_worker` FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_worker_deployments_partner` FOREIGN KEY (`partner_agency_id`) REFERENCES `partner_agencies` (`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
