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
$features = rateb_features(db());
echo json_encode($features, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
$settings = (string) file_get_contents(dirname(__DIR__) . '/admin/settings.php');
$campaign = (string) file_get_contents(dirname(__DIR__) . '/campaign.php');
echo (str_contains($settings, 'feature_settings') ? "settings_ui=ok\n" : "settings_ui=missing\n");
echo (str_contains($campaign, '$featAiImage') ? "campaign_ui=ok\n" : "campaign_ui=missing\n");
$stmt = db()->query("SELECT setting_key, setting_value FROM platform_settings WHERE setting_key LIKE 'feature_%' ORDER BY setting_key");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo $row['setting_key'] . '=' . $row['setting_value'] . PHP_EOL;
}
