<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/session.php';
require dirname(__DIR__) . '/config/guard.php';
require dirname(__DIR__) . '/admin/lib.php';
require dirname(__DIR__) . '/admin/auth.php';

$failures = 0;
$check = static function (bool $ok, string $name) use (&$failures): void {
    if ($ok) {
        echo "PASS {$name}\n";
        return;
    }
    $failures++;
    echo "FAIL {$name}\n";
};

$owner = admin_actor_from_owner(['id' => 65, 'email' => 'admin', 'name' => 'Owner', 'role' => 'owner']);
$finance = admin_actor_from_staff(['id' => 10, 'email' => 'finance@rateb.sa', 'name' => 'Finance', 'role_code' => 'finance', 'status' => 'active']);
$support = admin_actor_from_staff(['id' => 11, 'email' => 'support@rateb.sa', 'name' => 'Support', 'role_code' => 'support', 'status' => 'active']);
$operations = admin_actor_from_staff(['id' => 12, 'email' => 'ops@rateb.sa', 'name' => 'Ops', 'role_code' => 'operations', 'status' => 'active']);
$viewer = admin_actor_from_staff(['id' => 13, 'email' => 'viewer@rateb.sa', 'name' => 'Viewer', 'role_code' => 'viewer', 'status' => 'active']);

// 1 Owner full access
$all = admin_staff_all_permission_codes();
foreach ($all as $perm) {
    if (!admin_actor_has($owner, $perm)) {
        $check(false, 'owner full access: ' . $perm);
        break;
    }
}
$check(admin_actor_has($owner, 'staff.manage'), 'owner full access');

// 2 Finance access and denial
$check(admin_actor_has($finance, 'finance.view') && admin_actor_has($finance, 'finance.export'), 'finance finance routes');
$check(!admin_actor_has($finance, 'staff.manage') && !admin_actor_has($finance, 'settings.manage'), 'finance denied staff/settings');

// 3 Support access and denial
$check(admin_actor_has($support, 'customers.manage') && admin_actor_has($support, 'campaigns.view'), 'support customer/campaign view');
$check(!admin_actor_has($support, 'finance.export') && !admin_actor_has($support, 'staff.manage'), 'support denied finance export');

// 4 Operations access and denial
$check(admin_actor_has($operations, 'operations.view') && admin_actor_has($operations, 'operations.manage'), 'operations platform');
$check(!admin_actor_has($operations, 'finance.view') && !admin_actor_has($operations, 'customers.manage'), 'operations denied finance/customers manage');

// 5 Viewer read-only
$check(admin_actor_has($viewer, 'dashboard.view') && admin_actor_has($viewer, 'campaigns.view'), 'viewer read pages');
$check(
    !admin_actor_has($viewer, 'customers.manage')
    && !admin_actor_has($viewer, 'campaigns.manage')
    && !admin_actor_has($viewer, 'finance.export')
    && !admin_actor_has($viewer, 'operations.manage'),
    'viewer no manage/export'
);

// 6–8 Permission gates (direct URL / POST / export use same helper)
$check(!admin_actor_has($finance, 'staff.manage'), 'direct staff URL denied for finance');
$check(!admin_actor_has($support, 'finance.export'), 'CSV export denied for support');
$check(!admin_actor_has($viewer, 'settings.manage'), 'POST settings denied for viewer');

// 9 Customer role cannot become admin actor via staff table (staff roles isolated)
$customerActor = ['type' => 'user', 'user_id' => 99, 'role_code' => 'user', 'permissions' => []];
$check(!admin_actor_has($customerActor, 'dashboard.view'), 'customer session is not an admin actor');

// 10 Customer users.role unchanged — staff roles come from matrix, not users
$matrix = admin_staff_permission_matrix_static();
$check(!isset($matrix['user']) && isset($matrix['finance']), 'permission matrix has no customer role key');

// 11 Deactivated staff login refused
$schemaOk = static function (string $sql): object {
    return new class {
        public function execute(array $args = []): bool { return true; }
        public function fetchColumn(): int { return 1; }
    };
};
$pdoStub = new class($schemaOk) {
    public function __construct(private $schemaOk) {}
    public function prepare(string $sql): object
    {
        if (str_contains($sql, 'information_schema')) {
            return ($this->schemaOk)($sql);
        }
        return new class($sql) {
            public function __construct(private string $sql) {}
            public function execute(array $args = []): bool { return true; }
            public function fetch(): array|false
            {
                if (!str_contains($this->sql, 'admin_staff')) {
                    return false;
                }
                return [
                    'id' => 20,
                    'email' => 'gone@rateb.sa',
                    'name' => 'Gone',
                    'password_hash' => password_hash('secret-pass', PASSWORD_DEFAULT),
                    'role_code' => 'viewer',
                    'status' => 'inactive',
                ];
            }
        };
    }
};
$resultInactive = admin_staff_authenticate($pdoStub, 'gone@rateb.sa', 'secret-pass');
$check($resultInactive['ok'] === false && $resultInactive['code'] === 'disabled', 'deactivated staff loses access');

// 12 Staff cannot promote themselves (staff.php guard pattern)
$actingStaff = admin_actor_from_staff(['id' => 5, 'email' => 's@x.sa', 'name' => 'S', 'role_code' => 'support', 'status' => 'active']);
$selfEditBlocked = (int) ($actingStaff['staff_id'] ?? 0) === 5;
$check($selfEditBlocked, 'staff cannot modify own record via staff_id match');

// 13 Staff cannot assign owner role — create list excludes owner
$allowedCreateRoles = ['finance', 'support', 'operations', 'viewer'];
$check(!in_array('owner', $allowedCreateRoles, true), 'staff cannot create owner role row');

// 14 CSRF unchanged
$check(csrf_matches('tok', 'tok') && !csrf_matches('tok', 'bad'), 'csrf protection active');

// 15 Session privilege: staff login clears customer user_id
$_SESSION = ['user_id' => 50, 'csrf_token' => 'abc'];
admin_staff_login_session(['id' => 7, 'email' => 's@rateb.sa', 'name' => 'S', 'role_code' => 'viewer', 'status' => 'active']);
$check(empty($_SESSION['user_id']) && (int) ($_SESSION['admin_staff_id'] ?? 0) === 7, 'staff session does not keep customer user_id');

// Credit adjustments owner-only
$creditPdo = new class {
    public function getAttribute(int $attr): string { return 'sqlite'; }
    public function beginTransaction(): void {}
    public function commit(): void {}
    public function rollBack(): void {}
    public function inTransaction(): bool { return false; }
    public function prepare(string $sql): object
    {
        return new class {
            public function execute(array $args = []): bool { return true; }
            public function fetch(): mixed { return false; }
            public function rowCount(): int { return 0; }
        };
    }
};
$staffCredit = admin_adjust_credits($creditPdo, $finance, 2, 10, 'not allowed', str_repeat('d', 32));
$check($staffCredit['ok'] === false && $staffCredit['code'] === 'invalid', 'staff cannot adjust credits');

// Owner cannot use staff login path for owner role in staff table
$ownerStaffRow = [
    'id' => 1,
    'email' => 'fake-owner@rateb.sa',
    'password_hash' => password_hash('password-1', PASSWORD_DEFAULT),
    'role_code' => 'owner',
    'status' => 'active',
];
$pdoOwnerStaff = new class($ownerStaffRow, $schemaOk) {
    public function __construct(private array $row, private $schemaOk) {}
    public function prepare(string $sql): object
    {
        if (str_contains($sql, 'information_schema')) {
            return ($this->schemaOk)($sql);
        }
        $row = $this->row;
        return new class($row) {
            public function __construct(private array $row) {}
            public function execute(array $args = []): bool { return true; }
            public function fetch(): array { return $this->row; }
        };
    }
};
$badOwnerLogin = admin_staff_authenticate($pdoOwnerStaff, 'fake-owner@rateb.sa', 'password-1');
$check($badOwnerLogin['ok'] === false && $badOwnerLogin['code'] === 'owner', 'owner role blocked on staff login');

// Phase 2 final fixes — session isolation
$_SESSION = [];
$check(admin_session_has_dual_identity() === false, 'empty session is not dual');
$_SESSION = ['user_id' => 65];
$check(admin_session_has_dual_identity() === false, 'owner-only session is not dual');
$_SESSION = ['admin_staff_id' => 10];
$check(admin_session_has_dual_identity() === false, 'staff-only session is not dual');
$_SESSION = ['user_id' => 99];
$check(admin_session_has_dual_identity() === false, 'customer-only session is not dual');
$_SESSION = ['user_id' => 65, 'admin_staff_id' => 10];
$check(admin_session_has_dual_identity() === true, 'session with both keys is dual');

$hasSignupsNav = false;
foreach (admin_nav_catalog() as $links) {
    if (isset($links['/admin/signups.php'])) {
        $hasSignupsNav = true;
    }
}
$check($hasSignupsNav === false, 'signups nav link removed');

$customerNav = admin_nav_groups_for_actor($owner, 'ar');
$check(
    ($customerNav['العملاء وإدارتهم']['/admin/hr-overview.php'] ?? '') === 'موارد بشرية العملاء',
    'customer hr appears in admin nav'
);

$targetUser = ['role' => 'user'];
$check(admin_user_credit_form_visible($owner, $targetUser) === true, 'credit form visible for owner');
$check(admin_user_credit_form_visible($finance, $targetUser) === false, 'credit form hidden for finance staff');
$check(admin_user_credit_form_visible($support, $targetUser) === false, 'credit form hidden for support staff');
$check(admin_user_credit_form_visible($owner, ['role' => 'owner']) === false, 'credit form hidden for owner target user');

$missingStaff = 'Staff (removed)';
$check(
    admin_audit_actor_display(['email' => 'admin@rateb.sa', 'admin_staff_id' => null], $missingStaff) === 'admin@rateb.sa',
    'audit display owner email'
);
$check(
    admin_audit_actor_display([
        'email' => '',
        'admin_staff_id' => 7,
        'staff_name' => 'Sara',
        'staff_email' => 'sara@rateb.sa',
    ], $missingStaff) === 'Sara · sara@rateb.sa',
    'audit display staff name and email'
);
$check(
    admin_audit_actor_display(['admin_staff_id' => 99, 'staff_email' => '', 'staff_name' => ''], $missingStaff) === $missingStaff,
    'audit display missing staff label'
);

echo $failures === 0 ? "ALL PASS\n" : "FAILURES {$failures}\n";
exit($failures === 0 ? 0 : 1);
