<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Persistence\Migrations;

final class M017ProductPrices extends AbstractMigration
{
    public function name(): string
    {
        return '017_product_prices';
    }

    public function up(): void
    {
        $this->exec(
            'CREATE TABLE IF NOT EXISTS product_prices (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                currency_code CHAR(3) NOT NULL,
                cost DECIMAL(14,4) NULL,
                msrp DECIMAL(14,4) NULL,
                default_price DECIMAL(14,4) NULL,
                effective_from DATE NULL,
                effective_to DATE NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
                updated_at DATETIME(6) NULL ON UPDATE CURRENT_TIMESTAMP(6),
                created_by BIGINT UNSIGNED NULL,
                updated_by BIGINT UNSIGNED NULL,
                deleted_at DATETIME(6) NULL,
                deleted_by BIGINT UNSIGNED NULL,
                UNIQUE KEY uk_product_prices_uuid (uuid),
                UNIQUE KEY uk_product_prices_product_currency (product_id, currency_code),
                KEY idx_product_prices_product (product_id),
                KEY idx_product_prices_currency (currency_code),
                KEY idx_product_prices_deleted (deleted_at),
                CONSTRAINT fk_product_prices_product FOREIGN KEY (product_id) REFERENCES products (id),
                CONSTRAINT fk_product_prices_currency FOREIGN KEY (currency_code) REFERENCES currencies (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        // Reverse dependency order not required (no FK children created here).
        $this->exec(
            'DROP TABLE IF EXISTS product_prices'
        );
    }
}
