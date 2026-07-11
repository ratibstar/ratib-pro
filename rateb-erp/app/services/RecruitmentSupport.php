<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\RecruitmentCandidate;

/**
 * Shared helpers for Phase 15A Recruitment domain services.
 * Offline Foundation must call domain services — never duplicate these helpers offline.
 */
final class RecruitmentSupport
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

    public static function userId(): ?int
    {
        $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $uid > 0 ? $uid : null;
    }

    public static function actorFields(bool $creating = true): array
    {
        $uid = self::userId();
        $out = ['updated_by' => $uid];
        if ($creating) {
            $out['created_by'] = $uid;
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public static function findCandidate(int $candidateId, int $companyId): ?array
    {
        if ($candidateId < 1 || $companyId < 1) {
            return null;
        }
        $row = (new RecruitmentCandidate())->queryOne(
            'SELECT * FROM rateb_recruitment_candidates
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL
             LIMIT 1',
            ['id' => $candidateId, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    public static function assertCandidate(int $candidateId, int $companyId): array
    {
        $row = self::findCandidate($candidateId, $companyId);
        if ($row === null) {
            throw new \RuntimeException('candidate_not_found');
        }

        return $row;
    }

    public static function nextCandidateNo(int $companyId): string
    {
        $row = (new RecruitmentCandidate())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_recruitment_candidates WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return 'RC-' . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public static function nextContractNo(int $companyId): string
    {
        $row = (new RecruitmentCandidate())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_recruitment_contracts WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return 'RCT-' . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public static function nextAgencyCode(int $companyId): string
    {
        $row = (new RecruitmentCandidate())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_recruitment_agencies WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return 'RA-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
