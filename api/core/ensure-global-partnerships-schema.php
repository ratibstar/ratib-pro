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

    ratibEnsurePartnerPortalPartnershipsSchema($conn);
    ratibEnsurePartnerAgencyExtendedProfileColumns($conn);
}

/**
 * Extended partner agency profile fields (license, bank, passport, legacy Arabic columns unused in English UI).
 */
function ratibEnsurePartnerAgencyExtendedProfileColumns(PDO $conn): void
{
    static $extDone = false;
    if ($extDone) {
        return;
    }

    $alters = [
        'ALTER TABLE `partner_agencies` ADD COLUMN `name_ar` VARCHAR(255) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `agency_code` VARCHAR(64) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `address_ar` VARCHAR(500) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `address_en` VARCHAR(500) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `city_ar` VARCHAR(100) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `license` VARCHAR(255) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `phone2` VARCHAR(50) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `fax` VARCHAR(50) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `passport_no` VARCHAR(80) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `passport_issue_place` VARCHAR(255) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `passport_issue_date` DATE DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `sending_bank` VARCHAR(255) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `account_number` VARCHAR(100) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `mobile` VARCHAR(50) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `license_owner` VARCHAR(255) DEFAULT NULL',
        'ALTER TABLE `partner_agencies` ADD COLUMN `notes` TEXT DEFAULT NULL',
    ];
    foreach ($alters as $sql) {
        try {
            $conn->exec($sql);
        } catch (Throwable $e) {
            if (stripos($e->getMessage(), 'Duplicate column') === false) {
                error_log('ratibEnsurePartnerAgencyExtendedProfileColumns: ' . $e->getMessage());
            }
        }
    }

    $extDone = true;
}

/**
 * Partner portal access (magic link / optional password) + CV documents per agency.
 */
function ratibEnsurePartnerPortalPartnershipsSchema(PDO $conn): void
{
    static $portalDone = false;
    if ($portalDone) {
        return;
    }

    $addColumn = static function (PDO $conn, string $sql) use (&$addColumn): void {
        try {
            $conn->exec($sql);
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate column') === false && stripos($msg, 'check that column/key exists') === false) {
                error_log('ratibEnsurePartnerPortalPartnershipsSchema ALTER: ' . $msg);
            }
        }
    };

    $addColumn(
        $conn,
        "ALTER TABLE `partner_agencies` ADD COLUMN `portal_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`"
    );
    $addColumn(
        $conn,
        "ALTER TABLE `partner_agencies` ADD COLUMN `portal_access_token` VARCHAR(64) DEFAULT NULL AFTER `portal_enabled`"
    );
    $addColumn(
        $conn,
        "ALTER TABLE `partner_agencies` ADD COLUMN `portal_password_hash` VARCHAR(255) DEFAULT NULL AFTER `portal_access_token`"
    );

    try {
        $conn->exec(
            'CREATE UNIQUE INDEX `idx_partner_agencies_portal_token` ON `partner_agencies` (`portal_access_token`)'
        );
    } catch (Throwable $e) {
        if (stripos($e->getMessage(), 'Duplicate key name') === false) {
            error_log('ratibEnsurePartnerPortalPartnershipsSchema index: ' . $e->getMessage());
        }
    }

    try {
        $conn->exec(
            "CREATE TABLE IF NOT EXISTS `partner_agency_cvs` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `partner_agency_id` INT NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `stored_filename` VARCHAR(255) NOT NULL,
                `original_filename` VARCHAR(255) NOT NULL,
                `mime_type` VARCHAR(120) DEFAULT NULL,
                `file_size` INT UNSIGNED DEFAULT NULL,
                `sort_order` INT NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_partner_agency_cvs_agency` (`partner_agency_id`),
                CONSTRAINT `fk_partner_agency_cvs_agency` FOREIGN KEY (`partner_agency_id`) REFERENCES `partner_agencies` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
        error_log('ratibEnsurePartnerPortalPartnershipsSchema partner_agency_cvs table: ' . $e->getMessage());
    }

    $portalDone = true;
}
