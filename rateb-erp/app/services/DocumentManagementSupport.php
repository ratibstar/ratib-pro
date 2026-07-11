<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\DmsDocument;

/**
 * Shared helpers for Phase 26A Enterprise Document Management (DMS) domain services.
 * Soft-links legacy_document_id only — never mutates rateb_documents or DocumentService.
 */
final class DocumentManagementSupport
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

    public static function nextCode(string $table, string $prefix, int $companyId): string
    {
        $allowed = [
            'rateb_dms_repositories',
            'rateb_dms_folders',
            'rateb_dms_documents',
            'rateb_dms_retention_policies',
            'rateb_dms_legal_holds',
            'rateb_dms_categories',
            'rateb_dms_tags',
            'rateb_dms_signature_requests',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_code_table');
        }
        $row = (new DmsDocument())->queryOne(
            'SELECT COUNT(*) AS c FROM ' . $table . ' WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return $prefix . '-' . date('Y') . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function assertOptimisticVersion(array $row, mixed $expectedVersion): void
    {
        if ($expectedVersion === null || $expectedVersion === '') {
            return;
        }
        if ((int) $expectedVersion !== (int) ($row['version'] ?? 1)) {
            throw new \RuntimeException('version_conflict');
        }
    }

    /** @return array<string, mixed>|null */
    public static function findDocument(int $id, int $companyId): ?array
    {
        if ($id < 1 || $companyId < 1) {
            return null;
        }
        $row = (new DmsDocument())->queryOne(
            'SELECT * FROM rateb_dms_documents WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertDocument(int $id, int $companyId): array
    {
        $row = self::findDocument($id, $companyId);
        if ($row === null) {
            throw new \RuntimeException('document_not_found');
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

    public static function dateOrNull(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));
        if ($s === '') {
            return null;
        }

        return substr($s, 0, 32);
    }

    public static function shareToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
