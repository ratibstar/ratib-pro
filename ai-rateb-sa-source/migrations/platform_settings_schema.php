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
    foreach (rateb_feature_catalog() as $key => $meta) {
        $defaults[$key] = !empty($meta['default']) ? '1' : '0';
    }
    $ins = $pdo->prepare('INSERT IGNORE INTO platform_settings (setting_key, setting_value) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $ins->execute([$k, $v]);
    }
}

/**
 * Public product features that can be fully hidden from customers (UI + APIs).
 *
 * @return array<string,array{en:string,ar:string,default:bool,note_en:string,note_ar:string}>
 */
function rateb_feature_catalog(): array
{
    return [
        'feature_ai_strategy' => [
            'en' => 'AI strategy generation',
            'ar' => 'توليد الاستراتيجية',
            'default' => true,
            'note_en' => 'Hides strategy generation and blocks /app/ai/generate.php?stage=strategy.',
            'note_ar' => 'يخفي توليد الاستراتيجية ويمنع مسار التوليد بالكامل.',
        ],
        'feature_ai_copy' => [
            'en' => 'AI copy generation',
            'ar' => 'توليد النصوص الإعلانية',
            'default' => true,
            'note_en' => 'Hides copy generation and blocks /app/ai/generate.php?stage=copy.',
            'note_ar' => 'يخفي توليد النصوص ويمنع مسار التوليد بالكامل.',
        ],
        'feature_ai_image' => [
            'en' => 'AI image generation',
            'ar' => 'توليد الصور بالذكاء الاصطناعي',
            'default' => true,
            'note_en' => 'Hides AI image generation and blocks image APIs. Customer uploads stay available.',
            'note_ar' => 'يخفي توليد الصور بالـ AI ويمنع واجهات التوليد. رفع الصور من الجهاز يبقى متاحًا.',
        ],
        'feature_ai_voice' => [
            'en' => 'AI voice generation',
            'ar' => 'توليد الصوت',
            'default' => true,
            'note_en' => 'Hides the whole voice stage and blocks voice APIs.',
            'note_ar' => 'يخفي مرحلة الصوت بالكامل ويمنع واجهات الصوت.',
        ],
        'feature_ai_video' => [
            'en' => 'AI video generation',
            'ar' => 'توليد الفيديو',
            'default' => true,
            'note_en' => 'Hides AI video creation and blocks video APIs. Manual video upload can remain.',
            'note_ar' => 'يخفي إنشاء فيديو RATEB ويمنع واجهات الفيديو. رفع فيديو يدوي يمكن أن يبقى.',
        ],
    ];
}

function rateb_feature_enabled(string $key, ?PDO $pdo = null): bool
{
    $catalog = rateb_feature_catalog();
    if (!isset($catalog[$key])) {
        return false;
    }
    $default = !empty($catalog[$key]['default']);
    try {
        $pdo = $pdo ?? (function_exists('db') ? db() : null);
    } catch (Throwable $e) {
        return $default;
    }
    if (!$pdo instanceof PDO) {
        return $default;
    }
    ensure_platform_settings_schema($pdo);
    return platform_setting_bool($pdo, $key, $default);
}

/**
 * @return array<string,bool>
 */
function rateb_features(?PDO $pdo = null): array
{
    $out = [];
    foreach (array_keys(rateb_feature_catalog()) as $key) {
        $out[$key] = rateb_feature_enabled($key, $pdo);
    }
    return $out;
}

/** Block a disabled feature completely (API). */
function rateb_feature_require(string $key): void
{
    if (rateb_feature_enabled($key)) {
        return;
    }
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    $arabic = function_exists('ui_language') && ui_language() === 'ar';
    echo json_encode([
        'error' => $arabic ? 'هذه الميزة معطّلة من الإدارة.' : 'This feature is disabled by the administrator.',
        'code' => 'feature_disabled',
        'feature' => $key,
    ], JSON_UNESCAPED_UNICODE);
    exit;
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
