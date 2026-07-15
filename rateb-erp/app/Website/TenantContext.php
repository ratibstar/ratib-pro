<?php
declare(strict_types=1);

namespace Rateb\App\Website;

/**
 * Phase WEBSITE-02 — Host-resolved tenant context (reuses agency_lookup; no new tenant system).
 */
final class TenantContext
{
    private static ?self $current = null;

    /** @param array<string, mixed>|null $agencyRow */
    private function __construct(
        private readonly string $host,
        private readonly bool $isPlatform,
        private readonly ?array $agencyRow,
    ) {
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function reset(): void
    {
        self::$current = null;
    }

    /**
     * Resolve tenant from Host using existing ERP agency resolvers.
     */
    public static function resolveFromRequest(): self
    {
        if (self::$current !== null) {
            return self::$current;
        }

        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        $isPlatform = in_array($host, ['rateb.sa', 'www.rateb.sa'], true);

        $agencyRow = null;
        if (!$isPlatform && $host !== '') {
            $agencyRow = self::lookupAgency($host);
        }

        self::$current = new self($host, $isPlatform, $agencyRow);

        return self::$current;
    }

    /** @return array<string, mixed>|null */
    private static function lookupAgency(string $host): ?array
    {
        $lookupFile = dirname(__DIR__, 3) . '/config/env/agency_lookup.php';
        if (!is_file($lookupFile)) {
            $lookupFile = dirname(__DIR__, 2) . '/../config/env/agency_lookup.php';
        }
        if (is_file($lookupFile)) {
            require_once $lookupFile;
        }

        if (function_exists('rateb_lookup_agency_erp_by_host')) {
            $row = rateb_lookup_agency_erp_by_host($host);
            if (is_array($row)) {
                return $row;
            }
        }
        if (function_exists('rateb_lookup_agency_by_host')) {
            $row = rateb_lookup_agency_by_host($host);
            if (is_array($row)) {
                return $row;
            }
        }

        return null;
    }

    public function host(): string
    {
        return $this->host;
    }

    public function isPlatform(): bool
    {
        return $this->isPlatform;
    }

    public function isAgency(): bool
    {
        return !$this->isPlatform && $this->agencyRow !== null;
    }

    /** @return array<string, mixed>|null */
    public function agency(): ?array
    {
        return $this->agencyRow;
    }

    public function agencyId(): int
    {
        return (int) ($this->agencyRow['id'] ?? 0);
    }

    public function siteUrl(): string
    {
        return trim((string) ($this->agencyRow['site_url'] ?? ''));
    }

    public function erpReady(): bool
    {
        if ($this->isPlatform) {
            return true;
        }
        if ($this->agencyRow === null) {
            return false;
        }
        $erpDb = trim((string) ($this->agencyRow['erp_db_name'] ?? $this->agencyRow['db_name'] ?? ''));
        $status = strtolower(trim((string) ($this->agencyRow['erp_status'] ?? '')));

        return $erpDb !== '' && ($status === '' || $status === 'ready');
    }
}
