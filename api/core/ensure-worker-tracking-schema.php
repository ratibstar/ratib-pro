<?php
/**
 * Worker tracking schema in control DB.
 * Safe to call multiple times.
 */
function ratibEnsureWorkerTrackingSchema(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `worker_locations` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `worker_id` INT NOT NULL,
            `tenant_id` INT NOT NULL,
            `lat` DECIMAL(10,8) NULL,
            `lng` DECIMAL(11,8) NULL,
            `accuracy` FLOAT NULL,
            `speed` FLOAT NULL,
            `status` ENUM('moving','idle','offline','alert') NOT NULL DEFAULT 'idle',
            `battery` INT NULL,
            `source` ENUM('gps','network','cached') NOT NULL DEFAULT 'gps',
            `recorded_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_worker_locations_worker` (`worker_id`),
            KEY `idx_worker_locations_tenant` (`tenant_id`),
            KEY `idx_worker_locations_recorded_at` (`recorded_at`),
            KEY `idx_worker_locations_worker_recorded` (`worker_id`,`recorded_at`),
            KEY `idx_worker_locations_tenant_recorded` (`tenant_id`,`recorded_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `worker_locations_archive` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `worker_id` INT NOT NULL,
            `tenant_id` INT NOT NULL,
            `lat` DECIMAL(10,8) NULL,
            `lng` DECIMAL(11,8) NULL,
            `accuracy` FLOAT NULL,
            `speed` FLOAT NULL,
            `status` ENUM('moving','idle','offline','alert') NOT NULL DEFAULT 'idle',
            `battery` INT NULL,
            `source` ENUM('gps','network','cached') NOT NULL DEFAULT 'gps',
            `recorded_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_worker_locations_archive_worker_recorded` (`worker_id`,`recorded_at`),
            KEY `idx_worker_locations_archive_tenant_recorded` (`tenant_id`,`recorded_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `worker_tracking_sessions` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `worker_id` INT NOT NULL,
            `tenant_id` INT NOT NULL,
            `started_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `last_seen` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `status` ENUM('active','inactive','lost') NOT NULL DEFAULT 'active',
            `last_lat` DECIMAL(10,8) NULL,
            `last_lng` DECIMAL(11,8) NULL,
            `last_speed` FLOAT NULL,
            `last_battery` INT NULL,
            `last_source` ENUM('gps','network','cached') NULL,
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_tracking_worker_tenant` (`worker_id`,`tenant_id`),
            KEY `idx_tracking_worker` (`worker_id`),
            KEY `idx_tracking_tenant` (`tenant_id`),
            KEY `idx_tracking_status` (`status`),
            KEY `idx_tracking_last_seen` (`last_seen`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `worker_tracking_devices` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `worker_id` INT NOT NULL,
            `tenant_id` INT NOT NULL,
            `device_id` VARCHAR(191) NOT NULL,
            `api_token` VARCHAR(255) NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_seen` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_tracking_device_worker_tenant` (`worker_id`,`tenant_id`,`device_id`),
            KEY `idx_tracking_device_tenant_worker` (`tenant_id`,`worker_id`),
            KEY `idx_tracking_device_token` (`api_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `worker_geofences` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `tenant_id` INT NULL,
            `agency_id` INT NULL,
            `name` VARCHAR(100) NOT NULL,
            `center_lat` DECIMAL(10,8) NOT NULL,
            `center_lng` DECIMAL(11,8) NOT NULL,
            `radius_m` INT NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_by` INT NULL,
            `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_worker_geofences_tenant` (`tenant_id`),
            KEY `idx_worker_geofences_agency` (`agency_id`),
            KEY `idx_worker_geofences_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $conn->exec(
        "CREATE TABLE IF NOT EXISTS `worker_geofence_states` (
            `id` BIGINT NOT NULL AUTO_INCREMENT,
            `worker_id` INT NOT NULL,
            `tenant_id` INT NOT NULL,
            `geofence_id` BIGINT NOT NULL,
            `is_inside` TINYINT(1) NOT NULL DEFAULT 1,
            `last_distance_m` FLOAT NULL,
            `last_lat` DECIMAL(10,8) NULL,
            `last_lng` DECIMAL(11,8) NULL,
            `last_seen_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_worker_geofence_state` (`worker_id`,`tenant_id`,`geofence_id`),
            KEY `idx_worker_geofence_state_geofence` (`geofence_id`),
            KEY `idx_worker_geofence_state_inside` (`is_inside`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
