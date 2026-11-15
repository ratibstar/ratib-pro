<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/migrations/admin_staff_schema.php';

/**
 * @return array<string,list<string>>
 */
function admin_permission_matrix(): array
{
    return admin_staff_permission_matrix_static();
}

/** @return list<string> */
function admin_permissions_for_role(string $roleCode): array
{
    if ($roleCode === 'owner') {
        return admin_staff_all_permission_codes();
    }
    $matrix = admin_permission_matrix();
    return $matrix[$roleCode] ?? [];
}

/**
 * @return array{type:string,user_id?:int,staff_id?:int,email:string,name:string,role_code:string,permissions:list<string>}
 */
function admin_actor_from_owner(array $user): array
{
    return [
        'type' => 'owner',
        'user_id' => (int) $user['id'],
        'email' => (string) $user['email'],
        'name' => (string) ($user['name'] ?? $user['email']),
        'role_code' => 'owner',
        'permissions' => admin_staff_all_permission_codes(),
    ];
}

/**
 * @return array{type:string,user_id?:int,staff_id?:int,email:string,name:string,role_code:string,permissions:list<string>}
 */
function admin_actor_from_staff(array $staff): array
{
  $role = (string) $staff['role_code'];
    return [
        'type' => 'staff',
        'staff_id' => (int) $staff['id'],
        'email' => (string) $staff['email'],
        'name' => (string) $staff['name'],
        'role_code' => $role,
        'permissions' => admin_permissions_for_role($role),
    ];
}

function admin_actor_has(array $actor, string $permission): bool
{
    if (($actor['type'] ?? '') === 'owner') {
        return true;
    }
    return in_array($permission, $actor['permissions'] ?? [], true);
}

function admin_require_permission(string $permission): void
{
    global $adminActor;
    if (!isset($adminActor) || !admin_actor_has($adminActor, $permission)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        $ar = (($_COOKIE['rateb_admin_lang'] ?? '') === 'en');
        echo $ar
            ? '<!doctype html><html lang="en" dir="ltr"><body><p>Access denied.</p></body></html>'
            : '<!doctype html><html lang="ar" dir="rtl"><body><p>الوصول مرفوض.</p></body></html>';
        exit;
    }
}

function admin_actor_is_owner(array $actor): bool
{
    return ($actor['type'] ?? '') === 'owner';
}

function admin_session_has_dual_identity(): bool
{
    return !empty($_SESSION['user_id']) && !empty($_SESSION['admin_staff_id']);
}

function admin_clear_dual_identity(): void
{
    if (!admin_session_has_dual_identity()) {
        return;
    }
    unset($_SESSION['admin_staff_id'], $_SESSION['user_id']);
}

function admin_invalidate_dual_admin_session(): void
{
    if (!admin_session_has_dual_identity()) {
        return;
    }
    admin_clear_dual_identity();
    header('Location: /admin');
    exit;
}

/**
 * @return array{type:string,user_id?:int,staff_id?:int,email:string,name:string,role_code:string,permissions:list<string>}|null
 */
function resolve_admin_actor(object $pdo): ?array
{
    admin_clear_dual_identity();

    if (!empty($_SESSION['admin_staff_id']) && admin_table_has($pdo, 'admin_staff', 'id')) {
        $stmt = $pdo->prepare("SELECT id, email, name, role_code, status FROM admin_staff WHERE id = ? LIMIT 1");
        $stmt->execute([(int) $_SESSION['admin_staff_id']]);
        $staff = $stmt->fetch();
        if (!$staff || (string) $staff['status'] !== 'active') {
            unset($_SESSION['admin_staff_id']);
            return null;
        }
        if ((string) $staff['role_code'] === 'owner') {
            unset($_SESSION['admin_staff_id']);
            return null;
        }
        return admin_actor_from_staff($staff);
    }

    if (!empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user && (string) ($user['role'] ?? '') === 'owner') {
            return admin_actor_from_owner($user);
        }
        if ($user) {
            http_response_code(403);
            header('Content-Type: text/html; charset=utf-8');
            $ar = (($_COOKIE['rateb_ui_lang'] ?? '') === 'ar');
            echo $ar
                ? '<!doctype html><html lang="ar" dir="rtl"><body><p>حساب العميل لا يمكنه الوصول إلى لوحة الإدارة.</p></body></html>'
                : '<!doctype html><html lang="en" dir="ltr"><body><p>Customer accounts cannot access the admin panel.</p></body></html>';
            exit;
        }
    }

    return null;
}

/**
 * @return array{type:string,user_id?:int,staff_id?:int,email:string,name:string,role_code:string,permissions:list<string>}
 */
function require_admin_access(object $pdo): array
{
    $actor = resolve_admin_actor($pdo);
    if ($actor === null) {
        header('Location: /admin');
        exit;
    }
    return $actor;
}

/** @return array{id:int,email:string,name:string,role:string} */
function admin_actor_legacy_user(array $actor): array
{
    return [
        'id' => $actor['type'] === 'owner' ? (int) ($actor['user_id'] ?? 0) : (int) ($actor['staff_id'] ?? 0),
        'email' => (string) $actor['email'],
        'name' => (string) $actor['name'],
        'role' => (string) $actor['role_code'],
    ];
}

/**
 * Canonical admin nav entries. Every item must declare its sidebar group explicitly
 * so new routes are not dropped by routing heuristics.
 *
 * @return list<array{permission:string,group:string,href:string,en:string,ar:string}>
 */
function admin_nav_items(): array
{
    return [
        ['permission' => 'dashboard.view', 'group' => 'overview', 'href' => '/admin', 'en' => 'Dashboard & analytics', 'ar' => 'لوحة التحليلات'],
        ['permission' => 'customers.view', 'group' => 'customers', 'href' => '/admin/users.php', 'en' => 'Customer accounts', 'ar' => 'حسابات العملاء'],
        ['permission' => 'customers.view', 'group' => 'customers', 'href' => '/admin/hr-overview.php', 'en' => 'Customer HR overview', 'ar' => 'موارد بشرية العملاء'],
        ['permission' => 'finance.view', 'group' => 'customers', 'href' => '/admin/finance.php?tab=customers', 'en' => 'Customer finances', 'ar' => 'مالية العملاء'],
        ['permission' => 'campaigns.view', 'group' => 'content', 'href' => '/admin/campaigns.php', 'en' => 'Campaigns', 'ar' => 'الحملات'],
        ['permission' => 'finance.view', 'group' => 'finance', 'href' => '/admin/finance.php', 'en' => 'Financial overview', 'ar' => 'الإدارة المالية'],
        ['permission' => 'finance.view', 'group' => 'finance', 'href' => '/admin/finance.php?tab=reconcile', 'en' => 'Payment reconciliation', 'ar' => 'مطابقة المدفوعات'],
        ['permission' => 'finance.view', 'group' => 'finance', 'href' => '/admin/refunds.php', 'en' => 'Refunds', 'ar' => 'الاستردادات'],
        ['permission' => 'finance.view', 'group' => 'finance', 'href' => '/admin/payments.php', 'en' => 'Payments', 'ar' => 'المدفوعات'],
        ['permission' => 'finance.view', 'group' => 'finance', 'href' => '/admin/subscriptions.php', 'en' => 'Subscriptions', 'ar' => 'الاشتراكات'],
        ['permission' => 'finance.view', 'group' => 'finance', 'href' => '/admin/plans.php', 'en' => 'Plans & packages', 'ar' => 'الباقات والخطط'],
        ['permission' => 'finance.export', 'group' => 'finance', 'href' => '/admin/finance-export.php?export=summary&range=30', 'en' => 'Export finance CSV', 'ar' => 'تصدير المالية CSV'],
        ['permission' => 'operations.view', 'group' => 'operations', 'href' => '/admin/usage.php', 'en' => 'Credits & usage', 'ar' => 'النقاط والاستخدام'],
        ['permission' => 'operations.view', 'group' => 'operations', 'href' => '/admin/usage.php#providers', 'en' => 'AI providers', 'ar' => 'مزوّدو الذكاء'],
        ['permission' => 'operations.view', 'group' => 'system', 'href' => '/admin/audit.php', 'en' => 'Audit log', 'ar' => 'سجل التدقيق'],
        ['permission' => 'settings.manage', 'group' => 'system', 'href' => '/admin/settings.php', 'en' => 'System & public site', 'ar' => 'النظام والموقع العام'],
        ['permission' => 'staff.manage', 'group' => 'security', 'href' => '/admin/staff.php', 'en' => 'Admin staff', 'ar' => 'موظفو المنصة'],
        ['permission' => 'roles.manage', 'group' => 'security', 'href' => '/admin/roles.php', 'en' => 'Roles & permissions', 'ar' => 'الأدوار والصلاحيات'],
    ];
}

/** @return array<string,string> */
function admin_nav_section_labels(string $lang): array
{
    return $lang === 'ar'
        ? [
            'overview' => 'نظرة عامة والتحليلات',
            'customers' => 'العملاء وإدارتهم',
            'content' => 'المحتوى والحملات',
            'finance' => 'الإدارة المالية',
            'operations' => 'التشغيل والاستخدام',
            'system' => 'النظام والامتثال',
            'security' => 'أمان المنصة',
        ]
        : [
            'overview' => 'Overview & analytics',
            'customers' => 'Customers',
            'content' => 'Content & campaigns',
            'finance' => 'Finance',
            'operations' => 'Operations & usage',
            'system' => 'System & compliance',
            'security' => 'Platform security',
        ];
}

/** @return array<string,array<string,array{en:string,ar:string}>> */
function admin_nav_catalog(): array
{
    $out = [];
    foreach (admin_nav_items() as $item) {
        $out[$item['permission']][$item['href']] = ['en' => $item['en'], 'ar' => $item['ar']];
    }
    return $out;
}

/** @return array<string,array<string,string>> */
function admin_nav_groups_for_actor(array $actor, string $lang): array
{
    $labels = admin_nav_section_labels($lang);
    $order = ['overview', 'customers', 'content', 'finance', 'operations', 'system', 'security'];
    $bucket = [];
    foreach (admin_nav_items() as $item) {
        if (!admin_actor_has($actor, $item['permission'])) {
            continue;
        }
        $key = $item['group'];
        $bucket[$key][$item['href']] = $lang === 'ar' ? $item['ar'] : $item['en'];
    }
    $out = [];
    foreach ($order as $key) {
        if (!isset($bucket[$key]) || $bucket[$key] === []) {
            continue;
        }
        $out[$labels[$key] ?? $key] = $bucket[$key];
    }
    foreach ($bucket as $key => $links) {
        if (isset($labels[$key]) && isset($out[$labels[$key]])) {
            continue;
        }
        $out[$labels[$key] ?? $key] = $links;
    }
    return $out;
}

function admin_normalize_login_email(string $email): string
{
    $email = strtolower(trim($email));
    if ($email !== '' && !str_contains($email, '@')) {
        $email .= '@rateb.sa';
    }
    return $email;
}

/**
 * @return array{ok:bool,code:string,user?:array{id:int,name:string,email:string,role:string}}
 */
function admin_owner_authenticate(object $pdo, string $email, string $password): array
{
    $email = admin_normalize_login_email($email);
    if ($email === '' || $password === '') {
        return ['ok' => false, 'code' => 'invalid'];
    }
    $stmt = $pdo->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        return ['ok' => false, 'code' => 'invalid'];
    }
    require_once dirname(__DIR__) . '/app/auth/account-state.php';
    if (rateb_user_is_disabled($pdo, (int) $user['id'])) {
        return ['ok' => false, 'code' => 'disabled'];
    }
    if ((string) ($user['role'] ?? '') !== 'owner') {
        return ['ok' => false, 'code' => 'customer'];
    }
    return [
        'ok' => true,
        'code' => 'applied',
        'user' => [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ],
    ];
}

function admin_owner_login_session(array $user): void
{
    session_regenerate_id(true);
    unset($_SESSION['admin_staff_id']);
    $_SESSION['user_id'] = (int) $user['id'];
}

function admin_staff_authenticate(object $pdo, string $email, string $password): array
{
    $email = admin_normalize_login_email($email);
    if ($email === '' || $password === '') {
        return ['ok' => false, 'code' => 'invalid'];
    }
    if (!admin_table_has($pdo, 'admin_staff', 'id')) {
        return ['ok' => false, 'code' => 'schema'];
    }
    $stmt = $pdo->prepare('SELECT id, email, name, password_hash, role_code, status FROM admin_staff WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $staff = $stmt->fetch();
    if (!$staff || !password_verify($password, (string) $staff['password_hash'])) {
        return ['ok' => false, 'code' => 'invalid'];
    }
    if ((string) $staff['status'] !== 'active') {
        return ['ok' => false, 'code' => 'disabled'];
    }
    if ((string) $staff['role_code'] === 'owner') {
        return ['ok' => false, 'code' => 'owner'];
    }
    return ['ok' => true, 'code' => 'applied', 'staff' => $staff];
}

function admin_staff_login_session(array $staff): void
{
    session_regenerate_id(true);
    unset($_SESSION['user_id']);
    $_SESSION['admin_staff_id'] = (int) $staff['id'];
}

function admin_staff_logout_session(): void
{
    unset($_SESSION['admin_staff_id']);
}

function admin_staff_touch_login(object $pdo, int $staffId): void
{
    if ($staffId < 1 || !admin_table_has($pdo, 'admin_staff', 'last_login_at')) {
        return;
    }
    $pdo->prepare('UPDATE admin_staff SET last_login_at = NOW() WHERE id = ?')->execute([$staffId]);
}
