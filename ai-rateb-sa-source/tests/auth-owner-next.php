<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/session.php';
require dirname(__DIR__) . '/config/guard.php';
require dirname(__DIR__) . '/admin/lib.php';

$failures = 0;
$check = static function (bool $ok, string $name) use (&$failures): void {
    if ($ok) {
        echo "PASS {$name}\n";
        return;
    }
    $failures++;
    echo "FAIL {$name}\n";
};

$check(rateb_safe_next('/admin', 'owner') === '/admin', 'owner may use /admin');
$check(rateb_safe_next('/admin/', 'owner') === '/admin/', 'owner trailing slash still allowed');
$check(rateb_safe_next('/admin', 'user') === '/dashboard', 'customer cannot use /admin');
$check(rateb_safe_next('/admin', null) === '/dashboard', 'anonymous cannot use /admin');
$check(rateb_safe_next('https://evil.example/', 'owner') === '/dashboard', 'reject absolute URL');
$check(rateb_safe_next('//evil.example/', 'owner') === '/dashboard', 'reject protocol-relative URL');
$check(rateb_safe_next('/admin\\foo', 'owner') === '/dashboard', 'reject backslashes');
$check(rateb_safe_next('/dashboard?x=1', 'owner') === '/dashboard', 'reject query string');
$check(rateb_safe_next('/dashboard#x', 'owner') === '/dashboard', 'reject fragment');
$check(rateb_safe_next('/../admin/', 'owner') === '/dashboard', 'reject parent segments');
$check(rateb_safe_next('/dashboard', 'user') === '/dashboard', 'customer dashboard allowed');
$check(rateb_safe_next('/campaign-new.php', 'user') === '/campaign-new.php', 'customer campaign-new allowed');
$check(rateb_normalize_next_path('/admin') === '/admin', 'normalize preserves /admin');

$staffRow = [
    'admin_staff_id' => 5,
    'staff_email' => 'support@rateb.sa',
    'staff_name' => 'Support',
    'email' => '',
];
$ownerRow = ['admin_staff_id' => 0, 'staff_email' => '', 'staff_name' => '', 'email' => 'admin@rateb.sa'];
$check(
    admin_audit_actor_display($staffRow, 'missing') === 'Support · support@rateb.sa',
    'customer audit shows staff actor'
);
$check(admin_audit_actor_display($ownerRow, 'missing') === 'admin@rateb.sa', 'customer audit shows owner email');

exit($failures > 0 ? 1 : 0);
