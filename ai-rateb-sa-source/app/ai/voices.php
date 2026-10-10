<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/sawtak.php';
require_login();

try {
    $catalog = rateb_sawtak_catalog();
} catch (RuntimeException $error) {
    $catalog = ['ok' => false, 'code' => $error->getMessage() === 'auth' ? 'auth' : 'catalog', 'voices' => [], 'pages' => 0, 'complete' => false];
}
$arabic = ui_language() === 'ar';
$messages = $arabic
    ? ['not_configured' => 'كتالوج الأصوات غير مُعد. أضف SAWTAK_API_KEY على الخادم.', 'auth' => 'تعذر التحقق من كتالوج الأصوات.', 'catalog' => 'تعذر تحميل كتالوج الأصوات.']
    : ['not_configured' => 'The voice catalog is not configured. Set SAWTAK_API_KEY on the server.', 'auth' => 'The voice catalog could not be authenticated.', 'catalog' => 'The voice catalog could not be loaded.'];
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => $catalog['ok'],
    'error' => $catalog['ok'] ? '' : ($messages[$catalog['code']] ?? $messages['catalog']),
    'voices' => $catalog['voices'],
    'pages' => $catalog['pages'],
    'complete' => $catalog['complete'],
    'language_field' => false,
    'region_field' => false,
], JSON_UNESCAPED_UNICODE);
