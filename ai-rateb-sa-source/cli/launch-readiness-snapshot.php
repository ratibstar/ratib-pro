<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/migrations/platform_settings_schema.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No database\n");
    exit(1);
}
if (!function_exists('db')) {
    function db(): PDO
    {
        $pdo = $GLOBALS['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            throw new RuntimeException('No database');
        }
        return $pdo;
    }
}

ensure_platform_settings_schema(db());
$ai = require dirname(__DIR__) . '/config/ai.php';

$presence = static function (string $key, mixed $value): string {
    $ok = is_string($value) ? trim($value) !== '' : !empty($value);
    return $key . '=' . ($ok ? 'present' : 'absent');
};

echo "PHP=" . PHP_VERSION . PHP_EOL;
echo "features=" . json_encode(rateb_features(db()), JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo "public_site=" . (platform_setting_bool(db(), 'public_site_enabled', true) ? '1' : '0') . PHP_EOL;
echo "maintenance=" . (platform_setting_bool(db(), 'maintenance_mode', false) ? '1' : '0') . PHP_EOL;
echo $presence('text_api_key', $ai['api_key'] ?? '') . PHP_EOL;
echo 'text_provider=' . (string) ($ai['provider'] ?? 'unset') . PHP_EOL;
echo 'text_base=' . (string) ($ai['base_url'] ?? '') . PHP_EOL;
echo $presence('ai_horde', $ai['horde_api_key'] ?? '') . PHP_EOL;
echo $presence('sawtak', $ai['sawtak_api_key'] ?? '') . PHP_EOL;
echo $presence('runway', getenv('RUNWAY_API_KEY') ?: ($ai['runway_api_key'] ?? '')) . PHP_EOL;
echo 'video_provider_env=' . (string) (getenv('RATEB_VIDEO_PROVIDER') ?: 'unset') . PHP_EOL;

$q = static function (string $sql) {
    return (int) db()->query($sql)->fetchColumn();
};

echo 'users=' . $q('SELECT COUNT(*) FROM users') . PHP_EOL;
echo 'campaigns=' . $q('SELECT COUNT(*) FROM campaigns') . PHP_EOL;
echo 'payment_receipts=' . $q("SELECT COUNT(*) FROM payment_receipts") . PHP_EOL;
echo 'payment_orders_paid=' . $q("SELECT COUNT(*) FROM payment_orders WHERE status IN ('paid','completed','captured')") . PHP_EOL;
echo 'staff_users=' . $q("SELECT COUNT(*) FROM admin_staff") . PHP_EOL;
echo 'usage_completed=' . $q("SELECT COUNT(*) FROM usage_events WHERE status='completed'") . PHP_EOL;
echo 'usage_failed=' . $q("SELECT COUNT(*) FROM usage_events WHERE status='failed'") . PHP_EOL;
echo 'video_jobs_completed=' . $q("SELECT COUNT(*) FROM video_jobs WHERE status='completed'") . PHP_EOL;
echo 'video_jobs_failed=' . $q("SELECT COUNT(*) FROM video_jobs WHERE status='failed'") . PHP_EOL;
echo 'video_jobs_rejected=' . $q("SELECT COUNT(*) FROM video_jobs WHERE status='rejected' OR approval_status='rejected'") . PHP_EOL;
echo 'outbound_email_sent=' . $q("SELECT COUNT(*) FROM outbound_email_log WHERE status='sent'") . PHP_EOL;
echo 'outbound_email_failed=' . $q("SELECT COUNT(*) FROM outbound_email_log WHERE status='failed'") . PHP_EOL;

$plans = db()->query('SELECT code, price_sar, is_active, name_ar FROM plans ORDER BY price_sar')->fetchAll(PDO::FETCH_ASSOC);
echo 'plans=' . json_encode($plans, JSON_UNESCAPED_UNICODE) . PHP_EOL;
echo $presence('moyasar_secret', $ai['moyasar_secret_key'] ?? '') . PHP_EOL;
echo $presence('moyasar_webhook', $ai['moyasar_webhook_secret'] ?? '') . PHP_EOL;

echo 'ffmpeg=' . (trim((string) shell_exec('command -v ffmpeg')) !== '' ? 'yes' : 'no') . PHP_EOL;
$cron = trim((string) shell_exec('crontab -l 2>/dev/null'));
echo 'cron_has_render_videos=' . (str_contains($cron, 'render-videos') ? 'yes' : 'no') . PHP_EOL;
echo 'cron_has_reconcile=' . (str_contains($cron, 'reconcile-pending-payments') ? 'yes' : 'no') . PHP_EOL;
echo 'cron_has_subscription=' . (str_contains($cron, 'enforce-subscription-expiry') || str_contains($cron, 'notify-subscription') ? 'yes' : 'no') . PHP_EOL;
echo 'cron_lines=' . substr_count($cron, "\n") . PHP_EOL;
