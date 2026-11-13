<?php
declare(strict_types=1);

/**
 * Idempotent admin staff schema. Run locally only — not on production without approval.
 * php migrations/admin_staff_schema.php
 */
function ensure_admin_staff_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_roles (
        code VARCHAR(32) NOT NULL,
        name_en VARCHAR(80) NOT NULL,
        name_ar VARCHAR(80) NOT NULL,
        is_owner TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_permissions (
        code VARCHAR(64) NOT NULL,
        label_en VARCHAR(120) NOT NULL,
        label_ar VARCHAR(120) NOT NULL,
        PRIMARY KEY (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_role_permissions (
        role_code VARCHAR(32) NOT NULL,
        permission_code VARCHAR(64) NOT NULL,
        PRIMARY KEY (role_code, permission_code),
        CONSTRAINT fk_admin_rp_role FOREIGN KEY (role_code) REFERENCES admin_roles (code) ON DELETE CASCADE,
        CONSTRAINT fk_admin_rp_perm FOREIGN KEY (permission_code) REFERENCES admin_permissions (code) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_staff (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(190) NOT NULL,
        name VARCHAR(120) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role_code VARCHAR(32) NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'active',
        last_login_at DATETIME NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        KEY role_status (role_code, status),
        CONSTRAINT fk_admin_staff_role FOREIGN KEY (role_code) REFERENCES admin_roles (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if (admin_table_has($pdo, 'admin_audit_log', 'id') && !admin_table_has($pdo, 'admin_audit_log', 'admin_staff_id')) {
        $pdo->exec('ALTER TABLE admin_audit_log ADD COLUMN admin_staff_id BIGINT UNSIGNED NULL AFTER admin_user_id');
    }

    admin_staff_seed_roles_permissions($pdo);
}

function admin_staff_seed_roles_permissions(PDO $pdo): void
{
    $roles = [
        ['owner', 'Owner', 'المالك', 1],
        ['finance', 'Finance', 'المالية', 0],
        ['support', 'Support', 'الدعم', 0],
        ['operations', 'Operations', 'التشغيل', 0],
        ['viewer', 'Viewer', 'مشاهد', 0],
    ];
    $stmt = $pdo->prepare('INSERT IGNORE INTO admin_roles (code, name_en, name_ar, is_owner) VALUES (?, ?, ?, ?)');
    foreach ($roles as $row) {
        $stmt->execute($row);
    }

    $perms = [
        ['dashboard.view', 'View dashboard', 'عرض اللوحة'],
        ['finance.view', 'View financial data', 'عرض البيانات المالية'],
        ['finance.export', 'Export financial reports', 'تصدير التقارير المالية'],
        ['customers.view', 'View customers', 'عرض العملاء'],
        ['customers.manage', 'Manage customers', 'إدارة العملاء'],
        ['campaigns.view', 'View campaigns', 'عرض الحملات'],
        ['campaigns.manage', 'Manage campaigns', 'إدارة الحملات'],
        ['operations.view', 'View operations', 'عرض التشغيل'],
        ['operations.manage', 'Manage operations', 'إدارة التشغيل'],
        ['staff.view', 'View staff', 'عرض الموظفين'],
        ['staff.manage', 'Manage staff', 'إدارة الموظفين'],
        ['roles.manage', 'Manage roles', 'إدارة الأدوار'],
        ['settings.manage', 'System settings', 'إعدادات النظام'],
    ];
    $pstmt = $pdo->prepare('INSERT IGNORE INTO admin_permissions (code, label_en, label_ar) VALUES (?, ?, ?)');
    foreach ($perms as $row) {
        $pstmt->execute($row);
    }

    $matrix = admin_staff_permission_matrix_static();
    $rp = $pdo->prepare('INSERT IGNORE INTO admin_role_permissions (role_code, permission_code) VALUES (?, ?)');
    foreach ($matrix as $role => $codes) {
        if ($role === 'owner') {
            continue;
        }
        foreach ($codes as $code) {
            $rp->execute([$role, $code]);
        }
    }
}

/** @return array<string,list<string>> */
function admin_staff_permission_matrix_static(): array
{
    return [
        'owner' => admin_staff_all_permission_codes(),
        'finance' => [
            'dashboard.view',
            'finance.view',
            'finance.export',
            'customers.view',
        ],
        'support' => [
            'dashboard.view',
            'customers.view',
            'customers.manage',
            'campaigns.view',
            'campaigns.manage',
        ],
        'operations' => [
            'dashboard.view',
            'operations.view',
            'operations.manage',
        ],
        'viewer' => [
            'dashboard.view',
            'finance.view',
            'customers.view',
            'campaigns.view',
            'operations.view',
        ],
    ];
}

/** @return list<string> */
function admin_staff_all_permission_codes(): array
{
    return [
        'dashboard.view',
        'finance.view',
        'finance.export',
        'customers.view',
        'customers.manage',
        'campaigns.view',
        'campaigns.manage',
        'operations.view',
        'operations.manage',
        'staff.view',
        'staff.manage',
        'roles.manage',
        'settings.manage',
    ];
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename((string) ($argv[0] ?? ''))) {
    require dirname(__DIR__) . '/config/bootstrap.php';
    require dirname(__DIR__) . '/admin/lib.php';
    ensure_admin_staff_schema($pdo);
    echo "admin staff schema OK\n";
}
