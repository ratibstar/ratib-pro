<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/payment_orders_schema.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/payment_orders_schema.php`.
 */

declare(strict_types=1);

/**
 * Tables for N-Genius agency registration only — NOT generic shop `orders`
 * (avoids CREATE IF NOT EXISTS skipping when another `orders` table already exists).
 */
if (!defined('RATIB_NGENIUS_ORDERS_TABLE')) {
    define('RATIB_NGENIUS_ORDERS_TABLE', 'ngenius_reg_orders');
}
if (!defined('RATIB_NGENIUS_PAYMENTS_TABLE')) {
    define('RATIB_NGENIUS_PAYMENTS_TABLE', 'ngenius_reg_payments');
}

/**
 * Whether a column exists on the current schema (SHOW COLUMNS — works when information_schema is restricted).
 */
function ratib_payment_orders_column_exists(PDO $pdo, string $table, string $column): bool
{
    $table = str_replace(['`', "\0"], '', $table);
    $column = str_replace(['`', "\0", '%', '_'], '', $column);
    if ($table === '' || $column === '') {
        return false;
    }
    try {
        $st = $pdo->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
        $st->execute([$column]);

        return (bool) $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if (function_exists('paymentLog')) {
            paymentLog('payment_orders_schema column check failed', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }
}

/**
 * ADD COLUMN only when missing. Omits AFTER … so legacy tables without intermediate columns still migrate.
 */
function ratib_payment_orders_add_column_if_missing(PDO $pdo, string $table, string $column, string $columnSqlTail): void
{
    $table = str_replace('`', '', $table);
    $column = str_replace('`', '', $column);
    if (ratib_payment_orders_column_exists($pdo, $table, $column)) {
        return;
    }
    try {
        $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $columnSqlTail);
    } catch (PDOException $e) {
        $code = (int) ($e->errorInfo[1] ?? 0);
        if ($code === 1060 || stripos($e->getMessage(), 'duplicate column') !== false) {
            return;
        }
        throw $e;
    }
}

/**
 * Ensure tables used by api/create-order.php and api/verify.php exist (idempotent).
 */
function payment_ensure_ngenius_tables($pdo): void
{
    $orders = RATIB_NGENIUS_ORDERS_TABLE;
    $payments = RATIB_NGENIUS_PAYMENTS_TABLE;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `{$orders}` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            amount INT NOT NULL COMMENT 'Gateway minor units (halalas if SAR, cents if USD; matches N-Genius amount.value)',
            plan_key VARCHAR(32) NOT NULL DEFAULT '',
            years SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            control_request_id INT UNSIGNED NULL DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'pending',
            ngenius_order_id VARCHAR(128) NULL DEFAULT NULL,
            reg_agency_name VARCHAR(255) NOT NULL DEFAULT '',
            reg_agency_id VARCHAR(64) NOT NULL DEFAULT '',
            reg_country_id INT NOT NULL DEFAULT 0,
            reg_country_name VARCHAR(255) NOT NULL DEFAULT '',
            reg_contact_phone VARCHAR(64) NOT NULL DEFAULT '',
            reg_desired_site_url VARCHAR(512) NOT NULL DEFAULT '',
            reg_notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY ngenius_reg_orders_status_idx (status),
            KEY ngenius_reg_orders_email_idx (email),
            KEY ngenius_reg_orders_control_request_idx (control_request_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Backfill schema for existing installs (no IF NOT EXISTS — unsupported on many MySQL/MariaDB builds).
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'plan_key', "VARCHAR(32) NOT NULL DEFAULT ''");
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'years', 'SMALLINT UNSIGNED NOT NULL DEFAULT 1');
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'subtotal', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'tax_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'total_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0.00');
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'control_request_id', 'INT UNSIGNED NULL DEFAULT NULL');
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'ngenius_order_id', 'VARCHAR(128) NULL DEFAULT NULL');
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'reg_agency_name', "VARCHAR(255) NOT NULL DEFAULT ''");
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'reg_agency_id', "VARCHAR(64) NOT NULL DEFAULT ''");
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'reg_country_id', 'INT NOT NULL DEFAULT 0');
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'reg_country_name', "VARCHAR(255) NOT NULL DEFAULT ''");
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'reg_contact_phone', "VARCHAR(64) NOT NULL DEFAULT ''");
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'reg_desired_site_url', "VARCHAR(512) NOT NULL DEFAULT ''");
    ratib_payment_orders_add_column_if_missing($pdo, $orders, 'reg_notes', 'TEXT NULL');

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `{$payments}` (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id INT UNSIGNED NOT NULL,
            reference VARCHAR(128) NOT NULL,
            status VARCHAR(32) NOT NULL,
            raw_response LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY ngenius_reg_payments_order_idx (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
