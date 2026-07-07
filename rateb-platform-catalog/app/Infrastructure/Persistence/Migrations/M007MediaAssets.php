<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M007MediaAssets extends AbstractMigration
{
    public function name(): string
    {
        return '007_media_assets';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS asset_types (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                code VARCHAR(50) NOT NULL,
                category ENUM("image", "document", "video", "archive", "model_3d", "firmware", "other") NOT NULL DEFAULT "other",
                mime_patterns JSON NULL,
                extension_patterns JSON NULL,
                is_system TINYINT(1) NOT NULL DEFAULT 0,
                status ENUM("active", "inactive") NOT NULL DEFAULT "active",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_asset_types_uuid (uuid),
                UNIQUE KEY uk_asset_types_code (code),
                KEY idx_asset_types_deleted (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS asset_type_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                asset_type_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                name VARCHAR(150) NOT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_asset_type_translations_uuid (uuid),
                UNIQUE KEY uk_asset_type_translations_type_lang (asset_type_id, language_code),
                KEY idx_asset_type_translations_deleted (deleted_at),
                CONSTRAINT fk_asset_type_translations_type FOREIGN KEY (asset_type_id) REFERENCES asset_types (id),
                CONSTRAINT fk_asset_type_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_images (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                asset_type_id BIGINT UNSIGNED NOT NULL,
                storage_key VARCHAR(500) NOT NULL,
                mime_type VARCHAR(80) NOT NULL,
                width INT NULL,
                height INT NULL,
                file_size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
                variant ENUM("original", "thumbnail", "medium", "large", "webp") NOT NULL DEFAULT "original",
                sort_order INT NOT NULL DEFAULT 0,
                is_primary TINYINT(1) NOT NULL DEFAULT 0,
                background_removed TINYINT(1) NOT NULL DEFAULT 0,
                optimized TINYINT(1) NOT NULL DEFAULT 0,
                compressed TINYINT(1) NOT NULL DEFAULT 0,
                watermark TINYINT(1) NOT NULL DEFAULT 0,
                checksum_sha256 CHAR(64) NULL,
                processing_meta JSON NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_images_uuid_variant (uuid, variant),
                UNIQUE KEY uk_product_images_checksum (checksum_sha256),
                KEY idx_product_images_product (product_id),
                KEY idx_product_images_deleted (deleted_at),
                CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products (id),
                CONSTRAINT fk_product_images_asset_type FOREIGN KEY (asset_type_id) REFERENCES asset_types (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_image_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_image_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                alt_text VARCHAR(255) NOT NULL DEFAULT "",
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_image_translations_uuid (uuid),
                UNIQUE KEY uk_product_image_translations_image_lang (product_image_id, language_code),
                KEY idx_product_image_translations_deleted (deleted_at),
                CONSTRAINT fk_product_image_translations_image FOREIGN KEY (product_image_id) REFERENCES product_images (id),
                CONSTRAINT fk_product_image_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_files (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                asset_type_id BIGINT UNSIGNED NOT NULL,
                storage_key VARCHAR(500) NOT NULL,
                mime_type VARCHAR(80) NOT NULL,
                file_size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
                checksum_sha256 CHAR(64) NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_files_uuid (uuid),
                UNIQUE KEY uk_product_files_checksum (checksum_sha256),
                KEY idx_product_files_product (product_id),
                KEY idx_product_files_deleted (deleted_at),
                CONSTRAINT fk_product_files_product FOREIGN KEY (product_id) REFERENCES products (id),
                CONSTRAINT fk_product_files_asset_type FOREIGN KEY (asset_type_id) REFERENCES asset_types (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_file_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_file_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_file_translations_uuid (uuid),
                UNIQUE KEY uk_product_file_translations_file_lang (product_file_id, language_code),
                KEY idx_product_file_translations_deleted (deleted_at),
                CONSTRAINT fk_product_file_translations_file FOREIGN KEY (product_file_id) REFERENCES product_files (id),
                CONSTRAINT fk_product_file_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_videos (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                asset_type_id BIGINT UNSIGNED NOT NULL,
                video_type ENUM("youtube", "vimeo", "self_hosted") NOT NULL,
                external_id VARCHAR(100) NULL,
                external_url VARCHAR(500) NULL,
                storage_key VARCHAR(500) NULL,
                thumbnail_storage_key VARCHAR(500) NULL,
                duration_seconds INT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_videos_uuid (uuid),
                KEY idx_product_videos_product (product_id),
                KEY idx_product_videos_deleted (deleted_at),
                CONSTRAINT fk_product_videos_product FOREIGN KEY (product_id) REFERENCES products (id),
                CONSTRAINT fk_product_videos_asset_type FOREIGN KEY (asset_type_id) REFERENCES asset_types (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_video_translations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_video_id BIGINT UNSIGNED NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_video_translations_uuid (uuid),
                UNIQUE KEY uk_product_video_translations_video_lang (product_video_id, language_code),
                KEY idx_product_video_translations_deleted (deleted_at),
                CONSTRAINT fk_product_video_translations_video FOREIGN KEY (product_video_id) REFERENCES product_videos (id),
                CONSTRAINT fk_product_video_translations_language FOREIGN KEY (language_code) REFERENCES languages (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->seedAssetTypes();
    }

    public function down(): void
    {
        $this->exec('DROP TABLE IF EXISTS product_video_translations');
        $this->exec('DROP TABLE IF EXISTS product_videos');
        $this->exec('DROP TABLE IF EXISTS product_file_translations');
        $this->exec('DROP TABLE IF EXISTS product_files');
        $this->exec('DROP TABLE IF EXISTS product_image_translations');
        $this->exec('DROP TABLE IF EXISTS product_images');
        $this->exec('DROP TABLE IF EXISTS asset_type_translations');
        $this->exec('DROP TABLE IF EXISTS asset_types');
    }

    private function seedAssetTypes(): void
    {
        $types = [
            ['image_original', 'image', 'Image (original)'],
            ['pdf', 'document', 'PDF document'],
            ['manual', 'document', 'Manual'],
            ['datasheet', 'document', 'Datasheet'],
            ['certificate', 'document', 'Certificate'],
            ['warranty_doc', 'document', 'Warranty document'],
            ['catalog_pdf', 'document', 'Catalog PDF'],
            ['driver', 'firmware', 'Driver'],
            ['firmware', 'firmware', 'Firmware'],
            ['video_youtube', 'video', 'YouTube video'],
            ['video_vimeo', 'video', 'Vimeo video'],
            ['video_self_hosted', 'video', 'Self-hosted video'],
            ['model_3d', 'model_3d', '3D model'],
            ['cad', 'other', 'CAD file'],
            ['psd', 'image', 'PSD source'],
            ['zip', 'archive', 'Archive (ZIP)'],
        ];

        $insertType = $this->pdo->prepare(
            'INSERT INTO asset_types (uuid, code, category, is_system, status)
             SELECT :uuid, :code, :category, 1, "active"
             FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM asset_types WHERE code = :code_check AND deleted_at IS NULL)'
        );

        $findType = $this->pdo->prepare(
            'SELECT id FROM asset_types WHERE code = :code AND deleted_at IS NULL LIMIT 1'
        );

        $insertTranslation = $this->pdo->prepare(
            'INSERT INTO asset_type_translations (uuid, asset_type_id, language_code, name)
             SELECT :uuid, :asset_type_id, :language_code, :name
             FROM DUAL
             WHERE NOT EXISTS (
                SELECT 1 FROM asset_type_translations
                WHERE asset_type_id = :type_check AND language_code = :lang_check AND deleted_at IS NULL
             )'
        );

        foreach ($types as [$code, $category, $nameEn]) {
            $typeUuid = $this->uuidV4();
            $insertType->execute([
                'uuid' => $typeUuid,
                'code' => $code,
                'category' => $category,
                'code_check' => $code,
            ]);

            $findType->execute(['code' => $code]);
            $row = $findType->fetch(\PDO::FETCH_ASSOC);
            $findType->closeCursor();
            if (!is_array($row)) {
                continue;
            }

            $typeId = (int) $row['id'];
            foreach (['en' => $nameEn, 'ar' => $nameEn] as $lang => $name) {
                $insertTranslation->execute([
                    'uuid' => $this->uuidV4(),
                    'asset_type_id' => $typeId,
                    'language_code' => $lang,
                    'name' => $name,
                    'type_check' => $typeId,
                    'lang_check' => $lang,
                ]);
            }
        }
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
