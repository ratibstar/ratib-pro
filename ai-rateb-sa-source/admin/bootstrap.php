<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';
require_once dirname(__DIR__) . '/app/plans.php';
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/ui.php';
require_once __DIR__ . '/auth.php';

if (!isset($adminActor)) {
    $adminActor = require_admin_access($pdo);
}
$adminUser = admin_actor_legacy_user($adminActor);
$adminLang = (($_COOKIE['rateb_admin_lang'] ?? '') === 'en') ? 'en' : 'ar';

function admin_t(string $en, string $ar): string
{
    global $adminLang;
    return $adminLang === 'ar' ? $ar : $en;
}

function admin_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function admin_nav_on(string $here, string $href): bool
{
    if ($href === '/admin') {
        return $here === '/admin' || $here === '/admin/' || $here === '/admin/index.php';
    }
    $path = parse_url($href, PHP_URL_PATH) ?: $href;
    $query = (string) (parse_url($href, PHP_URL_QUERY) ?? '');
    if ($path === '/admin/finance.php') {
        $tab = (string) ($_GET['tab'] ?? '');
        $hrefTab = (string) (parse_url($href, PHP_URL_QUERY) ?? '');
        if ($query === 'tab=customers' || str_contains($hrefTab, 'tab=customers')) {
            return ($here === '/admin/finance.php' && $tab === 'customers')
                || str_starts_with($here, '/admin/finance-customer.php');
        }
        if ($query === 'tab=reconcile' || str_contains($hrefTab, 'tab=reconcile')) {
            return $here === '/admin/finance.php' && $tab === 'reconcile';
        }
        if ($query === '' && $hrefTab === '') {
            return $here === '/admin/finance.php' && ($tab === '' || $tab === 'overview');
        }
        return $here === '/admin/finance.php' && $tab === ''
            || str_starts_with($here, '/admin/finance-export.php');
    }
    if ($here === $path) {
        return true;
    }
    if ($path === '/admin/users.php' && str_starts_with($here, '/admin/user.php')) {
        return true;
    }
    if ($path === '/admin/hr-overview.php' && ($here === '/admin/hr-overview.php' || str_starts_with($here, '/admin/hr-customer.php'))) {
        return true;
    }
    if ($path === '/admin/hr-customer.php' && str_starts_with($here, '/admin/hr-customer.php')) {
        return true;
    }
    if (str_starts_with($href, '/admin/finance-export.php') && str_starts_with($here, '/admin/finance-export.php')) {
        return true;
    }
    if ($path === '/admin/refunds.php' && $here === '/admin/refunds.php') {
        return true;
    }
    if ($path === '/admin/campaigns.php' && str_starts_with($here, '/admin/campaign.php')) {
        return true;
    }
    if ($path === '/admin/staff.php' && $here === '/admin/staff.php') {
        return true;
    }
    if ($path === '/admin/roles.php' && $here === '/admin/roles.php') {
        return true;
    }
    return false;
}

function admin_start(string $title): void
{
    global $adminLang, $adminUser, $adminActor;
    $dir = $adminLang === 'ar' ? 'rtl' : 'ltr';
    $here = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/admin'), PHP_URL_PATH) ?: '/admin';
    $email = (string) ($adminUser['email'] ?? '');
    $initial = strtoupper(substr($email, 0, 1) ?: 'R');
    $console = admin_actor_is_owner($adminActor) ? admin_t('Owner console', 'وحدة المالك') : admin_t('Staff console', 'وحدة الموظف');
    echo '<!doctype html><html lang="' . admin_h($adminLang) . '" dir="' . $dir . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . admin_h($title) . ' — RATEB AI</title>';
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">';
    echo '<link rel="stylesheet" href="/admin/admin.css?v=15"></head><body class="admin-app">';
    echo '<input class="nav-check" id="nav-check" type="checkbox">';
    echo '<div class="shell"><aside class="side"><div class="brand"><b>RATEB</b> <span>AI</span></div><p class="role">' . admin_h(admin_t('Administration', 'الإدارة')) . '</p><nav class="nav-sections">';
    $groups = admin_nav_groups_for_actor($adminActor, $adminLang);
    foreach ($groups as $group => $links) {
        $sectionOpen = false;
        foreach ($links as $href => $label) {
            if (admin_nav_on($here, $href)) {
                $sectionOpen = true;
                break;
            }
        }
        echo '<details class="nav-section"' . ($sectionOpen ? ' open' : '') . '>';
        echo '<summary class="nav-section-title">' . admin_h($group) . '</summary>';
        echo '<div class="nav-section-links">';
        foreach ($links as $href => $label) {
            $on = admin_nav_on($here, $href);
            $glyph = admin_nav_glyph($href);
            echo '<a class="' . ($on ? 'on' : '') . '" href="' . admin_h($href) . '"><span class="nav-ico" aria-hidden="true">' . admin_h($glyph) . '</span>' . admin_h($label) . '</a>';
        }
        echo '</div></details>';
    }
    echo '</nav></aside><div class="main">';
    echo '<header class="topbar"><label class="menu" for="nav-check">' . admin_h(admin_t('Menu', 'القائمة')) . '</label><div class="top-title"><p>' . admin_h($console) . '</p><h1>' . admin_h($title) . '</h1></div>';
    echo '<div class="top-tools"><a class="lang" href="/admin/lang.php?lang=' . ($adminLang === 'ar' ? 'en' : 'ar') . '">' . admin_h($adminLang === 'ar' ? 'English' : 'العربية') . '</a>';
    if (($adminActor['type'] ?? '') === 'staff') {
        echo '<a class="lang" href="/admin/logout.php">' . admin_h(admin_t('Sign out', 'خروج')) . '</a>';
    }
    echo '<span class="who"><i>' . admin_h($initial) . '</i><span>' . admin_h($email) . '</span></span></div></header><main class="content-area">';
}

function admin_end(): void
{
    $year = date('Y');
    echo '</main><footer class="admin-footer">RATEB AI · ' . admin_h(admin_t('Administration', 'الإدارة')) . ' · ' . admin_h($year) . '</footer></div></div>';
    echo '<script src="/admin/admin.js?v=1" defer></script></body></html>';
}

function admin_pager(int $page, int $total, string $base): void
{
    $pages = max(1, (int) ceil($total / admin_per_page()));
    if ($pages < 2) {
        return;
    }
    echo '<nav class="pager">';
    if ($page > 1) {
        echo '<a href="' . admin_h($base . ($page - 1)) . '">' . admin_h(admin_t('Previous', 'السابق')) . '</a>';
    }
    echo '<span>' . (int) $page . ' / ' . (int) $pages . '</span>';
    if ($page < $pages) {
        echo '<a href="' . admin_h($base . ($page + 1)) . '">' . admin_h(admin_t('Next', 'التالي')) . '</a>';
    }
    echo '</nav>';
}

function admin_flash(string $code): string
{
    return match ($code) {
        'applied' => admin_t('Saved.', 'تم الحفظ.'),
        'duplicate' => admin_t('This change was already applied.', 'طُبّق هذا التغيير من قبل.'),
        'balance' => admin_t('The balance cannot go below zero.', 'لا يمكن أن يصبح الرصيد سالبًا.'),
        'reason' => admin_t('Enter a reason of at least 3 characters.', 'اكتب سببًا من 3 أحرف على الأقل.'),
        'owner' => admin_t('The owner account cannot be disabled.', 'لا يمكن تعطيل حساب المالك.'),
        'self' => admin_t('You cannot change your own access this way.', 'لا يمكن تغيير وصولك بهذه الطريقة.'),
        'missing' => admin_t('The record was not found.', 'السجل غير موجود.'),
        'schema' => admin_t('Apply the admin migration before this action.', 'طبّق ترحيل الإدارة قبل هذا الإجراء.'),
        'saved' => admin_t('Saved.', 'تم الحفظ.'),
        'owner_only' => admin_t('Only the platform owner can do this.', 'فقط مالك المنصة يمكنه ذلك.'),
        default => admin_t('The action was not completed.', 'لم يكتمل الإجراء.'),
    };
}
