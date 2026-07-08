<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M018CollectionsChannels extends AbstractMigration
{
    public function name(): string
    {
        return '018_collections_channels';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS collections (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                slug VARCHAR(150) NOT NULL,
                collection_type ENUM("manual", "dynamic", "seasonal", "promotional") NOT NULL DEFAULT "manual",
                image_path VARCHAR(500) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                publish_at DATETIME(6) NULL,
                archive_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_collections_uuid (uuid),
                UNIQUE KEY uk_collections_slug (slug),
                KEY idx_collections_status (status),
                KEY idx_collections_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS collection_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                collection_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_collection_translations_uuid (uuid),
                UNIQUE KEY uk_collection_translations_lang (collection_id, language_code),
                KEY idx_collection_translations_deleted (deleted_at),
                CONSTRAINT fk_collection_translations_collection FOREIGN KEY (collection_id) REFERENCES collections (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS collection_products (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                collection_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_collection_products_uuid (uuid),
                UNIQUE KEY uk_collection_products_pair (collection_id, product_id),
                KEY idx_collection_products_collection (collection_id),
                KEY idx_collection_products_product (product_id),
                KEY idx_collection_products_deleted (deleted_at),
                CONSTRAINT fk_collection_products_collection FOREIGN KEY (collection_id) REFERENCES collections (id),
                CONSTRAINT fk_collection_products_product FOREIGN KEY (product_id) REFERENCES products (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS channels (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                code VARCHAR(30) NOT NULL,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_channels_uuid (uuid),
                UNIQUE KEY uk_channels_code (code),
                KEY idx_channels_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS channel_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                channel_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(150) NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_channel_translations_uuid (uuid),
                UNIQUE KEY uk_channel_translations_lang (channel_id, language_code),
                KEY idx_channel_translations_deleted (deleted_at),
                CONSTRAINT fk_channel_translations_channel FOREIGN KEY (channel_id) REFERENCES channels (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_channels (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                channel_id BIGINT UNSIGNED NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                channel_config JSON NULL,
                publish_at DATETIME(6) NULL,
                archive_at DATETIME(6) NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_channels_uuid (uuid),
                UNIQUE KEY uk_product_channels_pair (product_id, channel_id),
                KEY idx_product_channels_product (product_id),
                KEY idx_product_channels_channel (channel_id),
                KEY idx_product_channels_deleted (deleted_at),
                CONSTRAINT fk_product_channels_product FOREIGN KEY (product_id) REFERENCES products (id),
                CONSTRAINT fk_product_channels_channel FOREIGN KEY (channel_id) REFERENCES channels (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->seedChannels();
    }

    public function down(): void
    {
        // Drop in reverse dependency order.
        $this->exec(
            'DROP TABLE IF EXISTS product_channels;
             DROP TABLE IF EXISTS collection_products;
             DROP TABLE IF EXISTS collection_translations;
             DROP TABLE IF EXISTS collections;
             DROP TABLE IF EXISTS channel_translations;
             DROP TABLE IF EXISTS channels'
        );
    }

    private function seedChannels(): void
    {
        $channels = [
            'website' => 'Website',
            'pos' => 'Point of Sale',
            'b2b' => 'B2B Portal',
            'marketplace' => 'Marketplace',
            'mobile' => 'Mobile App',
        ];

        foreach ($channels as $code => $name) {
            $channelUuid = $this->uuidFor('channel-' . $code);
            $this->exec(
                'INSERT IGNORE INTO channels (uuid, code, status) VALUES ("' . $channelUuid . '", "' . $code . '", "active")'
            );
            $channelId = $this->fetchChannelId($code);
            if ($channelId === null) {
                continue;
            }
            $translationUuid = $this->uuidFor('channel-tr-' . $code . '-en');
            $this->exec(
                'INSERT IGNORE INTO channel_translations (uuid, channel_id, language_code, name)
                 VALUES ("' . $translationUuid . '", ' . $channelId . ', "en", "' . addslashes($name) . '")'
            );
        }
    }

    private function fetchChannelId(string $code): ?int
    {
        $stmt = $this->pdo->query('SELECT id FROM channels WHERE code = ' . $this->pdo->quote($code) . ' LIMIT 1');
        if ($stmt === false) {
            return null;
        }
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return is_array($row) ? (int) $row['id'] : null;
    }

    private function uuidFor(string $seed): string
    {
        $hash = md5('rateb-s2-' . $seed);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }
}
