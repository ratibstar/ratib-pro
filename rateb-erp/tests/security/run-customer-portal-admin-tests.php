<?php
declare(strict_types=1);

/**
 * Customer Portal user administration (Website module) — in-process tests (fake stores, no database).
 * Usage: php tests/security/run-customer-portal-admin-tests.php
 */

use Rateb\App\CustomerPortal\CustomerPortalStore;
use Rateb\App\CustomerPortal\CustomerPortalTokenService;
use Rateb\App\Website\Portal\PortalUserAdminService;
use Rateb\App\Website\Portal\PortalUserAdminStore;

$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    $map = [
        'Rateb\\App\\CustomerPortal\\' => '/app/CustomerPortal/',
        'Rateb\\App\\Website\\Portal\\' => '/app/Website/Portal/',
    ];
    foreach ($map as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = $root . $dir . substr($class, strlen($prefix)) . '.php';
            if (is_file($file)) {
                require_once $file;
            }
        }
    }
});

$logFile = tempnam(sys_get_temp_dir(), 'cpa_log_');
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

$pass = 0;
$fail = 0;
$check = static function (string $name, bool $ok) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ": $name\n";
    $ok ? $pass++ : $fail++;
};

final class FakeAdminStore implements PortalUserAdminStore
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];
    /** @var array<int, int> crm company id => owner company */
    public array $crm = [];
    /** @var array<int, int> customer id => owner company */
    public array $customers = [];
    /** Simulates a concurrent insert: pre-check misses, unique key fires. */
    public bool $skipEmailPrecheck = false;
    /** Simulates a buggy store returning another tenant's rows. */
    public bool $leakForeignRows = false;
    public int $nextId = 1;
    /** @var list<array{0: string, 1: int, 2: int}> method, company_id, account id (0 when not applicable) */
    public array $calls = [];

    public function listForCompany(int $companyId, int $limit): array
    {
        $this->calls[] = ['listForCompany', $companyId, 0];
        $out = [];
        foreach (array_reverse($this->rows, true) as $row) {
            if ($this->leakForeignRows || (int) $row['company_id'] === $companyId) {
                $out[] = $row;
            }
        }

        return array_slice($out, 0, $limit);
    }

    public function findById(int $companyId, int $id): ?array
    {
        $this->calls[] = ['findById', $companyId, $id];
        $row = $this->rows[$id] ?? null;
        if ($row === null) {
            return null;
        }

        return ($this->leakForeignRows || (int) $row['company_id'] === $companyId) ? $row : null;
    }

    public function findIdByEmail(int $companyId, string $portalType, string $email): ?int
    {
        $this->calls[] = ['findIdByEmail', $companyId, 0];
        if ($this->skipEmailPrecheck) {
            return null;
        }
        foreach ($this->rows as $row) {
            if ((int) $row['company_id'] === $companyId && $row['portal_type'] === $portalType && $row['email'] === $email) {
                return (int) $row['id'];
            }
        }

        return null;
    }

    public function insert(int $companyId, array $row): int
    {
        $this->calls[] = ['insert', $companyId, 0];
        $this->assertUnique($companyId, (string) $row['portal_type'], (string) $row['email'], 0);
        $id = $this->nextId++;
        $this->rows[$id] = [
            'id' => $id,
            'company_id' => $companyId,
            'portal_type' => $row['portal_type'],
            'email' => $row['email'],
            'password_hash' => $row['password_hash'],
            'full_name' => $row['full_name'],
            'phone' => $row['phone'] ?? null,
            'organization_name' => $row['organization_name'] ?? null,
            'crm_company_id' => $row['crm_company_id'] ?? null,
            'erp_customer_id' => $row['erp_customer_id'] ?? null,
            'status' => 'active',
            'app_access_approved_at' => null,
            'last_login_at' => null,
        ];

        return $id;
    }

    public function update(int $companyId, int $id, array $fields): int
    {
        $this->calls[] = ['update', $companyId, $id];
        $row = $this->rows[$id] ?? null;
        if ($row === null || (int) $row['company_id'] !== $companyId) {
            return 0;
        }
        foreach (array_keys($fields) as $column) {
            if (in_array($column, ['id', 'company_id'], true)) {
                throw new InvalidArgumentException('column_not_updatable');
            }
        }
        $next = array_merge($row, $fields);
        $this->assertUnique($companyId, (string) $next['portal_type'], (string) $next['email'], $id);
        $this->rows[$id] = $next;

        return 1;
    }

    public function tokenStats(int $companyId, array $userIds, string $now): array
    {
        $this->calls[] = ['tokenStats', $companyId, 0];
        return FakeTokens::$instance !== null ? FakeTokens::$instance->stats($companyId, $userIds, $now) : [];
    }

    public function crmCompanyExists(int $companyId, int $crmCompanyId): bool
    {
        $this->calls[] = ['crmCompanyExists', $companyId, 0];
        return ($this->crm[$crmCompanyId] ?? 0) === $companyId;
    }

    public function customerExists(int $companyId, int $customerId): bool
    {
        $this->calls[] = ['customerExists', $companyId, 0];
        return ($this->customers[$customerId] ?? 0) === $companyId;
    }

    private function assertUnique(int $companyId, string $type, string $email, int $selfId): void
    {
        foreach ($this->rows as $row) {
            if ((int) $row['id'] !== $selfId && (int) $row['company_id'] === $companyId
                && $row['portal_type'] === $type && $row['email'] === $email) {
                $e = new PDOException('SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry');
                $e->errorInfo = ['23000', 1062, 'Duplicate entry'];
                throw $e;
            }
        }
    }
}

final class FakeTokens implements CustomerPortalStore
{
    public static ?self $instance = null;
    /** @var array<int, array<string, mixed>> */
    public array $tokens = [];
    public int $inserts = 0;

    public function add(int $companyId, int $userId, ?string $revokedAt = null, string $expiresAt = '2099-01-01 00:00:00'): int
    {
        $id = count($this->tokens) + 1;
        $this->tokens[$id] = [
            'id' => $id,
            'company_id' => $companyId,
            'portal_user_id' => $userId,
            'token_hash' => str_repeat('a', 64),
            'expires_at' => $expiresAt,
            'last_used_at' => '2026-09-20 10:00:00',
            'revoked_at' => $revokedAt,
            'revoke_reason' => null,
        ];

        return $id;
    }

    public function active(int $companyId, int $userId): int
    {
        $n = 0;
        foreach ($this->tokens as $t) {
            if ($t['company_id'] === $companyId && $t['portal_user_id'] === $userId && $t['revoked_at'] === null) {
                $n++;
            }
        }

        return $n;
    }

    /** @return list<string> */
    public function reasons(int $companyId, int $userId): array
    {
        $out = [];
        foreach ($this->tokens as $t) {
            if ($t['company_id'] === $companyId && $t['portal_user_id'] === $userId && $t['revoke_reason'] !== null) {
                $out[] = (string) $t['revoke_reason'];
            }
        }

        return array_values(array_unique($out));
    }

    /** @param list<int> $userIds */
    public function stats(int $companyId, array $userIds, string $now): array
    {
        $out = [];
        foreach ($this->tokens as $t) {
            if ($t['company_id'] !== $companyId || !in_array($t['portal_user_id'], $userIds, true)) {
                continue;
            }
            $uid = $t['portal_user_id'];
            $out[$uid] ??= ['active' => 0, 'last_used_at' => null];
            if ($t['revoked_at'] === null && $t['expires_at'] > $now) {
                $out[$uid]['active']++;
            }
            $out[$uid]['last_used_at'] = max((string) $out[$uid]['last_used_at'], (string) $t['last_used_at']);
        }

        return $out;
    }

    public function currentDatabaseName(): string { return 'fake'; }
    public function listCompanies(int $limit): array { return []; }
    public function findCompanyBySlug(string $slug): ?array { return null; }
    public function findCompanyById(int $companyId): ?array { return null; }
    public function hasValidSubscription(int $companyId, int $now): bool { return true; }
    public function findAccountByEmail(int $companyId, string $portalType, string $email): ?array { return null; }
    public function findAccountById(int $accountId): ?array { return null; }
    public function findTokenByHash(string $tokenHash): ?array { return null; }
    public function touchToken(int $tokenId, string $usedAt): void {}
    public function revokeToken(int $tokenId, string $reason, string $revokedAt): void {}

    public function insertToken(int $companyId, int $accountId, string $tokenHash, string $expiresAt): int
    {
        $this->inserts++;

        return $this->add($companyId, $accountId, null, $expiresAt);
    }

    /** @var list<array{0: int, 1: int, 2: string}> company_id, account id, reason */
    public array $revokeCalls = [];

    public function revokeAllForAccount(int $companyId, int $accountId, string $reason, string $revokedAt): int
    {
        $this->revokeCalls[] = [$companyId, $accountId, $reason];
        $n = 0;
        foreach ($this->tokens as $id => $t) {
            if ($t['company_id'] === $companyId && $t['portal_user_id'] === $accountId && $t['revoked_at'] === null) {
                $this->tokens[$id]['revoked_at'] = $revokedAt;
                $this->tokens[$id]['revoke_reason'] = $reason;
                $n++;
            }
        }

        return $n;
    }
}

$now = 1790000000;
$clock = static fn (): int => $now;
$audit = [];
$auditSink = static function (string $action, int $companyId, int $entityId, array $payload) use (&$audit): void {
    $audit[] = ['action' => $action, 'company_id' => $companyId, 'entity_id' => $entityId, 'payload' => $payload];
};

$store = new FakeAdminStore();
$store->crm = [501 => 1, 502 => 2];
$store->customers = [701 => 1, 702 => 2];
$tokenStore = new FakeTokens();
FakeTokens::$instance = $tokenStore;
$tokens = new CustomerPortalTokenService($tokenStore);
$svc = static fn (int $companyId) => new PortalUserAdminService($companyId, $store, $tokens, $auditSink, $clock);

$plain = 'Str0ng-Pass-9x';
$valid = static fn (array $over = []): array => array_merge([
    'portal_type' => 'customer',
    'email' => 'Client@Example.COM',
    'full_name' => 'Client One',
    'phone' => '0500000000',
    'organization_name' => 'Org',
    'password' => $plain,
    'password_confirmation' => $plain,
], $over);

// --- construction / company context
$threw = false;
try {
    new PortalUserAdminService(0, $store, $tokens, $auditSink, $clock);
} catch (InvalidArgumentException $e) {
    $threw = true;
}
$check('service refuses to run without a company context', $threw);
$check('portal types are exactly customer|employer|partner', PortalUserAdminService::types() === ['customer', 'employer', 'partner']);

// --- create
$_SESSION['rateb_company_id'] = 99;
$r = $svc(1)->create($valid(['company_id' => 2, 'status' => 'suspended', 'app_access_approved_at' => '2026-01-01 00:00:00', 'password_hash' => 'x']));
$check('create succeeds', $r['ok'] === true && ($r['id'] ?? 0) > 0);
$u1 = (int) ($r['id'] ?? 0);
$row = $store->rows[$u1] ?? [];
$check('create: company_id comes from context, never POST', (int) ($row['company_id'] ?? 0) === 1);
$check('create: email is lowercased', ($row['email'] ?? '') === 'client@example.com');
$check('create: new account is active', ($row['status'] ?? '') === 'active');
$check('create: app access is not granted', array_key_exists('app_access_approved_at', $row) && $row['app_access_approved_at'] === null);
$check('create: password stored as a hash, not plaintext', ($row['password_hash'] ?? '') !== $plain && password_verify($plain, (string) ($row['password_hash'] ?? '')));
$check('create: hash ignores POSTed password_hash', ($row['password_hash'] ?? '') !== 'x');
$check('create: no token issued', $tokenStore->inserts === 0);
$last = end($audit);
$check('create: audit portal_user.created', ($last['action'] ?? '') === 'portal_user.created' && ($last['entity_id'] ?? 0) === $u1);
$check('audit company is the WebsiteContext company, not $_SESSION company', ($last['company_id'] ?? 0) === 1);

foreach ([
    'invalid email' => [['email' => 'not-an-email'], 'email', 'invalid_email'],
    'missing name' => [['full_name' => '   '], 'full_name', 'full_name_required'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password', 'password_min'],
    'password confirmation mismatch' => [['password_confirmation' => 'Different-Pass-1'], 'password', 'password_mismatch'],
    'unknown portal type' => [['portal_type' => 'admin'], 'portal_type', 'invalid_portal_type'],
    'empty portal type' => [['portal_type' => ''], 'portal_type', 'invalid_portal_type'],
] as $label => [$over, $field, $code]) {
    $res = $svc(1)->create($valid(array_merge(['email' => 'v' . md5($label) . '@example.com'], $over)));
    $check("create validation: $label", $res['ok'] === false && ($res['errors'][$field] ?? '') === $code);
}
$res = $svc(1)->create($valid(['email' => 'upper@example.com', 'portal_type' => 'EMPLOYER']));
$check('portal type is normalized to lowercase', $res['ok'] === true && $store->rows[$res['id']]['portal_type'] === 'employer');

// --- duplicates
$res = $svc(1)->create($valid());
$check('duplicate email in same company/type is rejected (pre-check)', $res['ok'] === false && ($res['errors']['email'] ?? '') === 'email_taken');
$store->skipEmailPrecheck = true;
$res = $svc(1)->create($valid());
$store->skipEmailPrecheck = false;
$check('duplicate email caught from SQLSTATE 23000', $res['ok'] === false && ($res['errors']['email'] ?? '') === 'email_taken');
$res = $svc(1)->create($valid(['portal_type' => 'partner']));
$check('same email allowed for a different portal type', $res['ok'] === true);
$uPartner = (int) $res['id'];
$res = $svc(2)->create($valid());
$check('same email allowed in a different company', $res['ok'] === true);
$u2 = (int) $res['id'];

// --- company isolation / IDOR
$check('find: cannot read another company account', $svc(1)->find($u2) === null);
$foreignOps = [
    'update' => fn () => $svc(1)->update($u2, $valid(['full_name' => 'Hacked'])),
    'setPassword' => fn () => $svc(1)->setPassword($u2, 'NewPass-12345', 'NewPass-12345'),
    'suspend' => fn () => $svc(1)->suspend($u2),
    'activate' => fn () => $svc(1)->activate($u2),
    'approveAppAccess' => fn () => $svc(1)->approveAppAccess($u2),
    'revokeAppAccess' => fn () => $svc(1)->revokeAppAccess($u2),
    'revokeSessions' => fn () => $svc(1)->revokeSessions($u2),
];
$tokenStore->add(2, $u2);
$before = $store->rows[$u2];
foreach ($foreignOps as $name => $op) {
    $res = $op();
    $check("isolation: $name on another company account returns not_found", $res['ok'] === false && ($res['errors']['account'] ?? '') === 'not_found');
}
$check('isolation: foreign account row unchanged', $store->rows[$u2] === $before);
$check('isolation: foreign account tokens untouched', $tokenStore->active(2, $u2) === 1);
$store->leakForeignRows = true;
$listed = array_map(static fn (array $r): int => (int) $r['id'], $svc(1)->list());
$foundLeak = $svc(1)->find($u2);
$store->leakForeignRows = false;
$check('isolation: list drops rows from another company even if the store leaks them', !in_array($u2, $listed, true));
$check('isolation: find drops a leaked foreign row', $foundLeak === null);
$check('isolation: invalid id is not found', $svc(1)->find(0) === null && $svc(1)->find(-5) === null);

// --- CRM / customer links
$res = $svc(1)->create($valid(['email' => 'crm-own@example.com', 'crm_company_id' => '501', 'erp_customer_id' => '701']));
$check('link: own-company CRM company and customer accepted', $res['ok'] === true
    && (int) $store->rows[$res['id']]['crm_company_id'] === 501 && (int) $store->rows[$res['id']]['erp_customer_id'] === 701);
$res = $svc(1)->create($valid(['email' => 'crm-x@example.com', 'crm_company_id' => '502']));
$check('link: CRM company of another company rejected', $res['ok'] === false && ($res['errors']['crm_company_id'] ?? '') === 'invalid_link');
$res = $svc(1)->create($valid(['email' => 'cust-x@example.com', 'erp_customer_id' => '702']));
$check('link: customer of another company rejected', $res['ok'] === false && ($res['errors']['erp_customer_id'] ?? '') === 'invalid_link');
$res = $svc(1)->create($valid(['email' => 'crm-bad@example.com', 'crm_company_id' => '5x1']));
$check('link: non-numeric CRM id rejected', $res['ok'] === false && ($res['errors']['crm_company_id'] ?? '') === 'invalid_link');
$res = $svc(1)->update($u1, $valid(['crm_company_id' => '502']));
$check('link: update cannot link a CRM company of another company', $res['ok'] === false && ($res['errors']['crm_company_id'] ?? '') === 'invalid_link');

// --- update
$tokenStore->add(1, $u1);
$tokenStore->add(1, $u1);
$audit = [];
$res = $svc(1)->update($u1, $valid([
    'email' => 'client@example.com',
    'full_name' => 'Client Renamed',
    'status' => 'suspended',
    'app_access_approved_at' => '2026-01-01 00:00:00',
    'company_id' => 2,
    'password_hash' => 'x',
]));
$check('update: profile change saved', $res['ok'] === true && $store->rows[$u1]['full_name'] === 'Client Renamed');
$check('update: status/approval/company/hash from input are ignored', $store->rows[$u1]['status'] === 'active'
    && $store->rows[$u1]['app_access_approved_at'] === null && (int) $store->rows[$u1]['company_id'] === 1
    && password_verify($plain, (string) $store->rows[$u1]['password_hash']));
$check('update: name-only change keeps app sessions', $tokenStore->active(1, $u1) === 2 && ($res['sessions_revoked'] ?? -1) === 0);
$check('update: audit portal_user.updated lists changed fields only', ($audit[0]['action'] ?? '') === 'portal_user.updated'
    && ($audit[0]['payload']['changed_fields'] ?? null) === ['full_name']);
$res = $svc(1)->update($u1, $valid(['full_name' => 'Client Renamed', 'email' => 'client@example.com']));
$check('update: no-op change writes nothing', $res['ok'] === true && ($res['changed'] ?? null) === []);

$res = $svc(1)->update($u1, $valid(['email' => 'client-new@example.com', 'full_name' => 'Client Renamed']));
$check('update: email change saved', $res['ok'] === true && $store->rows[$u1]['email'] === 'client-new@example.com');
$check('update: email change revokes app tokens', $tokenStore->active(1, $u1) === 0 && ($res['sessions_revoked'] ?? 0) === 2);
$check('update: email change revoke reason is account_disabled', $tokenStore->reasons(1, $u1) === ['account_disabled']);

$tokenStore->add(1, $u1);
$res = $svc(1)->update($u1, $valid(['email' => 'client-new@example.com', 'full_name' => 'Client Renamed', 'portal_type' => 'employer']));
$lastAudit = end($audit);
$check('update: portal type change saved and revokes tokens', $res['ok'] === true && $store->rows[$u1]['portal_type'] === 'employer'
    && $tokenStore->active(1, $u1) === 0 && ($res['sessions_revoked'] ?? 0) === 1);
$check('update: audit records portal type from/to', ($lastAudit['payload']['portal_type_from'] ?? '') === 'customer'
    && ($lastAudit['payload']['portal_type_to'] ?? '') === 'employer');
$res = $svc(1)->update($u1, $valid(['email' => 'client@example.com', 'portal_type' => 'partner', 'full_name' => 'Client Renamed']));
$check('update: duplicate email/type rejected', $res['ok'] === false && ($res['errors']['email'] ?? '') === 'email_taken');
$res = $svc(1)->update($u1, $valid(['portal_type' => 'root']));
$check('update: invalid portal type rejected', $res['ok'] === false && ($res['errors']['portal_type'] ?? '') === 'invalid_portal_type');

// --- password
$tokenStore->add(1, $u1);
$res = $svc(1)->setPassword($u1, 'short', 'short');
$check('password: minimum 8 characters enforced', $res['ok'] === false && ($res['errors']['password'] ?? '') === 'password_min');
$res = $svc(1)->setPassword($u1, 'NewPass-12345', 'NewPass-12346');
$check('password: confirmation must match', $res['ok'] === false && ($res['errors']['password'] ?? '') === 'password_mismatch');
$check('password: failed attempts keep sessions', $tokenStore->active(1, $u1) === 1);
$newPlain = 'NewPass-12345';
$res = $svc(1)->setPassword($u1, $newPlain, $newPlain);
$check('password: change succeeds and is hashed', $res['ok'] === true && password_verify($newPlain, (string) $store->rows[$u1]['password_hash'])
    && $store->rows[$u1]['password_hash'] !== $newPlain);
$check('password: change revokes all app tokens', $tokenStore->active(1, $u1) === 0);
$check('password: revoke reason includes password_change', in_array('password_change', $tokenStore->reasons(1, $u1), true));
$check('password: audit portal_user.password_reset', (end($audit)['action'] ?? '') === 'portal_user.password_reset');

// --- approve / revoke app access
$res = $svc(1)->approveAppAccess($u1);
$check('approve: sets app_access_approved_at to now', $res['ok'] === true && $store->rows[$u1]['app_access_approved_at'] === date('Y-m-d H:i:s', $now));
$check('approve: does not issue tokens', $tokenStore->inserts === 0);
$check('approve: audit portal_user.app_access_approved', (end($audit)['action'] ?? '') === 'portal_user.app_access_approved');
$count = count($audit);
$res = $svc(1)->approveAppAccess($u1);
$check('approve: idempotent (no second write or audit)', $res['ok'] === true && count($audit) === $count);
$check('state: approved', PortalUserAdminService::appAccessState($store->rows[$u1]) === PortalUserAdminService::STATE_APPROVED);

$tokenStore->add(1, $u1);
$res = $svc(1)->revokeAppAccess($u1);
$check('revoke app access: clears approval', $res['ok'] === true && $store->rows[$u1]['app_access_approved_at'] === null);
$check('revoke app access: revokes tokens', $tokenStore->active(1, $u1) === 0 && ($res['sessions_revoked'] ?? 0) === 1);
$check('revoke app access: audit portal_user.app_access_revoked', (end($audit)['action'] ?? '') === 'portal_user.app_access_revoked');
$check('state: not approved', PortalUserAdminService::appAccessState($store->rows[$u1]) === PortalUserAdminService::STATE_NOT_APPROVED);

// --- suspend / activate
$tokenStore->add(1, $u1);
$res = $svc(1)->suspend($u1);
$check('suspend: status suspended', $res['ok'] === true && $store->rows[$u1]['status'] === 'suspended');
$check('suspend: revokes tokens with account_disabled', $tokenStore->active(1, $u1) === 0 && in_array('account_disabled', $tokenStore->reasons(1, $u1), true));
$check('suspend: audit portal_user.suspended', (end($audit)['action'] ?? '') === 'portal_user.suspended');
$check('state: suspended', PortalUserAdminService::appAccessState($store->rows[$u1]) === PortalUserAdminService::STATE_SUSPENDED);
$res = $svc(1)->activate($u1);
$check('activate: status active', $res['ok'] === true && $store->rows[$u1]['status'] === 'active');
$check('activate: does not grant app access', $store->rows[$u1]['app_access_approved_at'] === null);
$check('activate: audit portal_user.activated', (end($audit)['action'] ?? '') === 'portal_user.activated');
$check('state: pending status maps to pending', PortalUserAdminService::appAccessState(['status' => 'pending', 'app_access_approved_at' => '2026-01-01']) === PortalUserAdminService::STATE_PENDING);

// --- revoke sessions only
$tokenStore->add(1, $u1);
$tokenStore->add(1, $u1);
$tokenStore->add(1, $uPartner);
$svc(1)->approveAppAccess($u1);
$res = $svc(1)->revokeSessions($u1);
$check('revoke sessions: all of this account tokens revoked', $res['ok'] === true && ($res['sessions_revoked'] ?? 0) === 2 && $tokenStore->active(1, $u1) === 0);
$check('revoke sessions: other accounts untouched', $tokenStore->active(1, $uPartner) === 1 && $tokenStore->active(2, $u2) === 1);
$check('revoke sessions: status and approval unchanged', $store->rows[$u1]['status'] === 'active' && $store->rows[$u1]['app_access_approved_at'] !== null);
$check('revoke sessions: audit portal_user.sessions_revoked', (end($audit)['action'] ?? '') === 'portal_user.sessions_revoked');

// --- list / find output
$store->rows[$uPartner]['password_hash'] = password_hash('whatever-123', PASSWORD_DEFAULT);
$list = $svc(1)->list();
$hasHash = false;
foreach ($list as $item) {
    $hasHash = $hasHash || array_key_exists('password_hash', $item);
}
$found = $svc(1)->find($uPartner);
$check('list never exposes password_hash', !$hasHash && $list !== []);
$check('find never exposes password_hash', is_array($found) && !array_key_exists('password_hash', $found));
$check('list shows active app session count', (int) ($found['active_sessions'] ?? -1) === 1 && ($found['sessions_last_used_at'] ?? null) !== null);

// --- audit hygiene
$allActions = [];
$collected = [];
$audit = [];
$u3 = (int) $svc(1)->create($valid(['email' => 'audit@example.com']))['id'];
$svc(1)->update($u3, $valid(['email' => 'audit2@example.com']));
$svc(1)->setPassword($u3, $newPlain, $newPlain);
$svc(1)->suspend($u3);
$svc(1)->activate($u3);
$svc(1)->approveAppAccess($u3);
$svc(1)->revokeAppAccess($u3);
$svc(1)->revokeSessions($u3);
$allActions = array_map(static fn (array $a): string => $a['action'], $audit);
$check('audit: all eight events recorded', $allActions === [
    'portal_user.created', 'portal_user.updated', 'portal_user.password_reset', 'portal_user.suspended',
    'portal_user.activated', 'portal_user.app_access_approved', 'portal_user.app_access_revoked', 'portal_user.sessions_revoked',
]);
$blob = json_encode($audit);
$check('audit: no plaintext password in any payload', strpos((string) $blob, $plain) === false && strpos((string) $blob, $newPlain) === false);
$check('audit: no hash or token material in any payload', strpos((string) $blob, '$2y$') === false && strpos((string) $blob, 'password_hash') === false
    && strpos((string) $blob, 'rcp_') === false && strpos((string) $blob, 'token_hash') === false);
$check('audit: every event carries the context company', array_unique(array_map(static fn (array $a): int => $a['company_id'], $audit)) === [1]);
$serverLog = [];
$throwingSvc = new PortalUserAdminService(1, $store, $tokens, static function (): void {
    throw new RuntimeException('audit table unavailable');
}, $clock, static function (string $message, array $context) use (&$serverLog): void {
    $serverLog[] = ['message' => $message, 'context' => $context];
});
$res = $throwingSvc->activate($u3);
$res2 = $throwingSvc->suspend($u3);
$res3 = $throwingSvc->setPassword($u3, $newPlain, $newPlain);
$check('audit failure does not break the admin action', $res['ok'] === true && $res2['ok'] === true && $res3['ok'] === true
    && $store->rows[$u3]['status'] === 'suspended');
$check('audit failure is written to the server error log', count($serverLog) === 2
    && $serverLog[0]['message'] === 'portal_user_audit_failed'
    && $serverLog[0]['context']['action'] === 'portal_user.suspended'
    && $serverLog[0]['context']['company_id'] === 1
    && $serverLog[0]['context']['portal_user_id'] === $u3
    && $serverLog[0]['context']['exception'] === 'RuntimeException'
    && $serverLog[0]['context']['error'] === 'audit table unavailable'
    && $serverLog[1]['context']['action'] === 'portal_user.password_reset');
$logBlob = (string) json_encode($serverLog) . (string) file_get_contents($logFile);
$check('audit failure log has no secrets', strpos($logBlob, $plain) === false && strpos($logBlob, $newPlain) === false && strpos($logBlob, '$2y$') === false);
$fallbackSvc = new PortalUserAdminService(1, $store, $tokens, static function (): void {
    throw new RuntimeException('audit down');
}, $clock, static function (): void {
    throw new RuntimeException('log dir not writable');
});
$res = $fallbackSvc->revokeSessions($u3);
$fallbackLogged = (string) file_get_contents($logFile);
$check('audit failure falls back to PHP error_log when the file logger fails', $res['ok'] === true
    && strpos($fallbackLogged, 'portal_user_audit_failed portal_user.sessions_revoked company=1 portal_user=' . $u3) !== false);
$serviceSrcForLog = (string) file_get_contents($root . '/app/Website/Portal/PortalUserAdminService.php');
$check('default server log is Services\\Logger::error (storage/logs + error_log)', strpos($serviceSrcForLog, "Logger::error(\$message, \$context);") !== false);

$auditSrc = (string) file_get_contents($root . '/app/services/AuditService.php');
require_once $root . '/app/services/AuditService.php';
$params = (new ReflectionMethod(\Rateb\App\Services\AuditService::class, 'log'))->getParameters();
$check('AuditService::log accepts an explicit company', isset($params[4]) && $params[4]->getName() === 'companyId' && $params[4]->allowsNull());
$check('AuditService::log prefers the explicit company over the session', strpos($auditSrc, "\$companyId = \$companyId ?? (\$_SESSION['rateb_company_id'] ?? null);") !== false);
$serviceSrc = (string) file_get_contents($root . '/app/Website/Portal/PortalUserAdminService.php');
$check('default audit sink passes the service company to AuditService', (bool) preg_match('/->log\(\$action, self::AUDIT_ENTITY, \$entityId, \$payload, \$companyId\)/', $serviceSrc));

// --- Super Admin working inside Company A while the session still points at Company B
$A = 11;
$B = 22;
$saStore = new FakeAdminStore();
$saStore->crm = [801 => $A, 802 => $B];
$saStore->customers = [901 => $A, 902 => $B];
$saTokenStore = new FakeTokens();
FakeTokens::$instance = $saTokenStore;
$saTokens = new CustomerPortalTokenService($saTokenStore);
$saAudit = [];
$saSink = static function (string $action, int $companyId, int $entityId, array $payload) use (&$saAudit): void {
    $saAudit[] = ['action' => $action, 'company_id' => $companyId, 'entity_id' => $entityId, 'payload' => $payload];
};
$saLog = [];
$saLogger = static function (string $message, array $context) use (&$saLog): void {
    $saLog[] = $message;
};
$_SESSION['rateb_is_super_admin'] = true;
$_SESSION['rateb_company_id'] = $B;
// The controller builds the service only from WebsiteContext::bootForOps()->companyId() — here Company A.
$saA = new PortalUserAdminService($A, $saStore, $saTokens, $saSink, $clock, $saLogger);
$saB = new PortalUserAdminService($B, $saStore, $saTokens, $saSink, $clock, $saLogger);

$aIds = [];
$bIds = [];
for ($i = 1; $i <= 6; $i++) {
    $bIds[] = (int) $saB->create($valid(['email' => "b$i@example.com"]))['id'];
    $aIds[] = (int) $saA->create($valid(['email' => "a$i@example.com", 'company_id' => $B, 'company' => 'company-b', 'crm_company_id' => '801']))['id'];
}
foreach (array_merge($aIds, $bIds) as $uid) {
    $saTokenStore->add((int) $saStore->rows[$uid]['company_id'], $uid);
    $saTokenStore->add((int) $saStore->rows[$uid]['company_id'], $uid);
}
$check('super admin: accounts created in context A belong to A even with company_id=B in POST', array_filter(
    $aIds,
    static fn (int $id): bool => (int) $saStore->rows[$id]['company_id'] !== $A
) === []);
$saAudit = [];
$saStore->calls = [];
$saTokenStore->revokeCalls = [];
$bRowsBefore = array_intersect_key($saStore->rows, array_flip($bIds));
$bTokensBefore = array_filter($saTokenStore->tokens, static fn (array $t): bool => $t['company_id'] === $B);

$listed = array_map(static fn (array $r): int => (int) $r['id'], $saA->list());
sort($listed);
$sortedA = $aIds;
sort($sortedA);
$check('super admin: list in context A shows exactly Company A accounts', $listed === $sortedA);
$check('super admin: find works for every Company A account', array_filter($aIds, static fn (int $id): bool => $saA->find($id) === null) === []);

$foreignOpsFor = static fn (PortalUserAdminService $s, int $id): array => [
    'find' => $s->find($id) === null ? ['ok' => false, 'errors' => ['account' => 'not_found']] : ['ok' => true],
    'update' => $s->update($id, $valid(['email' => 'pwned@example.com', 'full_name' => 'Pwned', 'crm_company_id' => '801'])),
    'setPassword' => $s->setPassword($id, 'Pwned-Pass-123', 'Pwned-Pass-123'),
    'suspend' => $s->suspend($id),
    'activate' => $s->activate($id),
    'approveAppAccess' => $s->approveAppAccess($id),
    'revokeAppAccess' => $s->revokeAppAccess($id),
    'revokeSessions' => $s->revokeSessions($id),
];
$probeIds = array_merge($bIds, [0, -1, -999, max(array_merge($aIds, $bIds)) + 1, 2147483647, PHP_INT_MAX]);
$denied = 0;
$attempts = 0;
foreach ([false, true] as $leak) {
    $saStore->leakForeignRows = $leak;
    foreach ($probeIds as $probe) {
        foreach ($foreignOpsFor($saA, $probe) as $op => $res) {
            $attempts++;
            if ($res['ok'] === false && ($res['errors']['account'] ?? '') === 'not_found') {
                $denied++;
            }
        }
    }
    $leakedList = array_map(static fn (array $r): int => (int) $r['id'], $saA->list());
    $check('super admin: list never includes Company B ids' . ($leak ? ' (leaking store)' : ''), array_intersect($leakedList, $bIds) === []);
}
$saStore->leakForeignRows = false;
$check("super admin: every foreign/invalid id attempt denied ($attempts attempts, normal + leaking store)", $attempts === count($probeIds) * 8 * 2 && $denied === $attempts);
$check('super admin: Company B rows unchanged after all attempts', array_intersect_key($saStore->rows, array_flip($bIds)) === $bRowsBefore);
$check('super admin: Company B tokens unchanged after all attempts', array_filter($saTokenStore->tokens, static fn (array $t): bool => $t['company_id'] === $B) === $bTokensBefore);
$check('super admin: no write was attempted against a Company B id', array_filter(
    $saStore->calls,
    static fn (array $c): bool => in_array($c[0], ['update', 'insert'], true) && in_array($c[2], $bIds, true)
) === [] && array_filter($saTokenStore->revokeCalls, static fn (array $c): bool => in_array($c[1], $bIds, true)) === []);
$check('super admin: no audit event for denied attempts', $saAudit === []);

$a = $aIds[0];
$saTokenStore->add($A, $a);
$ops = [
    'update' => $saA->update($a, $valid(['email' => 'a1-renamed@example.com', 'full_name' => 'A One', 'crm_company_id' => '801', 'company_id' => $B])),
    'setPassword' => $saA->setPassword($a, $newPlain, $newPlain),
    'approveAppAccess' => $saA->approveAppAccess($a),
    'suspend' => $saA->suspend($a),
    'activate' => $saA->activate($a),
    'revokeSessions' => $saA->revokeSessions($a),
    'revokeAppAccess' => $saA->revokeAppAccess($a),
];
$check('super admin: update/password/approval/status/sessions all succeed on the Company A account', array_filter($ops, static fn (array $r): bool => $r['ok'] !== true) === []);
$check('super admin: account stays in Company A after update with company_id=B in POST', (int) $saStore->rows[$a]['company_id'] === $A);
$check('super admin: update cannot link a Company B CRM company', $saA->update($a, $valid(['email' => 'a1-renamed@example.com', 'crm_company_id' => '802']))['errors']['crm_company_id'] === 'invalid_link');
$check('super admin: update cannot link a Company B customer', $saA->update($a, $valid(['email' => 'a1-renamed@example.com', 'erp_customer_id' => '902']))['errors']['erp_customer_id'] === 'invalid_link');
$check('super admin: every store call used Company A', $saStore->calls !== [] && array_filter($saStore->calls, static fn (array $c): bool => $c[1] !== $A) === []);
$check('super admin: every token revocation used Company A and the A account', $saTokenStore->revokeCalls !== []
    && array_filter($saTokenStore->revokeCalls, static fn (array $c): bool => $c[0] !== $A || $c[1] !== $a) === []);
$saActions = array_map(static fn (array $e): string => $e['action'], $saAudit);
$check('super admin: audit recorded each operation', $saActions === [
    'portal_user.updated', 'portal_user.password_reset', 'portal_user.app_access_approved', 'portal_user.suspended',
    'portal_user.activated', 'portal_user.sessions_revoked', 'portal_user.app_access_revoked',
]);
$check('super admin: every audit event records Company A, not the stale session Company B', array_filter(
    $saAudit,
    static fn (array $e): bool => $e['company_id'] !== $A || $e['entity_id'] !== $a
) === [] && (int) $_SESSION['rateb_company_id'] === $B);
$check('super admin: no audit failures logged', $saLog === []);

$saTokenStore->add($A, $a);
$saA->approveAppAccess($a);
$approvedAt = $saStore->rows[$a]['app_access_approved_at'];
$saA->suspend($a);
$check('suspend keeps app_access_approved_at and revokes sessions', $saStore->rows[$a]['app_access_approved_at'] === $approvedAt
    && $saTokenStore->active($A, $a) === 0 && PortalUserAdminService::appAccessState($saStore->rows[$a]) === PortalUserAdminService::STATE_SUSPENDED);
$saA->activate($a);
$check('activate restores app access when the approval still exists', PortalUserAdminService::appAccessState($saStore->rows[$a]) === PortalUserAdminService::STATE_APPROVED
    && $saStore->rows[$a]['app_access_approved_at'] === $approvedAt);
$check('super admin: the B-context service cannot see A accounts either', $saB->find($a) === null
    && ($saB->suspend($a)['errors']['account'] ?? '') === 'not_found' && $saStore->rows[$a]['status'] === 'active');

$ctlSrc = (string) file_get_contents($root . '/app/controllers/Company/WebsitePortalUsersController.php');
$check('controller: the only service construction uses the WebsiteContext company', substr_count($ctlSrc, 'new PortalUserAdminService(') === 1
    && strpos($ctlSrc, 'new PortalUserAdminService($ctx->companyId())') !== false
    && strpos($ctlSrc, '$ctx = WebsiteContext::bootForOps();') !== false);
$check('controller: never reads the session company or a POSTed company', !preg_match('/\$_SESSION|rateb_company_id|\$_POST\[[\'"]company|\$_GET\[[\'"]company/', $ctlSrc));
$check('controller: every handler gets its service from the same context factory', substr_count($ctlSrc, '$this->service()') === 10);
unset($_SESSION['rateb_is_super_admin']);

// --- no hard delete
$deleteMethods = static function (string $class): array {
    return array_values(array_filter(
        array_map(static fn (ReflectionMethod $m): string => $m->getName(), (new ReflectionClass($class))->getMethods()),
        static fn (string $n): bool => (bool) preg_match('/delete|destroy|remove|purge/i', $n)
    ));
};
$storeSrc = (string) file_get_contents($root . '/app/Website/Portal/PdoPortalUserAdminStore.php');
$controllerSrc = (string) file_get_contents($root . '/app/controllers/Company/WebsitePortalUsersController.php');
$routesSrc = (string) file_get_contents($root . '/routes/modules/website-portal-users.php');
$check('no hard delete: service has no delete method', $deleteMethods(PortalUserAdminService::class) === []);
$check('no hard delete: store interface has no delete method', $deleteMethods(PortalUserAdminStore::class) === []);
$check('no hard delete: SQL has no DELETE statement', stripos($storeSrc, 'DELETE ') === false);
$check('no hard delete: controller and routes expose no delete', !preg_match('/function\s+(delete|destroy|remove)/i', $controllerSrc)
    && !preg_match('/\$router->\w+\([^;]*(delete|destroy|remove)/i', $routesSrc));

// --- SQL scoping (static)
preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/s", $storeSrc, $m);
$byId = array_filter($m[1], static fn (string $sql): bool => strpos($sql, ':id') !== false);
$allScoped = true;
foreach ($byId as $sql) {
    $allScoped = $allScoped && strpos($sql, 'WHERE id = :id AND company_id = :cid') !== false;
}
$check('SQL: every query bound to :id uses WHERE id = :id AND company_id = :cid', count($byId) === 4 && $allScoped);
$check('SQL: update statement is scoped by id and company', strpos($storeSrc, "' WHERE id = :id AND company_id = :cid'") !== false);
$check('SQL: list query is scoped by company', strpos($storeSrc, 'WHERE company_id = :cid ORDER BY id DESC') !== false);
$check('SQL: CRM/customer link lookups are scoped by company', substr_count($storeSrc, 'WHERE id = :id AND company_id = :cid LIMIT 1') >= 3);
$check('SQL: token stats scoped by company', strpos($storeSrc, 'WHERE company_id = :cid AND portal_user_id IN') !== false);
$check('SQL: insert hard-codes active status and NULL approval', strpos($storeSrc, ":crm, :cust, 'active', NULL)") !== false);
$check('SQL: company_id and id are not updatable', (bool) preg_match("/UPDATABLE = \[[^\]]*\]/s", $storeSrc, $um)
    && strpos($um[0], "'company_id'") === false && strpos($um[0], "'id'") === false);
$check('no companyWhere() fallback anywhere in the feature', strpos($storeSrc . $serviceSrc . $controllerSrc, 'companyWhere') === false);
$check('no AgencyId or staff API auth in the feature', !preg_match('/agency_?id|ApiAuthMiddleware|rateb_api_tokens/i', $storeSrc . $serviceSrc . $controllerSrc . $routesSrc));

// --- routes / permissions (static via a recording router)
$router = new class () {
    /** @var list<array{0: string, 1: string, 2: string, 3: array<string, string>}> */
    public array $log = [];
    public function get(string $path, array $handler, array $mw): void { $this->log[] = ['GET', $path, $handler[1], $mw]; }
    public function post(string $path, array $handler, array $mw): void { $this->log[] = ['POST', $path, $handler[1], $mw]; }
};
if (!function_exists('rateb_erp_mw')) {
    function rateb_erp_mw(string $module = '', string $permission = '', string $resource = ''): array
    {
        return ['module' => $module, 'permission' => $permission, 'resource' => $resource];
    }
}
$app = static fn (string $sub): string => '/admin/ops/' . $sub;
require $root . '/routes/modules/website-portal-users.php';
$recorded = $router->log;
$expected = [
    ['GET', '/admin/ops/website/portal-users', 'index', 'website.portal.view'],
    ['GET', '/admin/ops/website/portal-users/create', 'create', 'website.portal.manage'],
    ['POST', '/admin/ops/website/portal-users', 'store', 'website.portal.manage'],
    ['GET', '/admin/ops/website/portal-users/{id}/edit', 'edit', 'website.portal.manage'],
    ['POST', '/admin/ops/website/portal-users/{id}', 'update', 'website.portal.manage'],
    ['POST', '/admin/ops/website/portal-users/{id}/password', 'password', 'website.portal.manage'],
    ['POST', '/admin/ops/website/portal-users/{id}/status', 'status', 'website.portal.manage'],
    ['POST', '/admin/ops/website/portal-users/{id}/app-access/approve', 'approveAppAccess', 'website.portal.manage'],
    ['POST', '/admin/ops/website/portal-users/{id}/app-access/revoke', 'revokeAppAccess', 'website.portal.manage'],
    ['POST', '/admin/ops/website/portal-users/{id}/sessions/revoke', 'revokeSessions', 'website.portal.manage'],
];
$actual = array_map(static fn (array $r): array => [$r[0], $r[1], $r[2], $r[3]['permission']], $recorded);
$check('routes: exactly the ten approved routes with view/manage permissions', $actual === $expected);
$check('routes: every route uses the website module and website-portal-users resource', array_filter(
    $recorded,
    static fn (array $r): bool => $r[3]['module'] !== 'website' || $r[3]['resource'] !== 'website-portal-users'
) === []);
$check('routes: only the list is reachable with view permission', count(array_filter($actual, static fn (array $r): bool => $r[3] === 'website.portal.view')) === 1);
$entityPerms = require $root . '/config/entity-permissions.php';
$check('entity resource website-portal-users maps view/manage', ($entityPerms['website-portal-users'] ?? null) === [
    'module' => 'website', 'view' => 'website.portal.view', 'manage' => 'website.portal.manage',
]);
$opsSrc = (string) file_get_contents($root . '/routes/modules/ops.php');
$check('routes file is loaded from the Website block in ops.php', strpos($opsSrc, "require RATEB_ROOT . '/routes/modules/website-portal-users.php';") !== false);
$navSrc = (string) file_get_contents($root . '/views/partials/sidebar-ops-nav.php');
$check('sidebar: Portal Users entry uses website.portal.view', strpos($navSrc, "['website/portal-users', \$websitePortalUsersLabel, 'fa-user-shield', 'website', 'website.portal.view']") !== false);

// --- CSRF (static): every POST handler runs guardPost() before the service
$postHandlers = array_map(static fn (array $r): string => $r[2], array_filter($recorded, static fn (array $r): bool => $r[0] === 'POST'));
$csrfOk = true;
foreach ($postHandlers as $method) {
    if (!preg_match('/public function ' . preg_quote($method, '/') . '\([^)]*\): void\s*\{(.*?)\n    \}/s', $controllerSrc, $mm)) {
        $csrfOk = false;
        continue;
    }
    $body = $mm[1];
    $guard = strpos($body, '$this->guardPost(');
    $svcCall = strpos($body, '$this->service()');
    $csrfOk = $csrfOk && $guard !== false && $svcCall !== false && $guard < $svcCall;
}
$check('CSRF: every POST handler calls guardPost() before the service', count($postHandlers) === 7 && $csrfOk);
$check('CSRF: guardPost validates the token and redirects on failure', (bool) preg_match(
    '/private function guardPost\(string \$backRoute\): void\s*\{\s*if \(!\$this->validateCsrf\(\)\) \{.*?\$this->redirect\(/s',
    $controllerSrc
));
$views = (string) file_get_contents($root . '/views/company/website/portal-users/index.php')
    . (string) file_get_contents($root . '/views/company/website/portal-users/form.php');
$check('CSRF: every POST form in the views carries _csrf', substr_count($views, '<form method="post"') === substr_count($views, 'name="_csrf"')
    && substr_count($views, '<form method="post"') >= 8);

// --- password handling in views and controller
preg_match_all('/<input[^\n]*type="password"[^\n]*/', $views, $pw);
$pwOk = $pw[0] !== [];
foreach ($pw[0] as $input) {
    $pwOk = $pwOk && strpos($input, 'autocomplete="new-password"') !== false && stripos($input, 'value=') === false;
}
$check('views: password inputs use autocomplete=new-password and never a value', $pwOk && count($pw[0]) === 4);
$check('views: no password hash is ever rendered', stripos($views, 'password_hash') === false);
$check('controller: old-input flash is limited to profile fields (no password)', (bool) preg_match("/FORM_FIELDS = \[[^\]]*\]/", $controllerSrc, $ff)
    && stripos($ff[0], 'password') === false);
$check('controller: no raw $_POST logging', !preg_match('/(error_log|log\()[^;]*\$_POST/', $controllerSrc . $serviceSrc));

echo "\n" . ($fail === 0 ? 'ALL PASS' : 'FAILURES') . ": {$pass} passed, {$fail} failed\n";
@unlink($logFile);
exit($fail === 0 ? 0 : 1);
