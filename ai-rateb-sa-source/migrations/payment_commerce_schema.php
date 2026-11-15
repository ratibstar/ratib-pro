<?php
declare(strict_types=1);

function ensure_payment_commerce_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_refunds (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        receipt_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        moyasar_id VARCHAR(64) NULL,
        amount_halalas INT UNSIGNED NOT NULL,
        credits_reversed INT UNSIGNED NOT NULL DEFAULT 0,
        reason VARCHAR(500) NULL,
        source VARCHAR(32) NOT NULL DEFAULT 'admin',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY receipt_id (receipt_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS outbound_email_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        template_key VARCHAR(64) NOT NULL,
        recipient VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'queued',
        meta VARCHAR(500) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY template_created (template_key, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function commerce_table_has(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}