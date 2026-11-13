<?php
declare(strict_types=1);

function admin_table_has_platform(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    return (bool) $stmt->fetchColumn();
}

function ensure_platform_settings_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS platform_settings (
        setting_key VARCHAR(64) NOT NULL,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by_user_id BIGINT UNSIGNED NULL,
        PRIMARY KEY (setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaults = [
        'public_site_enabled' => '1',
        'maintenance_mode' => '0',
        'maintenance_message_en' => 'We are performing scheduled maintenance. Please check back soon.',
        'maintenance_message_ar' => 'نجري صيانة مجدولة. يُرجى معاودة الزيارة لاحقًا.',
    ];
    $ins = $pdo->prepare('INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }
}

function platform_setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    if (!admin_table_has_platform($pdo, 'platform_settings', 'setting_key')) {
        return $default;
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v !== false ? (string) $v : $default;
}

function platform_setting_bool(PDO $pdo, string $key, bool $default = false): bool
{
    $v = platform_setting($pdo, $key, $default ? '1' : '0');
    return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
}

function platform_set_setting(PDO $pdo, string $key, string $value, ?int $adminUserId = null): void
{
    ensure_platform_settings_schema($pdo);
    $pdo->prepare(
        'INSERT INTO platform_settings (setting_key, setting_value, updated_by_user_id) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by_user_id = VALUES(updated_by_user_id)'
    )->execute([$key, $value, $adminUserId]);
}
