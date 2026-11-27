<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/sawtak.php';
require_once __DIR__ . '/easyvoice.php';
require_once __DIR__ . '/namaa.php';
rateb_feature_require('feature_ai_voice');

try {
    $catalog = rateb_sawtak_catalog();
} catch (RuntimeException $error) {
    $catalog = ['ok' => false, 'code' => $error->getMessage() === 'auth' ? 'auth' : 'catalog', 'voices' => [], 'pages' => 0, 'complete' => false];
}
$voices = is_array($catalog['voices'] ?? null) ? $catalog['voices'] : [];
$arabic = ui_language() === 'ar';
// Arabic-first campaign UI: keep Arabic/dialect voices only (hide English catalog leakage).
if ($arabic) {
    $voices = array_values(array_filter($voices, static function ($voice): bool {
        if (!is_array($voice)) {
            return false;
        }
        $dialect = strtolower((string) ($voice['dialect'] ?? ''));
        $name = (string) ($voice['name'] ?? '');
        if (str_starts_with($dialect, 'en') || str_contains($dialect, 'english')) {
            return false;
        }
        if (preg_match('/\bEnglish\b/i', $name) === 1) {
            return false;
        }
        return true;
    }));
}
// NAMAA is the target Saudi provider — always listed first. No silent EasyVoice substitution.
$voices = array_merge(rateb_namaa_saudi_voices(), $voices);
if (rateb_easyvoice_configured()) {
    $voices = array_merge($voices, rateb_easyvoice_free_arabic_voices());
}
$catalog['ok'] = true;
$catalog['code'] = '';
$messages = $arabic
    ? ['not_configured' => 'كتالوج الأصوات غير مُعد.', 'auth' => 'تعذر التحقق من كتالوج الأصوات.', 'catalog' => 'تعذر تحميل كتالوج الأصوات.']
    : ['not_configured' => 'The voice catalog is not configured.', 'auth' => 'The voice catalog could not be authenticated.', 'catalog' => 'The voice catalog could not be loaded.'];
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => $catalog['ok'],
    'error' => $catalog['ok'] ? '' : ($messages[$catalog['code']] ?? $messages['catalog']),
    'voices' => $voices,
    'pages' => $catalog['pages'],
    'complete' => $catalog['complete'],
    'target_provider' => 'namaa',
    'default_voice' => 'namaa_saudi_m1',
    'language_field' => false,
    'region_field' => false,
], JSON_UNESCAPED_UNICODE);
