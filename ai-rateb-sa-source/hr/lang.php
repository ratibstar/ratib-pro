<?php
declare(strict_types=1);

$lang = ($_GET['lang'] ?? '') === 'en' ? 'en' : 'ar';
setcookie('rateb_ui_lang', $lang, ['expires' => time() + 86400 * 400, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => false, 'samesite' => 'Lax']);
$back = (string) ($_GET['back'] ?? '/hr/');
if (!str_starts_with($back, '/hr')) {
    $back = '/hr/';
}
header('Location: ' . $back);
exit;
