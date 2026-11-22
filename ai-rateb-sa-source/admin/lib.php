<?php
declare(strict_types=1);

function admin_public_site_url(): string
{
    return '/';
}

function admin_studio_url(): string
{
    return '/dashboard';
}

/** Admin shortcuts — full console map below analytics on the dashboard. */
function admin_customer_hub(?array $actor = null): void
{
    if ($actor === null) {
        global $adminActor;
        $actor = is_array($adminActor ?? null) ? $adminActor : ['type' => 'owner', 'permissions' => []];
    }
  echo '<h2 class="section-title hub-section-title">' . admin_h(admin_t('Console shortcuts', 'اختصارات النظام')) . '</h2>';
    echo '<p class="note hub-section-note">' . admin_h(admin_t(
        'All admin modules you can access. Analytics for the selected period are above.',
        'كل وحدات الإدارة المتاحة لك. التحليلات للفترة المحددة أعلاه.'
    )) . '</p>';
    echo '<section class="hub-cards" aria-label="' . admin_h(admin_t('Console shortcuts', 'اختصارات النظام')) . '">';
    if (function_exists('admin_nav_items') && function_exists('admin_actor_has') && function_exists('admin_nav_glyph')) {
        $seen = [];
        foreach (admin_nav_items() as $item) {
            if (!admin_actor_has($actor, $item['permission'])) {
                continue;
            }
            $href = (string) $item['href'];
            if (isset($seen[$href])) {
                continue;
            }
            $seen[$href] = true;
            $title = admin_t((string) $item['en'], (string) $item['ar']);
            $glyph = admin_nav_glyph($href);
            echo '<a class="hub-card" href="' . admin_h($href) . '">';
            echo '<span class="hub-glyph" aria-hidden="true">' . admin_h($glyph) . '</span>';
            echo '<strong>' . admin_h($title) . '</strong>';
            echo '<span class="hub-hint">' . admin_h($title) . '</span>';
            echo '</a>';
        }
    }
    echo '<a class="hub-card hub-card-public" href="' . admin_h(admin_public_site_url()) . '" target="_blank" rel="noopener">';
    echo '<span class="hub-glyph" aria-hidden="true">↗</span>';
    echo '<strong>' . admin_h(admin_t('Public website', 'الموقع العام')) . '</strong>';
    echo '<span class="hub-hint">' . admin_h(admin_t('Homepage & registration (new tab)', 'الصفحة الرئيسية والتسجيل (نافذة جديدة)')) . '</span>';
    echo '</a>';
    echo '<a class="hub-card" href="' . admin_h(admin_studio_url()) . '">';
    echo '<span class="hub-glyph" aria-hidden="true">◇</span>';
    echo '<strong>' . admin_h(admin_t('Customer studio', 'استوديو العملاء')) . '</strong>';
    echo '<span class="hub-hint">' . admin_h(admin_t('Same experience as logged-in customers', 'نفس تجربة العملاء بعد تسجيل الدخول')) . '</span>';
    echo '</a>';
    echo '</section>';
}

/** @param list<array{href?:string,label:string,class?:string,attrs?:array<string,string>}> $extra */
function admin_customers_nav_toolbar(array $actor, array $extra = []): void
{
    $links = [];
    if (admin_actor_has($actor, 'dashboard.view')) {
        $links[] = ['href' => '/admin', 'label' => admin_t('Dashboard', 'لوحة التحليلات'), 'class' => 'quiet'];
    }
    if (admin_actor_has($actor, 'customers.view')) {
        $links[] = ['href' => '/admin/users.php', 'label' => admin_t('Customer accounts', 'حسابات العملاء'), 'class' => 'quiet'];
        $links[] = ['href' => '/admin/hr-overview.php', 'label' => admin_t('Customer HR', 'موارد بشرية العملاء'), 'class' => 'quiet'];
    }
    if (admin_actor_has($actor, 'finance.view')) {
        $links[] = ['href' => '/admin/finance.php?tab=customers', 'label' => admin_t('Customer finances', 'مالية العملاء'), 'class' => 'quiet'];
    }
    if (admin_actor_has($actor, 'campaigns.view')) {
        $links[] = ['href' => '/admin/campaigns.php', 'label' => admin_t('Campaigns', 'الحملات'), 'class' => 'quiet'];
    }
    admin_toolbar(array_merge($links, $extra));
}

/** @param list<array{href?:string,label:string,class?:string,attrs?:array<string,string>}> $extra */
function admin_finance_nav_toolbar(array $actor, array $extra = []): void
{
    if (!admin_actor_has($actor, 'finance.view')) {
        if ($extra !== []) {
            admin_toolbar($extra);
        }
        return;
    }
    $links = [
        ['href' => '/admin/finance.php', 'label' => admin_t('Finance hub', 'مركز المالية'), 'class' => 'quiet'],
        ['href' => '/admin/payments.php', 'label' => admin_t('Payments', 'المدفوعات'), 'class' => 'quiet'],
        ['href' => '/admin/refunds.php', 'label' => admin_t('Refunds', 'الاستردادات'), 'class' => 'quiet'],
        ['href' => '/admin/subscriptions.php', 'label' => admin_t('Subscriptions', 'الاشتراكات'), 'class' => 'quiet'],
        ['href' => '/admin/plans.php', 'label' => admin_t('Plans', 'الباقات'), 'class' => 'quiet'],
        ['href' => '/admin/finance.php?tab=customers', 'label' => admin_t('Customer finances', 'مالية العملاء'), 'class' => 'quiet'],
    ];
    if (admin_actor_has($actor, 'finance.export')) {
        $links[] = ['href' => '/admin/finance-export.php?export=summary&range=30', 'label' => admin_t('Export CSV', 'تصدير CSV'), 'class' => 'quiet'];
    }
    admin_toolbar(array_merge($links, $extra));
}

/** @param list<array{href?:string,label:string,class?:string,attrs?:array<string,string>}> $extra */
function admin_operations_nav_toolbar(array $actor, array $extra = []): void
{
    $links = [];
    if (admin_actor_has($actor, 'dashboard.view')) {
        $links[] = ['href' => '/admin', 'label' => admin_t('Dashboard', 'لوحة التحليلات'), 'class' => 'quiet'];
    }
    if (admin_actor_has($actor, 'operations.view')) {
        $links[] = ['href' => '/admin/usage.php', 'label' => admin_t('Credits & usage', 'النقاط والاستخدام'), 'class' => 'quiet'];
        $links[] = ['href' => '/admin/audit.php', 'label' => admin_t('Audit log', 'سجل التدقيق'), 'class' => 'quiet'];
    }
    if (admin_actor_has($actor, 'settings.manage')) {
        $links[] = ['href' => '/admin/settings.php', 'label' => admin_t('System', 'النظام'), 'class' => 'quiet'];
    }
    if (admin_actor_has($actor, 'campaigns.view')) {
        $links[] = ['href' => '/admin/campaigns.php', 'label' => admin_t('Campaigns', 'الحملات'), 'class' => 'quiet'];
    }
    admin_toolbar(array_merge($links, $extra));
}

/** @param list<array{href?:string,label:string,class?:string,attrs?:array<string,string>}> $extra */
function admin_security_nav_toolbar(array $actor, array $extra = []): void
{
    $links = [];
    if (admin_actor_has($actor, 'staff.manage')) {
        $links[] = ['href' => '/admin/staff.php', 'label' => admin_t('Admin staff', 'موظفو المنصة'), 'class' => 'quiet'];
    }
    if (admin_actor_has($actor, 'roles.manage')) {
        $links[] = ['href' => '/admin/roles.php', 'label' => admin_t('Roles & permissions', 'الأدوار والصلاحيات'), 'class' => 'quiet'];
    }
    if (admin_actor_has($actor, 'operations.view')) {
        $links[] = ['href' => '/admin/audit.php', 'label' => admin_t('Audit log', 'سجل التدقيق'), 'class' => 'quiet'];
    }
    admin_toolbar(array_merge($links, $extra));
}

function admin_like(string $value): string
{
    $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    return '%' . $value . '%';
}

function admin_page(): int
{
    $page = (int) ($_GET['page'] ?? 1);
    return $page > 0 ? $page : 1;
}

function admin_per_page(): int
{
    return 10;
}

function admin_offset(int $page): int
{
    return ($page - 1) * admin_per_page();
}

function admin_clamp_page(int $page, int $total): int
{
    $pages = max(1, (int) ceil($total / admin_per_page()));
    if ($page < 1) {
        return 1;
    }
    return min($page, $pages);
}

/** Redirect when ?page= is past the last page (e.g. after bulk delete). */
function admin_enforce_page_in_range(string $path, int $page, int $total): int
{
    $clamped = admin_clamp_page($page, $total);
    if ($clamped === $page) {
        return $page;
    }
    $query = [];
    foreach ($_GET as $key => $value) {
        if ($key === 'page' || (!is_string($value) && !is_int($value))) {
            continue;
        }
        $query[$key] = (string) $value;
    }
    $query['page'] = (string) $clamped;
    $qs = http_build_query($query);
    header('Location: ' . $path . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

function admin_table_has(object $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = spl_object_id($pdo) . ':' . $table . ':' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $found = false;
    if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $column)) {
        $cache[$key] = false;
        return false;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    $found = (int) $stmt->fetchColumn() > 0;
    $cache[$key] = $found;
    return $found;
}

function admin_user_credit_form_visible(array $actor, array $targetUser): bool
{
    return ($actor['type'] ?? '') === 'owner' && (string) ($targetUser['role'] ?? '') !== 'owner';
}

/**
 * @param array<string,mixed> $row
 */
function admin_audit_actor_display(array $row, string $missingStaffLabel): string
{
    $staffId = (int) ($row['admin_staff_id'] ?? 0);
    $staffEmail = trim((string) ($row['staff_email'] ?? ''));
    $staffName = trim((string) ($row['staff_name'] ?? ''));
    if ($staffId > 0 || $staffEmail !== '') {
        if ($staffEmail === '' && $staffName === '') {
            return $missingStaffLabel;
        }
        if ($staffName !== '' && $staffEmail !== '') {
            return $staffName . ' · ' . $staffEmail;
        }
        return $staffEmail !== '' ? $staffEmail : $staffName;
    }
    $ownerEmail = trim((string) ($row['email'] ?? ''));
    return $ownerEmail !== '' ? $ownerEmail : '—';
}

require_once __DIR__ . '/lib-audit-target.php';

function admin_audit(object $pdo, array $actor, string $action, string $targetType, ?int $targetId, string $reason, string $detail): void
{
    if (!admin_table_has($pdo, 'admin_audit_log', 'id')) {
        return;
    }
    $userId = ($actor['type'] ?? '') === 'owner' ? (int) ($actor['user_id'] ?? 0) : null;
    $staffId = ($actor['type'] ?? '') === 'staff' ? (int) ($actor['staff_id'] ?? 0) : null;
  if ($userId === null && $staffId === null) {
        return;
    }
    $hasStaffCol = admin_table_has($pdo, 'admin_audit_log', 'admin_staff_id');
    if ($hasStaffCol) {
        $pdo->prepare('INSERT INTO admin_audit_log (admin_user_id, admin_staff_id, action, target_type, target_id, reason, detail) VALUES (?, ?, ?, ?, ?, ?, ?)')
            ->execute([$userId, $staffId, mb_substr($action, 0, 64), mb_substr($targetType, 0, 32), $targetId, mb_substr($reason, 0, 500), mb_substr($detail, 0, 1000)]);
        return;
    }
    if ($userId !== null) {
        $pdo->prepare('INSERT INTO admin_audit_log (admin_user_id, action, target_type, target_id, reason, detail) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$userId, mb_substr($action, 0, 64), mb_substr($targetType, 0, 32), $targetId, mb_substr($reason, 0, 500), mb_substr($detail, 0, 1000)]);
    }
}

/**
 * @return array{ok:bool,code:string,balance?:int}
 */
function admin_adjust_credits(object $pdo, array $actor, int $userId, int $delta, string $reason, string $key): array
{
    $reason = trim($reason);
    $adminLegacyId = ($actor['type'] ?? '') === 'owner' ? (int) ($actor['user_id'] ?? 0) : 0;
    if ($adminLegacyId < 1 || $userId < 1 || $delta === 0 || $delta < -100000 || $delta > 100000) {
        return ['ok' => false, 'code' => 'invalid'];
    }
    if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
        return ['ok' => false, 'code' => 'reason'];
    }
    if (preg_match('/^[a-f0-9]{32}$/', $key) !== 1) {
        return ['ok' => false, 'code' => 'key'];
    }
    $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $pdo->beginTransaction();
    try {
        $seen = $pdo->prepare('SELECT id, balance_after FROM admin_credit_adjustments WHERE idempotency_key = ?' . $lock);
        $seen->execute([$key]);
        $prior = $seen->fetch();
        if ($prior) {
            $pdo->commit();
            return ['ok' => true, 'code' => 'duplicate', 'balance' => (int) $prior['balance_after']];
        }
        $user = $pdo->prepare('SELECT id, credits_balance FROM users WHERE id = ?' . $lock);
        $user->execute([$userId]);
        $row = $user->fetch();
        if (!$row) {
            $pdo->rollBack();
            return ['ok' => false, 'code' => 'missing'];
        }
        $next = (int) $row['credits_balance'] + $delta;
        if ($next < 0) {
            $pdo->rollBack();
            return ['ok' => false, 'code' => 'balance'];
        }
        $updated = $pdo->prepare('UPDATE users SET credits_balance = ? WHERE id = ? AND credits_balance = ?');
        $updated->execute([$next, $userId, (int) $row['credits_balance']]);
        if ($updated->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'code' => 'conflict'];
        }
        $pdo->prepare('INSERT INTO admin_credit_adjustments (idempotency_key, admin_user_id, user_id, delta, balance_after, reason) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$key, $adminLegacyId, $userId, $delta, $next, $reason]);
        admin_audit($pdo, $actor, $delta > 0 ? 'credit_add' : 'credit_deduct', 'user', $userId, $reason, 'delta=' . $delta . ';balance=' . $next);
        $pdo->prepare("INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, NULL, 'admin_credit', 1, 'completed', 'admin_credit', ?)")
            ->execute([$userId, $delta > 0 ? -$delta : $delta]);
        $pdo->commit();
        return ['ok' => true, 'code' => 'applied', 'balance' => $next];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/**
 * @return array{ok:bool,code:string}
 */
function admin_set_account_state(object $pdo, array $actor, int $userId, bool $disabled, string $reason): array
{
    $reason = trim($reason);
    $actorOk = (($actor['type'] ?? '') === 'owner' && (int) ($actor['user_id'] ?? 0) > 0)
        || (($actor['type'] ?? '') === 'staff' && (int) ($actor['staff_id'] ?? 0) > 0);
    if (!$actorOk || $userId < 1 || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
        return ['ok' => false, 'code' => 'reason'];
    }
    if (($actor['type'] ?? '') === 'owner' && (int) ($actor['user_id'] ?? 0) === $userId) {
        return ['ok' => false, 'code' => 'self'];
    }
    $staffId = ($actor['type'] ?? '') === 'staff' ? (int) ($actor['staff_id'] ?? 0) : 0;
    if ($staffId > 0 && $staffId === $userId) {
        return ['ok' => false, 'code' => 'self'];
    }
    $pdo->beginTransaction();
    try {
        $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?' . $lock);
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->rollBack();
            return ['ok' => false, 'code' => 'missing'];
        }
        if ((string) $row['role'] === 'owner') {
            $pdo->rollBack();
            return ['ok' => false, 'code' => 'owner'];
        }
        if ($disabled) {
            $pdo->prepare('UPDATE users SET disabled_at = CURRENT_TIMESTAMP WHERE id = ? AND role <> \'owner\'')->execute([$userId]);
        } else {
            $pdo->prepare('UPDATE users SET disabled_at = NULL WHERE id = ?')->execute([$userId]);
        }
        admin_audit($pdo, $actor, $disabled ? 'account_disable' : 'account_enable', 'user', $userId, $reason, '');
        $pdo->commit();
        return ['ok' => true, 'code' => 'applied'];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

/**
 * Permanent removal of a customer account (not disable). Owner only.
 *
 * @return array{ok:bool,code:string}
 */
function admin_delete_customer_user(object $pdo, array $actor, int $userId, string $reason): array
{
    $reason = trim($reason);
    if (!admin_actor_is_owner($actor) || !admin_actor_has($actor, 'customers.manage')) {
        return ['ok' => false, 'code' => 'owner_only'];
    }
    if ($userId < 1 || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
        return ['ok' => false, 'code' => 'reason'];
    }
    if ((int) ($actor['user_id'] ?? 0) === $userId) {
        return ['ok' => false, 'code' => 'self'];
    }

    $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $stmt = $pdo->prepare('SELECT id, email, role FROM users WHERE id = ?' . $lock);
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        return ['ok' => false, 'code' => 'missing'];
    }
    if ((string) $row['role'] === 'owner') {
        return ['ok' => false, 'code' => 'owner'];
    }

    $pdo->beginTransaction();
    try {
        if (admin_table_has($pdo, 'video_jobs', 'user_id')) {
            $pdo->prepare('DELETE FROM video_jobs WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'campaign_media', 'user_id')) {
            $pdo->prepare('DELETE FROM campaign_media WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'campaign_outputs', 'campaign_id')) {
            $pdo->prepare('DELETE o FROM campaign_outputs o INNER JOIN campaigns c ON c.id = o.campaign_id WHERE c.user_id = ?')->execute([$userId]);
        }
        foreach (['campaign_items', 'campaign_variations', 'campaign_briefs'] as $table) {
            if (admin_table_has($pdo, $table, 'user_id')) {
                $pdo->prepare("DELETE FROM {$table} WHERE user_id = ?")->execute([$userId]);
            }
        }
        if (admin_table_has($pdo, 'campaigns', 'user_id')) {
            $pdo->prepare('DELETE FROM campaigns WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'usage_events', 'user_id')) {
            $pdo->prepare('DELETE FROM usage_events WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'subscriptions', 'user_id')) {
            $pdo->prepare('DELETE FROM subscriptions WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'payment_refunds', 'user_id')) {
            $pdo->prepare('DELETE FROM payment_refunds WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'payment_receipts', 'user_id')) {
            $pdo->prepare('DELETE FROM payment_receipts WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'payment_orders', 'user_id')) {
            $pdo->prepare('DELETE FROM payment_orders WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'admin_credit_adjustments', 'user_id')) {
            $pdo->prepare('DELETE FROM admin_credit_adjustments WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'brand_kits', 'user_id')) {
            $pdo->prepare('DELETE FROM brand_kits WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'password_resets', 'user_id')) {
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'welcome_grants', 'user_id')) {
            $pdo->prepare('DELETE FROM welcome_grants WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'signup_audit', 'user_id')) {
            $pdo->prepare('DELETE FROM signup_audit WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'outbound_email_log', 'user_id')) {
            $pdo->prepare('DELETE FROM outbound_email_log WHERE user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'hr_payroll_entries', 'company_user_id')) {
            $pdo->prepare('DELETE FROM hr_payroll_entries WHERE company_user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'hr_attendance', 'company_user_id')) {
            $pdo->prepare('DELETE FROM hr_attendance WHERE company_user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'hr_leave_requests', 'company_user_id')) {
            $pdo->prepare('DELETE FROM hr_leave_requests WHERE company_user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'hr_employees', 'company_user_id')) {
            $pdo->prepare('DELETE FROM hr_employees WHERE company_user_id = ?')->execute([$userId]);
        }
        if (admin_table_has($pdo, 'hr_departments', 'company_user_id')) {
            $pdo->prepare('DELETE FROM hr_departments WHERE company_user_id = ?')->execute([$userId]);
        }

        $deleted = $pdo->prepare("DELETE FROM users WHERE id = ? AND role <> 'owner'");
        $deleted->execute([$userId]);
        if ($deleted->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'code' => 'missing'];
        }
        admin_audit($pdo, $actor, 'user_delete', 'user', $userId, $reason, 'email=' . (string) $row['email']);
        $pdo->commit();
        return ['ok' => true, 'code' => 'applied'];
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
}

function admin_secret_present(string $value): bool
{
    return trim($value) !== '';
}

/** @return array<int,array{id:string,label:string,configured:bool,selected:bool,note:string}> */
function admin_provider_rows(array $cfg): array
{
    $deepseek = admin_secret_present((string) ($cfg['deepseek_present'] ?? ''));
    $groq = admin_secret_present((string) ($cfg['groq_present'] ?? ''));
    $rows = [
        ['id' => 'deepseek', 'label' => 'DeepSeek', 'configured' => $deepseek, 'selected' => $deepseek, 'note' => 'Text route when DEEPSEEK_API_KEY is set. Key presence is not a live health check.'],
        ['id' => 'groq', 'label' => 'Groq', 'configured' => $groq, 'selected' => !$deepseek && $groq, 'note' => 'Text route when DeepSeek is unset. Key presence is not a live health check.'],
        ['id' => 'horde', 'label' => 'AI Horde', 'configured' => admin_secret_present((string) ($cfg['horde_present'] ?? '')), 'selected' => false, 'note' => 'Image calls require workers at request time. Configuration is not uptime.'],
        ['id' => 'sawtak', 'label' => 'Sawtak TTS', 'configured' => admin_secret_present((string) ($cfg['sawtak_present'] ?? '')), 'selected' => false, 'note' => 'Arabic speech. No Orpheus integration exists in this application.'],
        ['id' => 'runway', 'label' => 'Runway', 'configured' => admin_secret_present((string) ($cfg['runway_present'] ?? '')), 'selected' => (string) ($cfg['video_provider'] ?? '') !== 'private', 'note' => 'Default video adapter. Selected does not mean the last job succeeded.'],
        ['id' => 'wan', 'label' => 'Private Wan adapter', 'configured' => admin_secret_present((string) ($cfg['wan_url_present'] ?? '')) && admin_secret_present((string) ($cfg['wan_token_present'] ?? '')), 'selected' => (string) ($cfg['video_provider'] ?? '') === 'private', 'note' => 'Separate HTTP process. This panel does not probe it.'],
    ];
    return $rows;
}

function admin_provider_snapshot(): array
{
    require_once dirname(__DIR__) . '/app/video/provider.php';
    $ai = require dirname(__DIR__) . '/config/ai.php';
    $flag = static fn (string $value): string => trim($value) !== '' ? '1' : '';
    $deepseek = $flag((string) getenv('DEEPSEEK_API_KEY'));
    $groqKey = $flag((string) getenv('RATEB_AI_API_KEY'));
    if ($groqKey === '' && $deepseek === '') {
        $groqKey = $flag((string) ($ai['api_key'] ?? ''));
    }
    return admin_provider_rows([
        'deepseek_present' => $deepseek,
        'groq_present' => $groqKey,
        'horde_present' => $flag((string) ($ai['horde_api_key'] ?? '')),
        'sawtak_present' => $flag((string) ($ai['sawtak_api_key'] ?? '')),
        'runway_present' => $flag((string) rateb_video_env('RUNWAY_API_KEY')),
        'wan_url_present' => $flag((string) rateb_video_env('RATEB_PRIVATE_VIDEO_URL')),
        'wan_token_present' => $flag((string) rateb_video_env('RATEB_PRIVATE_VIDEO_TOKEN')),
        'video_provider' => strtolower(trim((string) rateb_video_env('RATEB_VIDEO_PROVIDER'))),
    ]);
}

/** @return array<string,string> */
function admin_billing_truth(): array
{
    return [
        'limits' => 'subscriptions joined to plans is the source for image and video limits via rateb_lock_plan',
        'wallet' => 'users.credits_balance is the only spendable balance',
        'package_field' => 'users.package_type is written only by the Moyasar webhook',
        'expiry' => 'subscriptions.period_end is the stored period end; users.subscription_expires_at is unused',
        'payment' => 'payment_receipts is the paid-money record; public starter/pro prices come from plans (currently 111 and 399 SAR). Refunds are on /admin/refunds.php',
        'reconcile' => 'do not copy package_type onto subscriptions or grant credits automatically',
    ];
}

function admin_known_payment_credits(string $package): ?int
{
    return match ($package) {
        'starter' => 500,
        'pro' => 2500,
        default => null,
    };
}

/**
 * Canonical credit report.
 * A customer charge is the ledger row whose operation is the feature name
 * (ai_image, saudi_voice, text_campaign, video_storyboard) and whose
 * credits_deducted is positive. The matching reservation row (image, voice,
 * video, campaign_text) repeats that amount and must not be added again.
 * Refund rows store credits_deducted = 0; their amount is the feature price.
 * admin_credit rows are manual adjustments, not customer usage.
 *
 * @param array<int,array{operation:string,credits:int,events:int}> $rows
 * @return array{consumed:int,refunded:int,granted:int,adjusted_add:int,adjusted_deduct:int}
 */
function admin_credit_buckets(array $rows): array
{
    $price = [
        'text_campaign' => 5,
        'ai_image' => 10,
        'saudi_voice' => 15,
        'video_storyboard' => 20,
    ];
    $paymentCredits = ['payment_starter' => 500, 'payment_pro' => 2500];
    $consumed = 0;
    $refunded = 0;
    $granted = 0;
    $adjustedAdd = 0;
    $adjustedDeduct = 0;
    foreach ($rows as $row) {
        $operation = (string) ($row['operation'] ?? '');
        $credits = (int) ($row['credits'] ?? 0);
        $events = (int) ($row['events'] ?? 0);
        if ($operation === 'admin_credit') {
            if ($credits > 0) {
                $adjustedDeduct += $credits;
            } elseif ($credits < 0) {
                $adjustedAdd += -$credits;
            }
            continue;
        }
        if (isset($price[$operation]) && $credits > 0) {
            $consumed += $credits;
            continue;
        }
        if (str_ends_with($operation, '_refund')) {
            $base = substr($operation, 0, -strlen('_refund'));
            $refunded += ($price[$base] ?? 0) * max(0, $events);
            continue;
        }
        if (isset($paymentCredits[$operation])) {
            $granted += $paymentCredits[$operation] * max(0, $events);
        }
    }
    return [
        'consumed' => $consumed,
        'refunded' => $refunded,
        'granted' => $granted,
        'adjusted_add' => $adjustedAdd,
        'adjusted_deduct' => $adjustedDeduct,
    ];
}

function admin_halalas_to_sar(int $halalas): string
{
    $sign = $halalas < 0 ? '-' : '';
    $halalas = abs($halalas);
    return $sign . intdiv($halalas, 100) . '.' . str_pad((string) ($halalas % 100), 2, '0', STR_PAD_LEFT);
}

/**
 * @return array{key:string,start:string,end:string,from:string,to:string,label:string}
 */
function admin_finance_range(): array
{
    $key = (string) ($_GET['range'] ?? '30');
    $fromInput = trim((string) ($_GET['from'] ?? ''));
    $toInput = trim((string) ($_GET['to'] ?? ''));
    $allowed = ['today' => 1, '7' => 7, '30' => 30, '90' => 90, 'year' => 0];
    $end = new DateTimeImmutable('tomorrow');
    if ($key === 'custom' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromInput) === 1 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toInput) === 1) {
        $start = new DateTimeImmutable($fromInput);
        $last = new DateTimeImmutable($toInput);
        if ($start <= $last) {
            $end = $last->modify('+1 day');
            return [
                'key' => 'custom',
                'start' => $start->format('Y-m-d 00:00:00'),
                'end' => $end->format('Y-m-d 00:00:00'),
                'from' => $fromInput,
                'to' => $toInput,
                'label' => $fromInput . ' — ' . $toInput,
            ];
        }
    }
    if (!isset($allowed[$key])) {
        $key = '30';
    }
    if ($key === 'year') {
        $start = new DateTimeImmutable(date('Y-01-01'));
    } else {
        $start = (new DateTimeImmutable('today'))->modify('-' . ($allowed[$key] - 1) . ' days');
    }
    return [
        'key' => $key,
        'start' => $start->format('Y-m-d H:i:s'),
        'end' => $end->format('Y-m-d H:i:s'),
        'from' => '',
        'to' => '',
        'label' => $key,
    ];
}

/** @return array<int,array{label:string,value:int}> */
function admin_finance_series(PDO $pdo, string $bucketExpr, string $start, string $end): array
{
    $stmt = $pdo->prepare(sprintf(
        'SELECT %s AS bucket, COALESCE(SUM(amount),0) AS total FROM payment_receipts WHERE created_at >= ? AND created_at < ? GROUP BY bucket ORDER BY bucket',
        $bucketExpr
    ));
    $stmt->execute([$start, $end]);
    $points = [];
    foreach ($stmt->fetchAll() as $row) {
        $points[] = ['label' => (string) $row['bucket'], 'value' => (int) $row['total']];
    }
    return $points;
}

/** @param array<int,array{label:string,value:int}> $points */
function admin_finance_chart(string $title, array $points, bool $valuesAreHalalas = true): void
{
    $max = 1;
    foreach ($points as $point) {
        $max = max($max, $point['value']);
    }
    echo '<article class="chart-card"><h3>' . admin_h($title) . '</h3><div class="chart">';
    foreach ($points as $point) {
        $height = max(2, (int) round(($point['value'] / $max) * 100));
        $tip = $valuesAreHalalas ? admin_halalas_to_sar((int) $point['value']) . ' SAR' : (string) $point['value'];
        echo '<button type="button" class="bar"><i style="height:' . $height . '%"></i><em><b>' . admin_h($point['label']) . '</b><span>' . admin_h($tip) . '</span></em></button>';
    }
    echo '</div></article>';
}

function admin_finance_query_string(array $extra = []): string
{
    $params = array_merge([
        'range' => (string) ($_GET['range'] ?? '30'),
        'from' => (string) ($_GET['from'] ?? ''),
        'to' => (string) ($_GET['to'] ?? ''),
        'tab' => (string) ($_GET['tab'] ?? ''),
        'q' => (string) ($_GET['q'] ?? ''),
        'status' => (string) ($_GET['status'] ?? ''),
    ], $extra);
    $parts = [];
    foreach ($params as $name => $value) {
        if ($value === '') {
            continue;
        }
        $parts[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }
    return $parts === [] ? '' : '?' . implode('&', $parts);
}

function admin_csv_formula_risk(string $value): bool
{
    if ($value === '') {
        return false;
    }
    $probe = preg_replace('/^[\p{Z}\p{C}]+/u', '', $value);
    if (!is_string($probe) || $probe === '') {
        return false;
    }
    if (preg_match('/^-?\d+(?:\.\d+)?$/', $probe) === 1) {
        return false;
    }
    return preg_match('/^[=+\-@\x09\x0D\x0A]/u', $probe) === 1;
}

function admin_csv_cell(string $value): string
{
    if (admin_csv_formula_risk($value)) {
        $value = "'" . $value;
    }
    if (str_contains($value, '"') || str_contains($value, ',') || str_contains($value, "\n") || str_contains($value, "\r")) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

function admin_csv_row(array $cells): string
{
    return implode(',', array_map(static fn (string $c): string => admin_csv_cell($c), $cells)) . "\r\n";
}

function admin_finance_send_csv(string $filename, callable $writer): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Cache-Control: no-store');
    echo "\xEF\xBB\xBF";
    $writer();
    exit;
}

/**
 * @return array{code:string,count:int}
 */
function admin_bulk_apply(object $pdo, array $adminActor, string $screen, string $action, array $ids, string $reason): array
{
    $ids = array_values(array_unique(array_filter(array_map(static fn ($v) => (int) $v, $ids), static fn ($v) => $v > 0)));
    if ($ids === []) {
        return ['code' => 'missing', 'count' => 0];
    }
    if (in_array($action, ['disable', 'enable', 'delete', 'activate', 'deactivate'], true) && mb_strlen(trim($reason)) < 3) {
        return ['code' => 'reason', 'count' => 0];
    }

    $applied = 0;
    switch ($screen) {
        case 'users':
            if (!admin_actor_has($adminActor, 'customers.manage')) {
                return ['code' => 'invalid', 'count' => 0];
            }
            if ($action === 'delete') {
                foreach ($ids as $userId) {
                    $result = admin_delete_customer_user($pdo, $adminActor, $userId, $reason);
                    if (($result['ok'] ?? false) === true) {
                        $applied++;
                    }
                }
                break;
            }
            if (!in_array($action, ['disable', 'enable'], true)) {
                return ['code' => 'invalid', 'count' => 0];
            }
            foreach ($ids as $userId) {
                $result = admin_set_account_state($pdo, $adminActor, $userId, $action === 'disable', $reason);
                if (($result['ok'] ?? false) === true) {
                    $applied++;
                }
            }
            break;

        case 'staff':
            if (!admin_actor_is_owner($adminActor) || !admin_actor_has($adminActor, 'staff.manage')) {
                return ['code' => 'invalid', 'count' => 0];
            }
            if (!in_array($action, ['activate', 'deactivate'], true)) {
                return ['code' => 'invalid', 'count' => 0];
            }
            $status = $action === 'deactivate' ? 'inactive' : 'active';
            foreach ($ids as $staffId) {
                if ((int) ($adminActor['staff_id'] ?? 0) === $staffId) {
                    continue;
                }
                $pdo->prepare("UPDATE admin_staff SET status = ? WHERE id = ? AND role_code <> 'owner'")->execute([$status, $staffId]);
                admin_audit($pdo, $adminActor, $action === 'deactivate' ? 'staff_disable' : 'staff_enable', 'staff', $staffId, $reason, 'bulk');
                $applied++;
            }
            break;

        case 'campaigns':
            if (!admin_actor_has($adminActor, 'campaigns.manage')) {
                return ['code' => 'invalid', 'count' => 0];
            }
            if ($action !== 'delete') {
                return ['code' => 'invalid', 'count' => 0];
            }
            if (!admin_actor_is_owner($adminActor)) {
                return ['code' => 'owner_only', 'count' => 0];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT id FROM campaigns WHERE id IN ($placeholders) AND status IN ('draft','failed')");
            $stmt->execute($ids);
            $allowed = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($allowed as $campaignId) {
                $campaignId = (int) $campaignId;
                $pdo->prepare('DELETE FROM campaigns WHERE id = ?')->execute([$campaignId]);
                if (admin_table_has($pdo, 'admin_audit_log', 'id')) {
                    admin_audit($pdo, $adminActor, 'campaign_delete', 'campaign', $campaignId, $reason, 'bulk');
                }
                $applied++;
            }
            break;

        case 'finance_customers':
            if (!admin_actor_has($adminActor, 'customers.manage')) {
                return ['code' => 'invalid', 'count' => 0];
            }
            if ($action === 'delete') {
                foreach ($ids as $userId) {
                    $userId = (int) $userId;
                    if ($userId <= 0) {
                        continue;
                    }
                    $result = admin_delete_customer_user($pdo, $adminActor, $userId, $reason);
                    if (($result['ok'] ?? false) === true) {
                        $applied++;
                    }
                }
                break;
            }
            if (!in_array($action, ['disable', 'enable'], true)) {
                return ['code' => 'invalid', 'count' => 0];
            }
            foreach ($ids as $userId) {
                $userId = (int) $userId;
                if ($userId <= 0) {
                    continue;
                }
                $result = admin_set_account_state($pdo, $adminActor, $userId, $action === 'disable', $reason);
                if (($result['ok'] ?? false) === true) {
                    $applied++;
                }
            }
            break;

        case 'finance_payments':
        case 'finance_receipts':
            if (!admin_actor_is_owner($adminActor) || !admin_actor_has($adminActor, 'finance.view')) {
                return ['code' => 'owner_only', 'count' => 0];
            }
            if ($action !== 'delete') {
                return ['code' => 'invalid', 'count' => 0];
            }
            foreach ($ids as $token) {
                $token = (string) $token;
                if ($screen === 'finance_receipts' && ctype_digit($token)) {
                    $receiptId = (int) $token;
                    $refunds = $pdo->prepare('SELECT COUNT(*) FROM payment_refunds WHERE receipt_id = ?');
                    $refunds->execute([$receiptId]);
                    if ((int) $refunds->fetchColumn() > 0) {
                        continue;
                    }
                    $pdo->prepare('DELETE FROM payment_receipts WHERE id = ?')->execute([$receiptId]);
                    admin_audit($pdo, $adminActor, 'receipt_delete', 'payment_receipts', $receiptId, $reason, 'bulk');
                    $applied++;
                    continue;
                }
                if (!preg_match('/^(receipt|order):(\d+)$/', $token, $m)) {
                    continue;
                }
                $kind = $m[1];
                $rowId = (int) $m[2];
                if ($kind === 'order' && admin_table_has($pdo, 'payment_orders', 'status')) {
                    $st = $pdo->prepare("SELECT status FROM payment_orders WHERE id = ? LIMIT 1");
                    $st->execute([$rowId]);
                    $status = (string) ($st->fetchColumn() ?: '');
                    if (!in_array($status, ['pending', 'failed'], true)) {
                        continue;
                    }
                    $pdo->prepare('DELETE FROM payment_orders WHERE id = ?')->execute([$rowId]);
                    admin_audit($pdo, $adminActor, 'payment_order_delete', 'payment_orders', $rowId, $reason, 'bulk');
                    $applied++;
                } elseif ($kind === 'receipt') {
                    $refunds = $pdo->prepare('SELECT COUNT(*) FROM payment_refunds WHERE receipt_id = ?');
                    $refunds->execute([$rowId]);
                    if ((int) $refunds->fetchColumn() > 0) {
                        continue;
                    }
                    $pdo->prepare('DELETE FROM payment_receipts WHERE id = ?')->execute([$rowId]);
                    admin_audit($pdo, $adminActor, 'receipt_delete', 'payment_receipts', $rowId, $reason, 'bulk');
                    $applied++;
                }
            }
            break;

        default:
            return ['code' => 'invalid', 'count' => 0];
    }

    return ['code' => $applied > 0 ? 'applied' : 'missing', 'count' => $applied];
}

/** @return list<array{value:string,label:string}> */
function admin_bulk_actions_for_screen(string $screen): array
{
    return match ($screen) {
        'users' => [
            ['value' => 'disable', 'label' => admin_t('Disable (keep data)', 'تعطيل (يبقى السجل)')],
            ['value' => 'enable', 'label' => admin_t('Enable selected', 'تفعيل المحدد')],
            ['value' => 'delete', 'label' => admin_t('Delete permanently', 'حذف نهائي')],
        ],
        'staff' => [
            ['value' => 'deactivate', 'label' => admin_t('Disable selected', 'تعطيل المحدد')],
            ['value' => 'activate', 'label' => admin_t('Activate selected', 'تفعيل المحدد')],
        ],
        'campaigns' => [
            ['value' => 'delete', 'label' => admin_t('Delete draft/failed', 'حذف مسودة/فاشلة')],
        ],
        'finance_customers' => [
            ['value' => 'disable', 'label' => admin_t('Disable (keep data)', 'تعطيل (يبقى السجل)')],
            ['value' => 'enable', 'label' => admin_t('Enable selected', 'تفعيل المحدد')],
            ['value' => 'delete', 'label' => admin_t('Delete permanently', 'حذف نهائي')],
        ],
        'finance_payments' => [
            ['value' => 'delete', 'label' => admin_t('Delete selected', 'حذف المحدد')],
        ],
        'finance_receipts' => [
            ['value' => 'delete', 'label' => admin_t('Delete selected', 'حذف المحدد')],
        ],
        default => [],
    };
}
