<?php
declare(strict_types=1);

namespace Rateb\App\CustomerPortal;

/**
 * Resolves the single company a Customer Portal request belongs to.
 *
 * - Central host (rateb.sa): company comes only from an explicit slug.
 * - Dedicated host (admin.rateb.sa, alarfaj.rateb.sa): company comes only from the host's
 *   bound ERP database, which must contain exactly one company.
 *
 * There is no fallback company. Any ambiguity fails closed.
 */
final class CustomerPortalPlatformResolver
{
    public const MODE_CENTRAL = 'central';
    public const MODE_DEDICATED = 'dedicated';

    private const DEFAULT_DEDICATED_HOSTS = ['admin.rateb.sa', 'alarfaj.rateb.sa'];

    private CustomerPortalStore $store;

    /** @var array{is_central: \Closure, is_allowed: \Closure, dedicated_binding: \Closure} */
    private array $hostPolicy;

    /**
     * @param array{is_central: \Closure, is_allowed: \Closure, dedicated_binding: \Closure}|null $hostPolicy
     *        dedicated_binding(host) returns array{db:string, suspended:bool}|null
     */
    public function __construct(CustomerPortalStore $store, ?array $hostPolicy = null)
    {
        $this->store = $store;
        $this->hostPolicy = $hostPolicy ?? self::defaultHostPolicy();
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        if ($host !== '' && str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        return rtrim($host, '.');
    }

    /**
     * @return array{ok: bool, reason?: string, mode?: string, company?: array<string, mixed>}
     */
    public function resolve(string $host, ?string $companySlug, int $now): array
    {
        $host = self::normalizeHost($host);
        if ($host === '') {
            return self::fail('host_missing');
        }
        if (!(bool) ($this->hostPolicy['is_allowed'])($host)) {
            return self::fail('host_not_allowed');
        }

        if ((bool) ($this->hostPolicy['is_central'])($host)) {
            return $this->resolveCentral(trim((string) $companySlug), $now);
        }

        return $this->resolveDedicated($host);
    }

    /** @return array{ok: bool, reason?: string, mode?: string, company?: array<string, mixed>} */
    private function resolveCentral(string $slug, int $now): array
    {
        if ($slug === '') {
            return self::fail('company_slug_required');
        }
        if (!preg_match('/^[A-Za-z0-9._-]{1,120}$/', $slug)) {
            return self::fail('company_slug_invalid');
        }
        $company = $this->store->findCompanyBySlug($slug);
        if ($company === null || (int) ($company['id'] ?? 0) < 1) {
            return self::fail('company_not_found');
        }
        if ((string) ($company['status'] ?? '') !== 'active') {
            return self::fail('company_inactive');
        }
        if (!$this->store->hasValidSubscription((int) $company['id'], $now)) {
            return self::fail('subscription_inactive');
        }

        return ['ok' => true, 'mode' => self::MODE_CENTRAL, 'company' => $company];
    }

    /** @return array{ok: bool, reason?: string, mode?: string, company?: array<string, mixed>} */
    private function resolveDedicated(string $host): array
    {
        $binding = ($this->hostPolicy['dedicated_binding'])($host);
        $boundDb = is_array($binding) ? trim((string) ($binding['db'] ?? '')) : '';
        if ($boundDb === '') {
            return self::fail('host_not_bound');
        }
        if (!empty($binding['suspended'])) {
            return self::fail('platform_suspended');
        }
        if ($this->store->currentDatabaseName() !== $boundDb) {
            return self::fail('database_mismatch');
        }
        $companies = $this->store->listCompanies(2);
        if (count($companies) !== 1 || (int) ($companies[0]['id'] ?? 0) < 1) {
            return self::fail('company_ambiguous');
        }
        $company = $companies[0];
        if ((string) ($company['status'] ?? '') !== 'active') {
            return self::fail('company_inactive');
        }

        return ['ok' => true, 'mode' => self::MODE_DEDICATED, 'company' => $company];
    }

    /** @return array{ok: false, reason: string} */
    private static function fail(string $reason): array
    {
        return ['ok' => false, 'reason' => $reason];
    }

    /** @return array{is_central: \Closure, is_allowed: \Closure, dedicated_binding: \Closure} */
    public static function defaultHostPolicy(): array
    {
        $root = defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2);
        $resolverFile = dirname($root) . '/config/env/erp_agency_resolver.php';
        if (is_file($resolverFile)) {
            require_once $resolverFile;
        }

        $isCentral = static function (string $host): bool {
            if (function_exists('rateb_erp_is_main_platform_host')) {
                return rateb_erp_is_main_platform_host($host);
            }

            return in_array($host, ['rateb.sa', 'www.rateb.sa'], true);
        };

        $dedicatedHosts = self::DEFAULT_DEDICATED_HOSTS;
        $fromEnv = getenv('RATEB_CUSTOMER_PORTAL_HOSTS');
        if ($fromEnv !== false && trim((string) $fromEnv) !== '') {
            $dedicatedHosts = [];
            foreach (preg_split('/[\s,;]+/', (string) $fromEnv) ?: [] as $piece) {
                $piece = self::normalizeHost((string) $piece);
                if ($piece !== '') {
                    $dedicatedHosts[] = $piece;
                }
            }
        }

        return [
            'is_central' => $isCentral,
            'is_allowed' => static fn (string $host): bool => $isCentral($host) || in_array($host, $dedicatedHosts, true),
            'dedicated_binding' => static function (string $host): ?array {
                if (!function_exists('rateb_lookup_agency_erp_by_host')) {
                    return null;
                }
                $row = rateb_lookup_agency_erp_by_host($host);
                if (!is_array($row)) {
                    return null;
                }
                $suspended = (defined('RATEB_ERP_COMMERCIAL_SUSPENDED') && RATEB_ERP_COMMERCIAL_SUSPENDED)
                    || (int) ($row['is_suspended'] ?? 0) === 1;

                return ['db' => trim((string) ($row['erp_db_name'] ?? '')), 'suspended' => $suspended];
            },
        ];
    }
}
