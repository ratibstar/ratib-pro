<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/app/plans.php';

$adminUser = require_admin($pdo);
$adminLang = ui_language();

function admin_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function admin_start(string $title): void
{
    global $adminLang;
    $dir = $adminLang === 'ar' ? 'rtl' : 'ltr';
    echo '<!doctype html><html lang="' . admin_h($adminLang) . '" dir="' . $dir . '" data-theme="light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . admin_h($title) . '</title><link rel="stylesheet" href="/admin/admin.css"></head><body>';
    echo '<header><strong>RATEB AI</strong><nav>';
    $links = [
        '/admin/' => $adminLang === 'ar' ? 'اللوحة' : 'Dashboard',
        '/admin/users.php' => $adminLang === 'ar' ? 'المستخدمون' : 'Users',
        '/admin/plans.php' => $adminLang === 'ar' ? 'الباقات' : 'Plans',
        '/admin/subscriptions.php' => $adminLang === 'ar' ? 'الاشتراكات' : 'Subscriptions',
        '/admin/usage.php' => $adminLang === 'ar' ? 'الاستخدام' : 'Usage',
        '/admin/campaigns.php' => $adminLang === 'ar' ? 'الحملات' : 'Campaigns',
    ];
    foreach ($links as $href => $label) {
        echo '<a href="' . admin_h($href) . '">' . admin_h($label) . '</a>';
    }
    echo '</nav></header><main><h1>' . admin_h($title) . '</h1>';
}

function admin_end(): void
{
    echo '</main></body></html>';
}
