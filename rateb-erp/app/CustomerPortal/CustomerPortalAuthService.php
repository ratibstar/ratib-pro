<?php
declare(strict_types=1);

namespace Rateb\App\CustomerPortal;

use Rateb\App\Core\IpRateLimiter;

/**
 * Customer Portal app authentication (login / bearer validation / logout).
 * Accounts: rateb_website_portal_users only. Never staff users or staff API tokens.
 */
final class CustomerPortalAuthService
{
    public const PORTAL_TYPES = ['customer', 'employer', 'partner'];

    public const IP_MAX_FAILURES = 20;
    public const ACCOUNT_MAX_FAILURES = 5;
    public const FAILURE_WINDOW_SECONDS = 900;

    /** Equalizes timing when the account does not exist. */
    private const DUMMY_PASSWORD_HASH = '$2y$12$6sGvr5V7mBa63.lZGObC1OyprFZPWbe9SiaTmz8.8Uy5hNkk350eC';

    private CustomerPortalStore $store;
    private CustomerPortalPlatformResolver $platform;
    private CustomerPortalTokenService $tokens;

    /** @var array{available: \Closure, limited: \Closure, hit: \Closure, clear: \Closure} */
    private array $limiter;

    /** @var \Closure(): int */
    private \Closure $clock;

    /**
     * @param array{available: \Closure, limited: \Closure, hit: \Closure, clear: \Closure}|null $limiter
     * @param (\Closure(): int)|null $clock
     */
    public function __construct(
        ?CustomerPortalStore $store = null,
        ?CustomerPortalPlatformResolver $platform = null,
        ?array $limiter = null,
        ?\Closure $clock = null
    ) {
        $this->store = $store ?? new PdoCustomerPortalStore();
        $this->platform = $platform ?? new CustomerPortalPlatformResolver($this->store);
        $this->tokens = new CustomerPortalTokenService($this->store);
        $this->limiter = $limiter ?? self::defaultLimiter();
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * @param array<string, mixed> $input email, password, portal_type, company (slug; central host only)
     * @return array{status: int, body: array<string, mixed>}
     */
    public function login(array $input, string $host, string $ip): array
    {
        $now = ($this->clock)();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $portalType = strtolower(trim((string) ($input['portal_type'] ?? '')));
        $slug = trim((string) ($input['company'] ?? ''));
        $host = CustomerPortalPlatformResolver::normalizeHost($host);

        if (!($this->limiter['available'])()) {
            self::log('rate_limiter_unavailable', $host);

            return self::error(503, 'service_unavailable', 'Service unavailable');
        }

        $ipKey = 'customer_portal_login_ip|' . $ip;
        $accountKey = 'customer_portal_login_account|' . $host . '|' . strtolower($slug) . '|' . $portalType . '|' . $email;
        if (($this->limiter['limited'])($ipKey, self::IP_MAX_FAILURES)
            || ($this->limiter['limited'])($accountKey, self::ACCOUNT_MAX_FAILURES)) {
            return self::error(429, 'too_many_attempts', 'Too many attempts');
        }

        if ($email === '' || $password === '' || !in_array($portalType, self::PORTAL_TYPES, true)) {
            ($this->limiter['hit'])($ipKey, self::IP_MAX_FAILURES, self::FAILURE_WINDOW_SECONDS);

            return self::error(422, 'invalid_request', 'email, password and portal_type are required');
        }

        $resolved = $this->platform->resolve($host, $slug, $now);
        if (empty($resolved['ok'])) {
            ($this->limiter['hit'])($ipKey, self::IP_MAX_FAILURES, self::FAILURE_WINDOW_SECONDS);
            self::log((string) ($resolved['reason'] ?? 'platform_rejected'), $host);

            return self::portalUnavailable();
        }
        $company = $resolved['company'];
        $companyId = (int) $company['id'];

        $account = $this->store->findAccountByEmail($companyId, $portalType, $email);
        $passwordOk = password_verify($password, (string) ($account['password_hash'] ?? self::DUMMY_PASSWORD_HASH));
        $reason = null;
        if ($account === null) {
            $reason = 'account_not_found';
        } elseif (!$passwordOk) {
            $reason = 'password_mismatch';
        } else {
            $reason = self::accountRejection($account, $companyId);
        }
        if ($reason !== null) {
            ($this->limiter['hit'])($ipKey, self::IP_MAX_FAILURES, self::FAILURE_WINDOW_SECONDS);
            ($this->limiter['hit'])($accountKey, self::ACCOUNT_MAX_FAILURES, self::FAILURE_WINDOW_SECONDS);
            self::log($reason, $host);

            return self::error(401, 'invalid_credentials', 'Invalid credentials');
        }

        ($this->limiter['clear'])($accountKey);
        $issued = $this->tokens->issue($companyId, (int) $account['id'], $now);

        return [
            'status' => 200,
            'body' => [
                'success' => true,
                'token' => $issued['token'],
                'token_type' => 'Bearer',
                'expires_at' => $issued['expires_at'],
                'account' => self::publicAccount($account),
                'company' => self::publicCompany($company),
            ],
        ];
    }

    /**
     * Validates a bearer token for the current host/company.
     *
     * @return array{ok: bool, status?: int, body?: array<string, mixed>, context?: array{account: array<string, mixed>, company: array<string, mixed>, token_id: int, token_expires_at: string, mode: string}}
     */
    public function authenticate(string $bearer, string $host, ?string $companySlug): array
    {
        $now = ($this->clock)();
        $host = CustomerPortalPlatformResolver::normalizeHost($host);
        if (!CustomerPortalTokenService::isWellFormed($bearer)) {
            return ['ok' => false] + self::unauthorized();
        }

        $resolved = $this->platform->resolve($host, $companySlug, $now);
        if (empty($resolved['ok'])) {
            self::log((string) ($resolved['reason'] ?? 'platform_rejected'), $host);

            return ['ok' => false] + self::portalUnavailable();
        }
        $company = $resolved['company'];
        $companyId = (int) $company['id'];

        $token = $this->tokens->findActive($bearer, $now);
        if ($token === null) {
            return ['ok' => false] + self::unauthorized();
        }
        if ((int) ($token['company_id'] ?? 0) !== $companyId) {
            self::log('token_company_mismatch', $host);

            return ['ok' => false] + self::unauthorized();
        }

        $account = $this->store->findAccountById((int) ($token['portal_user_id'] ?? 0));
        $reason = $account === null ? 'account_not_found' : self::accountRejection($account, $companyId);
        if ($reason !== null) {
            self::log($reason, $host);

            return ['ok' => false] + self::unauthorized();
        }

        $this->tokens->touchIfStale($token, $now);

        return [
            'ok' => true,
            'context' => [
                'account' => self::publicAccount($account),
                'company' => self::publicCompany($company),
                'token_id' => (int) $token['id'],
                'token_expires_at' => (string) $token['expires_at'],
                'mode' => (string) $resolved['mode'],
            ],
        ];
    }

    public function logout(int $tokenId): void
    {
        if ($tokenId > 0) {
            $this->tokens->revoke($tokenId, CustomerPortalTokenService::REVOKE_LOGOUT, ($this->clock)());
        }
    }

    /** @param array<string, mixed> $account */
    private static function accountRejection(array $account, int $companyId): ?string
    {
        $accountCompany = (int) ($account['company_id'] ?? 0);
        if ($accountCompany < 1) {
            return 'account_without_company';
        }
        if ($accountCompany !== $companyId) {
            return 'account_company_mismatch';
        }
        if ((string) ($account['status'] ?? '') !== 'active') {
            return 'account_inactive';
        }
        if (trim((string) ($account['app_access_approved_at'] ?? '')) === '') {
            return 'account_not_approved';
        }
        if (!in_array((string) ($account['portal_type'] ?? ''), self::PORTAL_TYPES, true)) {
            return 'account_type_not_allowed';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $account
     * @return array{id: int, portal_type: string, full_name: string, email: string}
     */
    private static function publicAccount(array $account): array
    {
        return [
            'id' => (int) $account['id'],
            'portal_type' => (string) ($account['portal_type'] ?? ''),
            'full_name' => (string) ($account['full_name'] ?? ''),
            'email' => (string) ($account['email'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed> $company
     * @return array{id: int, name: string, slug: string}
     */
    private static function publicCompany(array $company): array
    {
        return [
            'id' => (int) $company['id'],
            'name' => (string) ($company['name'] ?? ''),
            'slug' => (string) ($company['slug'] ?? ''),
        ];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private static function error(int $status, string $code, string $message): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'code' => $code, 'message' => $message]];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private static function portalUnavailable(): array
    {
        return self::error(403, 'portal_unavailable', 'Portal unavailable');
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private static function unauthorized(): array
    {
        return self::error(401, 'unauthorized', 'Unauthorized');
    }

    private static function log(string $reason, string $host): void
    {
        error_log('customer_portal_auth reason=' . $reason . ' host=' . $host);
    }

    /** @return array{available: \Closure, limited: \Closure, hit: \Closure, clear: \Closure} */
    private static function defaultLimiter(): array
    {
        return [
            'available' => static function (): bool {
                $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
                $dir = $root . '/storage/rate-limit';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }

                return is_dir($dir) && is_writable($dir);
            },
            'limited' => static fn (string $key, int $max): bool => IpRateLimiter::isLimited($key, $max),
            'hit' => static function (string $key, int $max, int $decay): void {
                IpRateLimiter::attempt($key, $max, $decay);
            },
            'clear' => static function (string $key): void {
                IpRateLimiter::reset($key);
            },
        ];
    }
}
