<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/bootstrap.php';
require dirname(__DIR__) . '/app/hr/hr-lib.php';
require __DIR__ . '/ui.php';

$hrCompanyId = hr_require_company($pdo);
$hrUser = current_user($pdo);
$hrLang = (($_COOKIE['rateb_ui_lang'] ?? '') === 'ar') ? 'ar' : 'en';

function hr_t(string $en, string $ar): string
{
    global $hrLang;
    return $hrLang === 'ar' ? $ar : $en;
}

function hr_h(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

/** @return list<array{title:array{en:string,ar:string},links:array<string,array{en:string,ar:string}>}> */
function hr_nav_sections(): array
{
    return [
        [
            'title' => ['en' => 'Overview', 'ar' => 'نظرة عامة'],
            'links' => [
                '/hr/' => ['en' => 'Dashboard', 'ar' => 'لوحة HR'],
            ],
        ],
        [
            'title' => ['en' => 'People', 'ar' => 'الأفراد'],
            'links' => [
                '/hr/employees.php' => ['en' => 'Employees', 'ar' => 'الموظفون'],
                '/hr/departments.php' => ['en' => 'Departments', 'ar' => 'الأقسام'],
            ],
        ],
        [
            'title' => ['en' => 'Operations', 'ar' => 'العمليات'],
            'links' => [
                '/hr/attendance.php' => ['en' => 'Attendance', 'ar' => 'الحضور'],
                '/hr/leave.php' => ['en' => 'Leave', 'ar' => 'الإجازات'],
                '/hr/documents.php' => ['en' => 'Documents', 'ar' => 'المستندات'],
                '/hr/payroll.php' => ['en' => 'Payroll', 'ar' => 'الرواتب'],
            ],
        ],
        [
            'title' => ['en' => 'Insights', 'ar' => 'التقارير'],
            'links' => [
                '/hr/reports.php' => ['en' => 'Reports', 'ar' => 'التقارير'],
            ],
        ],
    ];
}

function hr_link_active(string $href, string $here): bool
{
    if ($href === '/hr/') {
        return $here === '/hr/' || $here === '/hr/index.php';
    }
    return $here === $href || str_starts_with($here, $href);
}

function hr_start(string $title): void
{
    global $hrLang, $hrUser;
    $dir = $hrLang === 'ar' ? 'rtl' : 'ltr';
    $here = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/hr/'), PHP_URL_PATH) ?: '/hr/';
    $email = (string) ($hrUser['email'] ?? '');
    $initial = strtoupper(substr($email, 0, 1) ?: 'H');
    echo '<!doctype html><html lang="' . hr_h($hrLang) . '" dir="' . $dir . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . hr_h($title) . ' — RATEB AI HR</title>';
    echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=Tajawal:wght@400;500;700;800&display=swap">';
    echo '<link rel="stylesheet" href="/admin/admin.css?v=11"></head><body class="admin-app">';
    echo '<input class="nav-check" id="hr-nav" type="checkbox">';
    echo '<div class="shell"><aside class="side"><div class="brand"><b>RATEB</b> <span>AI</span></div>';
    echo '<p class="role">' . hr_h(hr_t('Company HR workspace', 'مساحة الموارد البشرية لشركتك')) . '</p><nav>';
    foreach (hr_nav_sections() as $section) {
        $sectionTitle = hr_t($section['title']['en'], $section['title']['ar']);
        $anyOpen = false;
        foreach ($section['links'] as $href => $_) {
            if (hr_link_active($href, $here)) {
                $anyOpen = true;
                break;
            }
        }
        echo '<details class="nav-section"' . ($anyOpen ? ' open' : '') . '><summary class="nav-section-title">' . hr_h($sectionTitle) . '</summary><div class="nav-section-links">';
        foreach ($section['links'] as $href => $labels) {
            $on = hr_link_active($href, $here);
            echo '<a class="' . ($on ? 'on' : '') . '" href="' . hr_h($href) . '">' . hr_h(hr_t($labels['en'], $labels['ar'])) . '</a>';
        }
        echo '</div></details>';
    }
    echo '<p class="nav-label" style="margin:12px 4px 6px;font-size:12px;color:var(--muted)">' . hr_h(hr_t('Campaigns', 'الحملات')) . '</p>';
    echo '<a href="/dashboard">' . hr_h(hr_t('Back to campaigns', 'العودة للحملات')) . '</a>';
    echo '</nav></aside><div class="main">';
    echo '<header class="topbar"><label class="menu" for="hr-nav">' . hr_h(hr_t('Menu', 'القائمة')) . '</label>';
    echo '<div class="top-title"><p>' . hr_h(hr_t('Human resources', 'الموارد البشرية')) . '</p><h1>' . hr_h($title) . '</h1></div>';
    echo '<div class="top-tools"><a class="lang" href="/hr/lang.php?lang=' . ($hrLang === 'ar' ? 'en' : 'ar') . '">' . hr_h($hrLang === 'ar' ? 'English' : 'العربية') . '</a>';
    echo '<span class="who"><i>' . hr_h($initial) . '</i><span>' . hr_h($email) . '</span></span></div></header><main>';
}

function hr_end(): void
{
    echo '</main></div></div></body></html>';
}

function hr_flash(string $code): string
{
    return match ($code) {
        'saved' => hr_t('Saved.', 'تم الحفظ.'),
        'deleted' => hr_t('Deleted.', 'تم الحذف.'),
        'invalid' => hr_t('Invalid request.', 'طلب غير صالح.'),
        'forbidden' => hr_t('Record not in your company.', 'السجل لا يخص شركتك.'),
        default => $code,
    };
}
