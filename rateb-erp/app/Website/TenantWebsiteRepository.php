<?php
declare(strict_types=1);

namespace Rateb\App\Website;

use PDO;
use Rateb\App\Core\Database;

/**
 * Phase WEBSITE-03 — Tenant-scoped CMS data access (no cross-tenant reads).
 */
final class TenantWebsiteRepository
{
    private int $companyId;
    private bool $scoped;

    public function __construct(?WebsiteContext $ctx = null)
    {
        $ctx = $ctx ?? WebsiteContext::current();
        if ($ctx === null) {
            throw new \RuntimeException('TenantWebsiteRepository requires WebsiteContext');
        }
        $this->companyId = $ctx->companyId();
        $this->scoped = $ctx->isolationEnabled();
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function scoped(): bool
    {
        return $this->scoped;
    }

    /** @return array{0:string,1:array<string,mixed>} */
    public function companyWhere(string $alias = ''): array
    {
        if (!$this->scoped) {
            return ['1=1', []];
        }
        $col = $alias !== '' ? $alias . '.company_id' : 'company_id';

        return [$col . ' = :website_company_id', ['website_company_id' => $this->companyId]];
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): void
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
    }

    public function lastInsertId(): string
    {
        return Database::connection()->lastInsertId();
    }

    public function assertRowCompany(?array $row, string $entity = 'cms'): void
    {
        if (!$this->scoped || $row === null) {
            return;
        }
        $cid = (int) ($row['company_id'] ?? -1);
        if ($cid !== $this->companyId) {
            throw new \RuntimeException('Cross-tenant ' . $entity . ' access denied');
        }
    }
}
