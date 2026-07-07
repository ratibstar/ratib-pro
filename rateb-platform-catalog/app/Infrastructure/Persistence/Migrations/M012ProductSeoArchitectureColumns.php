<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M012ProductSeoArchitectureColumns extends AbstractMigration
{
    public function name(): string
    {
        return '012_product_seo_architecture_columns';
    }

    public function up(): void
    {
        $this->exec(
            'ALTER TABLE product_seo
             ADD COLUMN canonical_url VARCHAR(500) NULL AFTER product_id'
        );

        $this->exec(
            'ALTER TABLE product_seo_translations
             ADD COLUMN og_title VARCHAR(255) NULL AFTER keywords,
             ADD COLUMN og_description VARCHAR(500) NULL AFTER og_title,
             ADD COLUMN og_image_path VARCHAR(500) NULL AFTER og_description,
             ADD COLUMN twitter_title VARCHAR(255) NULL AFTER og_image_path,
             ADD COLUMN twitter_description VARCHAR(500) NULL AFTER twitter_title,
             ADD COLUMN twitter_image_path VARCHAR(500) NULL AFTER twitter_description'
        );

        $this->exec(
            'CREATE UNIQUE INDEX uk_product_seo_translations_slug_lang
             ON product_seo_translations (slug, language_code)'
        );
    }

    public function down(): void
    {
        $this->exec('DROP INDEX uk_product_seo_translations_slug_lang ON product_seo_translations');
        $this->exec(
            'ALTER TABLE product_seo_translations
             DROP COLUMN twitter_image_path,
             DROP COLUMN twitter_description,
             DROP COLUMN twitter_title,
             DROP COLUMN og_image_path,
             DROP COLUMN og_description,
             DROP COLUMN og_title'
        );
        $this->exec('ALTER TABLE product_seo DROP COLUMN canonical_url');
    }
}
