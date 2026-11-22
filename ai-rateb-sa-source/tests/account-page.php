<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/app/public-gate.php';

$fail = 0;
$assert = static function (bool $ok, string $label) use (&$fail): void {
    echo ($ok ? 'OK  ' : 'FAIL ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
};

$assert(is_file($root . '/account.php'), 'account page exists');
$assert(is_file($root . '/app/account/save.php'), 'account save exists');
$assert(rateb_public_gate_is_always_exempt('/account'), 'account exempt from public gate');
$assert(rateb_public_gate_is_always_exempt('/app/account/save.php'), 'account save exempt');

$htaccess = (string) file_get_contents($root . '/.htaccess');
$assert(str_contains($htaccess, 'RewriteRule ^account/?$ account.php'), 'account rewrite');

$nav = (string) file_get_contents($root . '/app/nav.php');
$assert(str_contains($nav, "['/account', 'nav_account', 'My account']"), 'nav has account link');

$js = (string) file_get_contents($root . '/public/assets/js/app.js');
$assert(str_contains($js, "nav_account: 'حسابي'"), 'AR account nav label');
$assert(str_contains($js, "account_save_password: 'تحديث كلمة المرور'"), 'AR password save label');

$page = (string) file_get_contents($root . '/account.php');
$assert(str_contains($page, 'name="action" value="profile"'), 'profile form');
$assert(str_contains($page, 'name="action" value="password"'), 'password form');
$assert(str_contains($page, 'readonly'), 'email readonly');

$save = (string) file_get_contents($root . '/app/account/save.php');
$assert(str_contains($save, 'password_verify'), 'password change checks current');
$assert(str_contains($save, 'UPDATE users SET name'), 'name update query');

exit($fail > 0 ? 1 : 0);
