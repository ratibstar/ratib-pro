<?php
declare(strict_types=1);

require_once __DIR__ . '/app/brand/library.php';
require_login();

$params = ['brand' => '1'];
if (($_GET['saved'] ?? '') === '1') {
    $params['saved'] = '1';
}
$error = (string) ($_GET['brand_error'] ?? '');
if (in_array($error, ['size', 'type', 'upload'], true)) {
    $params['brand_error'] = $error;
}
header('Location: /dashboard?' . http_build_query($params));
exit;
