<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$lang = (string) ($_GET['lang'] ?? '');
if ($lang === 'ar' || $lang === 'en') {
    setcookie('rateb_admin_lang', $lang, [
        'expires' => time() + 31536000,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}
$back = (string) ($_SERVER['HTTP_REFERER'] ?? '/admin/');
if (!str_starts_with($back, '/admin')) {
    $back = '/admin/';
}
header('Location: ' . $back);
exit;
