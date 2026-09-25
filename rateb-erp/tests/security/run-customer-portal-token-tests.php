<?php
declare(strict_types=1);

/**
 * Customer Portal token layer — in-process tests (fake store, no database).
 * Usage: php tests/security/run-customer-portal-token-tests.php
 */

use Rateb\App\CustomerPortal\CustomerPortalAuthService;
use Rateb\App\CustomerPortal\CustomerPortalPlatformResolver;
use Rateb\App\CustomerPortal\CustomerPortalStore;
use Rateb\App\CustomerPortal\CustomerPortalTokenService;

$root = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefix = 'Rateb\\App\\CustomerPortal\\';
    if (strpos($class, $prefix) === 0) {
        $file = $root . '/app/CustomerPortal/' . substr($class, strlen($prefix)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    }
});

$logFile = tempnam(sys_get_temp_dir(), 'cpt_log_');
ini_set('log_errors', '1');
ini_set('error_log', $logFile);

$pass = 0;
$fail = 0;
$check = static function (string $name, bool $ok) use (&$pass, &$fail): void {
    echo ($ok ? 'PASS' : 'FAIL') . ": $name\n";
    $ok ? $pass++ : $fail++;
};

final class FakeCustomerPortalStore implements CustomerPortalStore
{
    public string $database = 'db_dedicated';
    /** @var array<int, array<string, mixed>> */
    public array $companies = [];
    /** @var array<int, bool> */
    public array $subscriptions = [];
    /** @var array<int, array<string, mixed>> */
    public array $accounts = [];
    /** @var array<int, array<string, mixed>> */
    public array $tokens = [];
    /** @var list<string> */
    public array $calls = [];

    public function currentDatabaseName(): string
    {
        $this->calls[] = 'currentDatabaseName';

        return $this->database;
    }

    public function listCompanies(int $limit): array
    {
        $this->calls[] = 'listCompanies';
        ksort($this->companies);

        return array_slice(array_values($this->companies), 0, $limit);
    }

    public function findCompanyBySlug(string $slug): ?array
    {
        $this->calls[] = 'findCompanyBySlug';
        foreach ($this->companies as $c) {
            if ($c['slug'] === $slug) {
                return $c;
            }
        }

        return null;
    }

    public function findCompanyById(int $companyId): ?array
    {
        return $this->companies[$companyId] ?? null;
    }

    public function hasValidSubscription(int $companyId, int $now): bool
    {
        return $this->subscriptions[$companyId] ?? false;
    }

    public function findAccountByEmail(int $companyId, string $portalType, string $email): ?array
    {
        foreach ($this->accounts as $a) {
            if ((int) $a['company_id'] === $companyId && $a['portal_type'] === $portalType && $a['email'] === $email) {
                return $a;
            }
        }

        return null;
    }

    public function findAccountById(int $accountId): ?array
    {
        return $this->accounts[$accountId] ?? null;
    }

    public function insertToken(int $companyId, int $accountId, string $tokenHash, string $expiresAt): int
    {
        $id = count($this->tokens) + 1;
        $this->tokens[$id] = [
            'id' => $id,
            'company_id' => $companyId,
            'portal_user_id' => $accountId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
            'last_used_at' => null,
            'revoked_at' => null,
            'revoke_reason' => null,
        ];

        return $id;
    }

    public function findTokenByHash(string $tokenHash): ?array
    {
        foreach ($this->tokens as $t) {
            if ($t['token_hash'] === $tokenHash) {
                return $t;
            }
        }

        return null;
    }

    public function touchToken(int $tokenId, string $usedAt): void
    {
        $this->tokens[$tokenId]['last_used_at'] = $usedAt;
    }

    public function revokeToken(int $tokenId, string $reason, string $revokedAt): void
    {
        if (isset($this->tokens[$tokenId]) && $this->tokens[$tokenId]['revoked_at'] === null) {
            $this->tokens[$tokenId]['revoked_at'] = $revokedAt;
            $this->tokens[$tokenId]['revoke_reason'] = $reason;
        }
    }

    public function revokeAllForAccount(int $companyId, int $accountId, string $reason, string $revokedAt): int
    {
        $n = 0;
        foreach ($this->tokens as $id => $t) {
            if ((int) $t['company_id'] === $companyId && (int) $t['portal_user_id'] === $accountId && $t['revoked_at'] === null) {
                $this->tokens[$id]['revoked_at'] = $revokedAt;
                $this->tokens[$id]['revoke_reason'] = $reason;
                $n++;
            }
        }

        return $n;
    }
}

final class FakeLimiter
{
    public bool $available = true;
    /** @var array<string, int> */
    public array $counts = [];

    /** @return array{available: \Closure, limited: \Closure, hit: \Closure, clear: \Closure} */
    public function closures(): array
    {
        return [
            'available' => fn (): bool => $this->available,
            'limited' => fn (string $k, int $max): bool => ($this->counts[$k] ?? 0) >= $max,
            'hit' => function (string $k, int $max, int $decay): void {
                $this->counts[$k] = ($this->counts[$k] ?? 0) + 1;
            },
            'clear' => function (string $k): void {
                unset($this->counts[$k]);
            },
        ];
    }
}

const HOST_DEDICATED = 'alarfaj.rateb.sa';
const HOST_CENTRAL = 'rateb.sa';
const PASSWORD = 'Correct-Horse-9';

$hashFor = static fn (string $p): string => password_hash($p, PASSWORD_DEFAULT, ['cost' => 4]);

/**
 * @return array{store: FakeCustomerPortalStore, limiter: FakeLimiter, clock: \stdClass, policy: \stdClass, svc: CustomerPortalAuthService}
 */
$env = static function (string $mode = 'dedicated') use ($hashFor): array {
    $store = new FakeCustomerPortalStore();
    $policyState = new stdClass();
    $policyState->bindings = [HOST_DEDICATED => ['db' => 'db_dedicated', 'suspended' => false]];
    $policy = [
        'is_central' => static fn (string $h): bool => $h === HOST_CENTRAL,
        'is_allowed' => static fn (string $h): bool => in_array($h, [HOST_CENTRAL, HOST_DEDICATED], true),
        'dedicated_binding' => static fn (string $h): ?array => $policyState->bindings[$h] ?? null,
    ];
    if ($mode === 'dedicated') {
        $store->database = 'db_dedicated';
        $store->companies[1] = ['id' => 1, 'name' => 'Al Arfaj', 'slug' => 'l-rfaj', 'status' => 'active'];
        $store->accounts[10] = [
            'id' => 10, 'company_id' => 1, 'portal_type' => 'customer', 'email' => 'client@example.test',
            'password_hash' => $hashFor(PASSWORD), 'full_name' => 'Client One', 'status' => 'active',
            'app_access_approved_at' => '2026-09-01 10:00:00',
        ];
    } else {
        $store->database = 'db_central';
        $store->companies[31] = ['id' => 31, 'name' => 'Company A', 'slug' => 'company-a', 'status' => 'active'];
        $store->companies[32] = ['id' => 32, 'name' => 'Company B', 'slug' => 'company-b', 'status' => 'active'];
        $store->subscriptions = [31 => true, 32 => true];
        $store->accounts[20] = [
            'id' => 20, 'company_id' => 31, 'portal_type' => 'employer', 'email' => 'a@example.test',
            'password_hash' => $hashFor(PASSWORD), 'full_name' => 'Employer A', 'status' => 'active',
            'app_access_approved_at' => '2026-09-01 10:00:00',
        ];
        $store->accounts[21] = [
            'id' => 21, 'company_id' => 32, 'portal_type' => 'employer', 'email' => 'b@example.test',
            'password_hash' => $hashFor(PASSWORD), 'full_name' => 'Employer B', 'status' => 'active',
            'app_access_approved_at' => '2026-09-01 10:00:00',
        ];
    }
    $limiter = new FakeLimiter();
    $clock = new stdClass();
    $clock->now = strtotime('2026-09-25 12:00:00');
    $resolver = new CustomerPortalPlatformResolver($store, $policy);
    $svc = new CustomerPortalAuthService($store, $resolver, $limiter->closures(), static fn (): int => $clock->now);

    return ['store' => $store, 'limiter' => $limiter, 'clock' => $clock, 'policy' => $policyState, 'svc' => $svc];
};

$dedicatedLogin = static fn (CustomerPortalAuthService $svc, string $password = PASSWORD, string $email = 'client@example.test', string $ip = '10.0.0.1'): array
    => $svc->login(['email' => $email, 'password' => $password, 'portal_type' => 'customer'], HOST_DEDICATED, $ip);

// ---------------------------------------------------------------- 1. valid login
$e = $env();
$r = $dedicatedLogin($e['svc']);
$token = (string) ($r['body']['token'] ?? '');
$check('valid login returns 200', $r['status'] === 200 && ($r['body']['success'] ?? false) === true);
$check('token has rcp_ prefix and 64 hex chars', CustomerPortalTokenService::isWellFormed($token));
$check('token_type is Bearer', ($r['body']['token_type'] ?? '') === 'Bearer');
$stored = array_values($e['store']->tokens)[0] ?? [];
$check('stored row holds sha256 hash, not plaintext', ($stored['token_hash'] ?? '') === hash('sha256', $token) && !in_array($token, $stored, true));
$check('token bound to host company and account', (int) ($stored['company_id'] ?? 0) === 1 && (int) ($stored['portal_user_id'] ?? 0) === 10);
$check('token expires in 30 days', strtotime((string) $r['body']['expires_at']) === $e['clock']->now + 30 * 86400);
$check('response has no password hash', !str_contains(json_encode($r['body']), 'password'));
$a = $e['svc']->authenticate($token, HOST_DEDICATED, null);
$check('session check accepts valid token', ($a['ok'] ?? false) === true && (int) $a['context']['account']['id'] === 10 && (int) $a['context']['company']['id'] === 1);
$check('session check touches last_used_at', ($e['store']->tokens[1]['last_used_at'] ?? null) !== null);
$check('host port is ignored during resolution', ($e['svc']->authenticate($token, 'ALARFAJ.rateb.sa:443', null)['ok'] ?? false) === true);

// ---------------------------------------------------------------- 2. invalid login (no account enumeration)
$e = $env();
$wrongPw = $dedicatedLogin($e['svc'], 'wrong-password');
$unknown = $dedicatedLogin($e['svc'], PASSWORD, 'nobody@example.test');
$check('wrong password returns 401 invalid_credentials', $wrongPw['status'] === 401 && $wrongPw['body']['code'] === 'invalid_credentials');
$check('unknown email response identical to wrong password', $wrongPw === $unknown);
$e['store']->accounts[10]['app_access_approved_at'] = null;
$unapproved = $dedicatedLogin($e['svc']);
$check('unapproved account response identical to wrong password', $unapproved === $wrongPw);
$e['store']->accounts[10]['app_access_approved_at'] = '2026-09-01 10:00:00';
$e['store']->accounts[10]['status'] = 'pending';
$check('pending account response identical to wrong password', $dedicatedLogin($e['svc']) === $wrongPw);
$e['store']->accounts[10]['status'] = 'active';
$wrongType = $e['svc']->login(['email' => 'client@example.test', 'password' => PASSWORD, 'portal_type' => 'employer'], HOST_DEDICATED, '10.0.0.9');
$check('same email under another portal type is rejected identically', $wrongType === $wrongPw);
$bad = $e['svc']->login(['email' => 'client@example.test', 'password' => PASSWORD, 'portal_type' => 'admin'], HOST_DEDICATED, '10.0.0.9');
$check('unknown portal_type rejected with 422', $bad['status'] === 422);
$check('no token issued for failed logins', count($e['store']->tokens) === 0);

// ---------------------------------------------------------------- 3. wrong host / company
$e = $env();
$r = $e['svc']->login(['email' => 'client@example.test', 'password' => PASSWORD, 'portal_type' => 'customer'], 'test.rateb.sa', '10.0.0.2');
$check('non-allowed host rejected with 403 portal_unavailable', $r['status'] === 403 && $r['body']['code'] === 'portal_unavailable');
$e['store']->database = 'admin_rateb-erp';
$r = $dedicatedLogin($e['svc']);
$check('dedicated host bound to another database rejected (403)', $r['status'] === 403);
$e = $env('central');
$r = $e['svc']->login(['email' => 'b@example.test', 'password' => PASSWORD, 'portal_type' => 'employer', 'company' => 'company-a'], HOST_CENTRAL, '10.0.0.3');
$check('account of company B cannot log in under company A slug (401)', $r['status'] === 401 && $r['body']['code'] === 'invalid_credentials');
$r = $e['svc']->login(['email' => 'a@example.test', 'password' => PASSWORD, 'portal_type' => 'employer', 'company' => 'company-a'], HOST_CENTRAL, '10.0.0.3');
$tokenA = (string) ($r['body']['token'] ?? '');
$check('central login with matching slug succeeds', $r['status'] === 200 && (int) $r['body']['company']['id'] === 31);
$x = $e['svc']->authenticate($tokenA, HOST_CENTRAL, 'company-b');
$check('company A token rejected when presented with company B slug (401)', ($x['ok'] ?? true) === false && ($x['status'] ?? 0) === 401);
$x = $e['svc']->authenticate($tokenA, HOST_CENTRAL, null);
$check('central token rejected without slug (403)', ($x['ok'] ?? true) === false && ($x['status'] ?? 0) === 403);
$check('company A token accepted with company A slug', ($e['svc']->authenticate($tokenA, HOST_CENTRAL, 'company-a')['ok'] ?? false) === true);
$d = $env();
$d['store']->tokens = $e['store']->tokens;
$x = $d['svc']->authenticate($tokenA, HOST_DEDICATED, null);
$check('central token rejected on dedicated host (company mismatch, 401)', ($x['ok'] ?? true) === false && ($x['status'] ?? 0) === 401);
$e['store']->accounts[20]['company_id'] = 32;
$check('token rejected after account moves to another company', ($e['svc']->authenticate($tokenA, HOST_CENTRAL, 'company-a')['ok'] ?? true) === false);

// ---------------------------------------------------------------- 4. inactive company
$e = $env();
$token = (string) ($dedicatedLogin($e['svc'])['body']['token'] ?? '');
$e['store']->companies[1]['status'] = 'suspended';
$r = $dedicatedLogin($e['svc']);
$check('login rejected when company suspended (403)', $r['status'] === 403 && $r['body']['code'] === 'portal_unavailable');
$x = $e['svc']->authenticate($token, HOST_DEDICATED, null);
$check('existing token rejected when company suspended (403)', ($x['ok'] ?? true) === false && ($x['status'] ?? 0) === 403);
$e['store']->companies[1]['status'] = 'pending';
$check('pending company also rejected', $dedicatedLogin($e['svc'])['status'] === 403);
$e = $env();
$token = (string) ($dedicatedLogin($e['svc'])['body']['token'] ?? '');
$e['policy']->bindings[HOST_DEDICATED]['suspended'] = true;
$check('login rejected when platform commercially suspended', $dedicatedLogin($e['svc'])['status'] === 403);
$check('existing token rejected when platform commercially suspended', ($e['svc']->authenticate($token, HOST_DEDICATED, null)['status'] ?? 0) === 403);

// ---------------------------------------------------------------- 5. expired subscription (central)
$e = $env('central');
$r = $e['svc']->login(['email' => 'a@example.test', 'password' => PASSWORD, 'portal_type' => 'employer', 'company' => 'company-a'], HOST_CENTRAL, '10.0.0.4');
$tokenA = (string) ($r['body']['token'] ?? '');
$e['store']->subscriptions[31] = false;
$r = $e['svc']->login(['email' => 'a@example.test', 'password' => PASSWORD, 'portal_type' => 'employer', 'company' => 'company-a'], HOST_CENTRAL, '10.0.0.4');
$check('login rejected when subscription expired (403)', $r['status'] === 403 && $r['body']['code'] === 'portal_unavailable');
$check('existing token rejected when subscription expired (403)', ($e['svc']->authenticate($tokenA, HOST_CENTRAL, 'company-a')['status'] ?? 0) === 403);

// ---------------------------------------------------------------- 6. revoked / expired token
$e = $env();
$r = $dedicatedLogin($e['svc']);
$token = (string) $r['body']['token'];
$ctx = $e['svc']->authenticate($token, HOST_DEDICATED, null);
$e['svc']->logout((int) $ctx['context']['token_id']);
$x = $e['svc']->authenticate($token, HOST_DEDICATED, null);
$check('logout revokes current token (401 afterwards)', ($x['ok'] ?? true) === false && ($x['status'] ?? 0) === 401);
$check('revoke reason recorded as logout', ($e['store']->tokens[1]['revoke_reason'] ?? '') === 'logout');
$token2 = (string) ($dedicatedLogin($e['svc'])['body']['token'] ?? '');
$e['clock']->now += 30 * 86400 + 1;
$check('expired token rejected', ($e['svc']->authenticate($token2, HOST_DEDICATED, null)['ok'] ?? true) === false);
$check('malformed token rejected (401)', ($e['svc']->authenticate('not-a-token', HOST_DEDICATED, null)['status'] ?? 0) === 401);
$check('staff-style 64-hex token without rcp_ prefix rejected', ($e['svc']->authenticate(bin2hex(random_bytes(32)), HOST_DEDICATED, null)['status'] ?? 0) === 401);
$e = $env();
$token = (string) ($dedicatedLogin($e['svc'])['body']['token'] ?? '');
$e['store']->accounts[10]['app_access_approved_at'] = null;
$check('token rejected once app approval is withdrawn', ($e['svc']->authenticate($token, HOST_DEDICATED, null)['ok'] ?? true) === false);

// ---------------------------------------------------------------- 7. password-change revocation
$e = $env();
$t1 = (string) ($dedicatedLogin($e['svc'])['body']['token'] ?? '');
$t2 = (string) ($dedicatedLogin($e['svc'], PASSWORD, 'client@example.test', '10.0.0.7')['body']['token'] ?? '');
$e['store']->insertToken(1, 99, hash('sha256', 'other-account'), '2026-12-01 00:00:00');
$revoked = (new CustomerPortalTokenService($e['store']))->revokeAllForAccount(1, 10, CustomerPortalTokenService::REVOKE_PASSWORD_CHANGE, $e['clock']->now);
$check('password change revokes all tokens of the account', $revoked === 2);
$check('both sessions rejected after password change',
    ($e['svc']->authenticate($t1, HOST_DEDICATED, null)['ok'] ?? true) === false
    && ($e['svc']->authenticate($t2, HOST_DEDICATED, null)['ok'] ?? true) === false);
$check('other accounts tokens untouched', $e['store']->tokens[3]['revoked_at'] === null);
$check('revoke reason recorded as password_change', $e['store']->tokens[1]['revoke_reason'] === 'password_change');
$portalSrc = (string) file_get_contents($root . '/app/Website/Portal/PortalAuthService.php');
$revokePos = strpos($portalSrc, '$this->revokeAppTokens($userId');
$updatePos = strpos($portalSrc, "'UPDATE rateb_website_portal_users SET ' . implode");
$check('PortalAuthService revokes app tokens before saving new password hash', $revokePos !== false && $updatePos !== false && $revokePos < $updatePos);
$check('PortalAuthService regenerates session id on portal login', (bool) preg_match('/function establishSession[^{]*\{\s*SessionManager::regenerate\(\);/', $portalSrc));

// ---------------------------------------------------------------- 8. rate limiting
$e = $env();
for ($i = 0; $i < CustomerPortalAuthService::ACCOUNT_MAX_FAILURES; $i++) {
    $dedicatedLogin($e['svc'], 'wrong-' . $i);
}
$r = $dedicatedLogin($e['svc']);
$check('correct password blocked after 5 account failures (429)', $r['status'] === 429 && $r['body']['code'] === 'too_many_attempts');
$check('no token issued while limited', count($e['store']->tokens) === 0);
$e = $env();
for ($i = 0; $i < CustomerPortalAuthService::IP_MAX_FAILURES; $i++) {
    $dedicatedLogin($e['svc'], PASSWORD, 'probe' . $i . '@example.test', '10.9.9.9');
}
$check('IP blocked after 20 failures across emails (429)', $dedicatedLogin($e['svc'], PASSWORD, 'client@example.test', '10.9.9.9')['status'] === 429);
$e = $env();
$dedicatedLogin($e['svc'], 'wrong-1');
$dedicatedLogin($e['svc']);
$accountKeys = array_filter(array_keys($e['limiter']->counts), static fn (string $k): bool => str_contains($k, 'customer_portal_login_account'));
$check('successful login clears account failure counter', $accountKeys === []);
$e = $env();
$e['limiter']->available = false;
$r = $dedicatedLogin($e['svc']);
$check('limiter storage unavailable fails closed (503)', $r['status'] === 503 && count($e['store']->tokens) === 0);

// ---------------------------------------------------------------- 9. account with no company
$e = $env();
$token = (string) ($dedicatedLogin($e['svc'])['body']['token'] ?? '');
$e['store']->accounts[10]['company_id'] = 0;
$check('token rejected when account has no company', ($e['svc']->authenticate($token, HOST_DEDICATED, null)['status'] ?? 0) === 401);
$check('login rejected when account has no company', $dedicatedLogin($e['svc'])['status'] === 401);

// ---------------------------------------------------------------- 10. no fallback company selection
$e = $env();
$e['store']->companies = [];
$check('dedicated DB with 0 companies rejected (403)', $dedicatedLogin($e['svc'])['status'] === 403);
$e = $env();
$e['store']->companies[2] = ['id' => 2, 'name' => 'Second', 'slug' => 'second', 'status' => 'active'];
$check('dedicated DB with 2 companies rejected, no first-row fallback (403)', $dedicatedLogin($e['svc'])['status'] === 403 && count($e['store']->tokens) === 0);
$e = $env();
unset($e['policy']->bindings[HOST_DEDICATED]);
$check('dedicated host without ERP binding rejected (403)', $dedicatedLogin($e['svc'])['status'] === 403);
$e = $env('central');
$r = $e['svc']->login(['email' => 'a@example.test', 'password' => PASSWORD, 'portal_type' => 'employer'], HOST_CENTRAL, '10.0.0.5');
$check('central host without slug rejected (403)', $r['status'] === 403 && $r['body']['code'] === 'portal_unavailable');
$check('central host never lists companies (no fallback path)', !in_array('listCompanies', $e['store']->calls, true));
$r = $e['svc']->login(['email' => 'a@example.test', 'password' => PASSWORD, 'portal_type' => 'employer', 'company' => 'does-not-exist'], HOST_CENTRAL, '10.0.0.5');
$check('central host with unknown slug rejected (403)', $r['status'] === 403);
$r = $e['svc']->login(['email' => 'a@example.test', 'password' => PASSWORD, 'portal_type' => 'employer', 'company' => "company-a' OR 1=1"], HOST_CENTRAL, '10.0.0.5');
$check('central host with malformed slug rejected (403)', $r['status'] === 403);
$e['store']->companies[31]['status'] = 'suspended';
$r = $e['svc']->login(['email' => 'a@example.test', 'password' => PASSWORD, 'portal_type' => 'employer', 'company' => 'company-a'], HOST_CENTRAL, '10.0.0.6');
$check('central host with inactive company slug rejected (403)', $r['status'] === 403);

// ---------------------------------------------------------------- logging hygiene
$log = (string) file_get_contents($logFile);
@unlink($logFile);
$check('logs contain reason codes', str_contains($log, 'customer_portal_auth reason='));
$check('logs never contain passwords', !str_contains($log, PASSWORD) && !str_contains($log, 'wrong-password'));
$check('logs never contain tokens or emails', !preg_match('/rcp_[a-f0-9]{8}/', $log) && !str_contains($log, '@example.test'));

// ---------------------------------------------------------------- isolation from staff auth (static)
$newFiles = [
    'app/CustomerPortal/CustomerPortalStore.php',
    'app/CustomerPortal/PdoCustomerPortalStore.php',
    'app/CustomerPortal/CustomerPortalPlatformResolver.php',
    'app/CustomerPortal/CustomerPortalTokenService.php',
    'app/CustomerPortal/CustomerPortalAuthService.php',
    'app/CustomerPortal/CustomerPortalContext.php',
    'app/Core/Middleware/CustomerPortalAuthMiddleware.php',
    'app/controllers/Api/CustomerPortalAuthController.php',
    'routes/modules/customer-portal-api.php',
];
$staffRefs = false;
foreach ($newFiles as $rel) {
    $src = (string) @file_get_contents($root . '/' . $rel);
    $check('exists ' . $rel, $src !== '');
    $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $src) ?? $src;
    if (preg_match('/ApiAuthMiddleware|ApiTokenService|rateb_api_tokens|rateb_users|setApiUserId|agency_id|country/i', $code)) {
        $staffRefs = true;
        echo "  staff/agency reference in $rel\n";
    }
}
$check('new code never references staff auth, agency id or country', !$staffRefs);
$routes = (string) file_get_contents($root . '/routes/modules/customer-portal-api.php');
$check('no registration route', !preg_match('/regist|signup/i', $routes));
$check('logout and session routes use CustomerPortalAuthMiddleware', substr_count($routes, 'CustomerPortalAuthMiddleware::class]') === 2);
$api = (string) file_get_contents($root . '/routes/modules/api.php');
$check('staff token route unchanged', str_contains($api, "\$router->post('/api/v1/auth/token', [ApiController::class, 'createToken']);"));
$check('api.php loads customer portal routes', str_contains($api, "/routes/modules/customer-portal-api.php'"));
$mig = (string) file_get_contents($root . '/migrations/270_customer_portal_tokens.sql');
foreach (['CREATE TABLE IF NOT EXISTS rateb_customer_portal_tokens', 'token_hash CHAR(64) NOT NULL', 'UNIQUE KEY uq_cpt_token_hash', 'expires_at DATETIME NOT NULL', 'revoked_at DATETIME NULL', 'last_used_at DATETIME NULL', 'ADD COLUMN app_access_approved_at DATETIME NULL'] as $needle) {
    $check('migration has ' . $needle, str_contains($mig, $needle));
}
$check('migration stores no plaintext token column', !preg_match('/\btoken\s+(VARCHAR|TEXT|CHAR)/i', $mig));

echo "\n" . ($fail === 0 ? 'ALL PASS' : 'FAILURES') . ": $pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
