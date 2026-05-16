<?php
declare(strict_types=1);

/**
 * Minimal schema for App worker-platform workflows (Global AI onboarding, worker-platform.php).
 */
function ensure_worker_platform_schema(PDO $db): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS workflows (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL,
            context_json LONGTEXT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'running',
            failed_step VARCHAR(191) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_workflows_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS workflow_states (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workflow_id BIGINT UNSIGNED NOT NULL,
            current_step VARCHAR(191) NOT NULL DEFAULT '',
            status VARCHAR(32) NOT NULL DEFAULT 'running',
            context_json LONGTEXT NULL,
            error_message TEXT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_workflow_states_workflow (workflow_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS idempotency_keys (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            idempotency_key VARCHAR(191) NOT NULL,
            workflow_id BIGINT UNSIGNED NULL,
            locked_at DATETIME NULL,
            expires_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_idempotency_key (idempotency_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS tracking_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            worker_id BIGINT UNSIGNED NOT NULL,
            latitude DECIMAL(10,7) NOT NULL,
            longitude DECIMAL(10,7) NOT NULL,
            location_name VARCHAR(255) NULL,
            moved_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_tracking_logs_worker (worker_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS worker_platform_notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            worker_id BIGINT UNSIGNED NOT NULL,
            channel VARCHAR(64) NOT NULL DEFAULT 'system',
            recipient VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'queued',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_wpn_worker (worker_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS worker_platform_events_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_name VARCHAR(191) NOT NULL,
            payload LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_wpel_name (event_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS security_rate_limit_counters (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            action_key VARCHAR(120) NOT NULL,
            scope_type VARCHAR(32) NOT NULL,
            scope_value VARCHAR(191) NOT NULL,
            window_start INT NOT NULL,
            hit_count INT NOT NULL DEFAULT 0,
            updated_at INT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_rate_limit (action_key, scope_type, scope_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS user_session_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(128) NOT NULL,
            user_id INT NOT NULL,
            ip_address VARCHAR(64) NULL,
            device_info VARCHAR(500) NULL,
            login_time DATETIME NULL,
            last_activity DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_session_user (session_id, user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        "CREATE TABLE IF NOT EXISTS workflow_metrics (
            id INT UNSIGNED NOT NULL,
            total_started BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_workflows BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_completed BIGINT UNSIGNED NOT NULL DEFAULT 0,
            total_execution_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            avg_execution_time_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
            success_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            failure_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $sql) {
        $db->exec($sql);
    }

    $db->exec('INSERT IGNORE INTO workflow_metrics (id) VALUES (1)');
}
