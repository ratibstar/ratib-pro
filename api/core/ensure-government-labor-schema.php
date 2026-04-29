<?php
/**
 * Government Labor Monitoring — schema (per-tenant DB alongside workers).
 * Safe to call multiple times per request (guarded by static flag).
 */
function ratibEnsureGovernmentLaborSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `gov_inspections` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `worker_id` INT NOT NULL,
            `agency_id` INT DEFAULT NULL,
            `inspector_name` VARCHAR(255) NOT NULL,
            `inspector_identity` VARCHAR(255) DEFAULT NULL,
            `inspector_password_hash` VARCHAR(255) DEFAULT NULL,
            `inspection_date` DATE NOT NULL,
            `status` ENUM('pending','passed','failed') NOT NULL DEFAULT 'pending',
            `notes` TEXT NULL,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_gov_insp_worker` (`worker_id`),
            KEY `idx_gov_insp_agency` (`agency_id`),
            KEY `idx_gov_insp_status` (`status`),
            KEY `idx_gov_insp_date` (`inspection_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    try {
        $conn->exec("ALTER TABLE `gov_inspections` ADD COLUMN `inspector_identity` VARCHAR(255) DEFAULT NULL AFTER `inspector_name`");
    } catch (Throwable $e) {
        // Ignore if column already exists.
    }
    try {
        $conn->exec("ALTER TABLE `gov_inspections` ADD COLUMN `inspector_password_hash` VARCHAR(255) DEFAULT NULL AFTER `inspector_identity`");
    } catch (Throwable $e) {
        // Ignore if column already exists.
    }

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `gov_violations` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `worker_id` INT NOT NULL,
            `agency_id` INT DEFAULT NULL,
            `inspection_id` INT DEFAULT NULL,
            `type` VARCHAR(120) NOT NULL,
            `severity` ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
            `description` TEXT NOT NULL,
            `action_taken` TEXT NULL,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_gov_v_worker` (`worker_id`),
            KEY `idx_gov_v_agency` (`agency_id`),
            KEY `idx_gov_v_insp` (`inspection_id`),
            KEY `idx_gov_v_sev` (`severity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `gov_blacklist` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `entity_type` ENUM('worker','agency') NOT NULL,
            `entity_id` INT NOT NULL,
            `reason` TEXT NOT NULL,
            `status` ENUM('active','removed') NOT NULL DEFAULT 'active',
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_gov_bl_entity` (`entity_type`,`entity_id`,`status`),
            KEY `idx_gov_bl_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `gov_worker_tracking` (
            `id` INT NOT NULL AUTO_INCREMENT,
            `worker_id` INT NOT NULL,
            `last_checkin` DATETIME DEFAULT NULL,
            `location_text` VARCHAR(255) DEFAULT NULL,
            `status` ENUM('safe','warning','alert') NOT NULL DEFAULT 'safe',
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_gov_track_worker` (`worker_id`),
            KEY `idx_gov_track_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
