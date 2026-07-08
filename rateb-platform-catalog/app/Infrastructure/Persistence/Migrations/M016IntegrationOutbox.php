<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M016IntegrationOutbox extends AbstractMigration
{
    public function name(): string
    {
        return '016_integration_outbox';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS integration_outbox (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id CHAR(36) NOT NULL,
                event_type VARCHAR(80) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_uuid CHAR(36) NOT NULL,
                erp_company_id INT UNSIGNED NULL,
                payload JSON NOT NULL,
                status ENUM("pending", "dispatched", "delivered", "failed") NOT NULL DEFAULT "pending",
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                next_attempt_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_integration_outbox_event_id (event_id),
                KEY idx_integration_outbox_poll (status, next_attempt_at),
                KEY idx_integration_outbox_entity (entity_type, entity_uuid)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS webhook_subscriptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                erp_company_id INT UNSIGNED NULL,
                url VARCHAR(500) NOT NULL,
                secret_encrypted VARCHAR(512) NOT NULL,
                events JSON NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_webhook_subscriptions_uuid (uuid),
                KEY idx_webhook_subscriptions_company (erp_company_id),
                KEY idx_webhook_subscriptions_active (is_active, deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS webhook_deliveries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                subscription_id BIGINT UNSIGNED NOT NULL,
                event_id CHAR(36) NOT NULL,
                request_body JSON NOT NULL,
                response_status SMALLINT NULL,
                response_body TEXT NULL,
                status ENUM("pending", "delivered", "failed") NOT NULL DEFAULT "pending",
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                delivered_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                UNIQUE KEY uk_webhook_deliveries_uuid (uuid),
                KEY idx_webhook_deliveries_subscription (subscription_id),
                KEY idx_webhook_deliveries_event (event_id),
                KEY idx_webhook_deliveries_status (status),
                CONSTRAINT fk_webhook_deliveries_subscription FOREIGN KEY (subscription_id) REFERENCES webhook_subscriptions (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        // Drop in reverse dependency order (FK children first).
        $this->exec(
            'DROP TABLE IF EXISTS webhook_deliveries;
             DROP TABLE IF EXISTS webhook_subscriptions;
             DROP TABLE IF EXISTS integration_outbox'
        );
    }
}
