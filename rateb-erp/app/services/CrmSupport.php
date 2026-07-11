<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\CrmLead;

/**
 * Shared helpers for Phase 17A CRM domain services.
 * Future Offline (17B) must call domain services — never duplicate these helpers offline.
 */
final class CrmSupport
{
    public static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function requireCompanyId(): int
    {
        $cid = (int) (TenantContext::companyId() ?? 0);
        if ($cid < 1) {
            throw new \RuntimeException('company_required');
        }

        return $cid;
    }

    public static function branchId(): ?int
    {
        $bid = (int) (SessionManager::get('rateb_branch_id') ?? SessionManager::get('branch_id') ?? 0);

        return $bid > 0 ? $bid : null;
    }

    public static function userId(): ?int
    {
        $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $uid > 0 ? $uid : null;
    }

    /** @return array<string, mixed> */
    public static function actorFields(bool $creating = true): array
    {
        $uid = self::userId();
        $out = ['updated_by' => $uid];
        if ($creating) {
            $out['created_by'] = $uid;
        }

        return $out;
    }

    public static function nextLeadNo(int $companyId): string
    {
        $row = (new CrmLead())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_leads WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return 'LD-' . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public static function nextOpportunityNo(int $companyId): string
    {
        $row = (new CrmLead())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_crm_opportunities WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return 'OP-' . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public static function nextCode(string $table, string $prefix, int $companyId): string
    {
        $allowed = [
            'rateb_crm_companies', 'rateb_crm_campaigns', 'rateb_crm_pipelines',
            'rateb_crm_lead_sources', 'rateb_crm_tags',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_code_table');
        }
        $row = (new CrmLead())->queryOne(
            'SELECT COUNT(*) AS c FROM ' . $table . ' WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return $prefix . '-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed>|null */
    public static function findLead(int $leadId, int $companyId): ?array
    {
        if ($leadId < 1 || $companyId < 1) {
            return null;
        }
        $row = (new CrmLead())->queryOne(
            'SELECT * FROM rateb_crm_leads WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $leadId, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertLead(int $leadId, int $companyId): array
    {
        $row = self::findLead($leadId, $companyId);
        if ($row === null) {
            throw new \RuntimeException('lead_not_found');
        }

        return $row;
    }

    public static function nullIfEmpty(mixed $v): mixed
    {
        if ($v === null) {
            return null;
        }
        if (is_string($v) && trim($v) === '') {
            return null;
        }

        return $v;
    }

    public static function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }
}
