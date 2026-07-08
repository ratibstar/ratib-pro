<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M014DatabaseSearchIndexes extends AbstractMigration
{
    public function name(): string
    {
        return '014_database_search_indexes';
    }

    public function up(): void
    {
        $this->exec(
            'ALTER TABLE product_translations
             ADD FULLTEXT INDEX ft_product_translations_search (name, short_description, description)'
        );

        $this->exec(
            'ALTER TABLE product_variant_translations
             ADD FULLTEXT INDEX ft_variant_translations_search (name, description)'
        );

        $this->exec(
            'ALTER TABLE products
             ADD INDEX idx_products_sku_search (sku),
             ADD INDEX idx_products_status_published (status, deleted_at)'
        );

        $this->exec(
            'ALTER TABLE product_variants
             ADD INDEX idx_product_variants_sku_search (sku),
             ADD INDEX idx_product_variants_status_published (status, deleted_at)'
        );

        $this->exec(
            'ALTER TABLE product_barcodes
             ADD INDEX idx_product_barcodes_barcode_active (barcode, deleted_at)'
        );

        $this->exec(
            'ALTER TABLE product_variant_barcodes
             ADD INDEX idx_product_variant_barcodes_barcode_active (barcode, deleted_at)'
        );
    }

    public function down(): void
    {
        $this->exec('ALTER TABLE product_variant_barcodes DROP INDEX idx_product_variant_barcodes_barcode_active');
        $this->exec('ALTER TABLE product_barcodes DROP INDEX idx_product_barcodes_barcode_active');
        $this->exec('ALTER TABLE product_variants DROP INDEX idx_product_variants_status_published');
        $this->exec('ALTER TABLE product_variants DROP INDEX idx_product_variants_sku_search');
        $this->exec('ALTER TABLE products DROP INDEX idx_products_status_published');
        $this->exec('ALTER TABLE products DROP INDEX idx_products_sku_search');
        $this->exec('ALTER TABLE product_variant_translations DROP INDEX ft_variant_translations_search');
        $this->exec('ALTER TABLE product_translations DROP INDEX ft_product_translations_search');
    }
}
