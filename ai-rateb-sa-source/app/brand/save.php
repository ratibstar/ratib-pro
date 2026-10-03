<?php
declare(strict_types=1);

require_once __DIR__ . '/library.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$current = brand_owned();
$logoStored = $current['logo_stored'] ?? null;
$logoMime = $current['logo_mime'] ?? null;
try {
    if (!empty($_FILES['logo']['name'])) {
        $saved = brand_store_logo($_FILES['logo']);
        if ($saved['stored']) {
            $logoStored = $saved['stored'];
            $logoMime = $saved['mime'];
        }
    }
} catch (RuntimeException $error) {
    header('Location: /brand.php?brand_error=' . rawurlencode($error->getMessage()));
    exit;
}

$language = (string) ($_POST['preferred_language'] ?? 'en');
if ($language !== 'ar' && $language !== 'en') {
    $language = 'en';
}
$stmt = db()->prepare('INSERT INTO brand_kits (user_id, brand_name, logo_stored, logo_mime, primary_color, secondary_color, preferred_language, tone, contact) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE brand_name=VALUES(brand_name), logo_stored=VALUES(logo_stored), logo_mime=VALUES(logo_mime), primary_color=VALUES(primary_color), secondary_color=VALUES(secondary_color), preferred_language=VALUES(preferred_language), tone=VALUES(tone), contact=VALUES(contact)');
$stmt->execute([
    $_SESSION['user_id'],
    mb_substr(trim((string) ($_POST['brand_name'] ?? '')), 0, 160),
    $logoStored,
    $logoMime,
    campaign_hex_color((string) ($_POST['primary_color'] ?? ''), '#0f766e'),
    campaign_hex_color((string) ($_POST['secondary_color'] ?? ''), '#111827'),
    $language,
    mb_substr(trim((string) ($_POST['tone'] ?? '')), 0, 160),
    mb_substr(trim((string) ($_POST['contact'] ?? '')), 0, 255),
]);
header('Location: /brand.php?saved=1');
exit;
