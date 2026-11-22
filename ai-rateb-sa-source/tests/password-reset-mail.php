<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/app/auth/reset-lib.php';

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
};

$src = (string) file_get_contents($root . '/app/auth/reset-lib.php');
$assert(str_contains($src, 'rateb_mail_send'), 'deliver uses rateb_mail_send');
$assert(!str_contains($src, 'unset($email, $token);'), 'stub unset removed');
$assert(str_contains($src, 'reset-password.php?token='), 'reset link includes token');

$forgot = (string) file_get_contents($root . '/app/auth/forgot-password.php');
$assert(str_contains($forgot, 'reset_mail_disabled'), 'forgot page has disabled-mail key');
$assert(!str_contains($forgot, 'Email delivery is not configured yet, so no message was sent.'), 'hardcoded not-configured notice removed');

$js = (string) file_get_contents($root . '/public/assets/js/app.js');
$assert(str_contains($js, "reset_requested: 'If an account exists for that email, reset instructions were sent.'"), 'EN success copy');
$assert(str_contains($js, "reset_requested: 'إذا كان هناك حساب لهذا البريد فقد أُرسلت تعليمات الاستعادة.'"), 'AR success copy');
$assert(!str_contains($js, 'Email delivery is not configured yet, so no message was sent.'), 'EN old copy gone');

exit($fail > 0 ? 1 : 0);
