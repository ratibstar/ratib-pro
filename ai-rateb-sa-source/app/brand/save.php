<?php
declare(strict_types=1);

require_once __DIR__ . '/library.php';

require_login();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
check_csrf();

$modal = (string) ($_POST['modal'] ?? '') === '1';
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
    if ($modal) {
        header('Location: /dashboard?brand=1&brand_error=' . rawurlencode($error->getMessage()));
    } else {
        header('Location: /brand.php?brand_error=' . rawurlencode($error->getMessage()));
    }
    exit;
}

$language = (string) ($_POST['preferred_language'] ?? 'ar');
if ($language !== 'ar' && $language !== 'en') {
    $language = 'ar';
}
$dialect = (string) ($_POST['dialect'] ?? '');
if (!in_array($dialect, ['', 'najdi', 'hijazi', 'khaleeji', 'fusha'], true)) {
    $dialect = '';
}
$stmt = db()->prepare('INSERT INTO brand_kits (user_id, brand_name, logo_stored, logo_mime, primary_color, secondary_color, preferred_language, tone, contact, audience, offer_line, forbidden, dialect) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE brand_name=VALUES(brand_name), logo_stored=VALUES(logo_stored), logo_mime=VALUES(logo_mime), primary_color=VALUES(primary_color), secondary_color=VALUES(secondary_color), preferred_language=VALUES(preferred_language), tone=VALUES(tone), contact=VALUES(contact), audience=VALUES(audience), offer_line=VALUES(offer_line), forbidden=VALUES(forbidden), dialect=VALUES(dialect)');
$stmt->execute([
    (int) $_SESSION['user_id'],
    mb_substr(trim((string) ($_POST['brand_name'] ?? '')), 0, 160),
    $logoStored,
    $logoMime,
    campaign_hex_color((string) ($_POST['primary_color'] ?? ''), '#0f766e'),
    campaign_hex_color((string) ($_POST['secondary_color'] ?? ''), '#111827'),
    $language,
    mb_substr(trim((string) ($_POST['tone'] ?? '')), 0, 160),
    mb_substr(trim((string) ($_POST['contact'] ?? '')), 0, 255),
    mb_substr(trim((string) ($_POST['audience'] ?? '')), 0, 255),
    mb_substr(trim((string) ($_POST['offer_line'] ?? '')), 0, 255),
    mb_substr(trim((string) ($_POST['forbidden'] ?? '')), 0, 500),
    $dialect,
]);
header('Location: ' . ($modal ? '/dashboard?brand=1&saved=1' : '/brand.php?saved=1'));
exit;
