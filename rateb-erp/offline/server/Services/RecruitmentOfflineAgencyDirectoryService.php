<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

use Rateb\App\Core\Database;
use Rateb\App\Core\TenantContext;
use Rateb\App\Offline\Models\OfflineEntityCursor;

/**
 * Additive recruitment agency directory delta (Phase 15B) — read-only.
 * Available when offline.recruitment OR offline.master_data is enabled.
 */
final class RecruitmentOfflineAgencyDirectoryService
{
    private const ENTITY = 'recruitment_agency_directory';

    private ?OfflineEntityCursor $cursors = null;
    private ?OfflineFeatureFlagService $flags = null;

    private function cursors(): OfflineEntityCursor
    {
        return $this->cursors ??= new OfflineEntityCursor();
    }

    private function flags(): OfflineFeatureFlagService
    {
        return $this->flags ??= new OfflineFeatureFlagService();
    }

    public function isAvailable(): bool
    {
        return OfflineSchema::hasColumn('rateb_recruitment_agencies', 'id');
    }

    /**
     * @return array<string, mixed>
     */
    public function pull(?int $companyId = null, ?int $branchId = null, ?string $cursorToken = null, int $limit = 200): array
    {
        if (!$this->flags()->isRecruitmentEnabled() && !$this->flags()->isMasterDataEnabled()) {
            return [
                'entity_type' => self::ENTITY,
                'items' => [],
                'cursor_token' => $cursorToken,
                'stub' => true,
                'disabled' => true,
            ];
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return [
                'entity_type' => self::ENTITY,
                'items' => [],
                'cursor_token' => null,
                'error' => 'company_required',
            ];
        }

        if (!$this->isAvailable()) {
            return [
                'entity_type' => self::ENTITY,
                'items' => [],
                'cursor_token' => $cursorToken,
                'migration_required' => true,
            ];
        }

        $safeLimit = max(1, min(500, $limit));
        [$afterId, $afterUpdated] = OfflineDeltaCursorCodec::parse($cursorToken);

        $hasUpdated = OfflineSchema::hasColumn('rateb_recruitment_agencies', 'updated_at');
        $sql = 'SELECT id, company_id, branch_id, code, name, contact_name, email, phone,
                       country_code, status';
        if ($hasUpdated) {
            $sql .= ', updated_at, created_at';
        } else {
            $sql .= ', created_at';
        }
        $sql .= ' FROM rateb_recruitment_agencies WHERE company_id = :cid';
        $params = ['cid' => $companyId];

        if (OfflineSchema::hasColumn('rateb_recruitment_agencies', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }

        if ($branchId !== null && $branchId > 0 && OfflineSchema::hasColumn('rateb_recruitment_agencies', 'branch_id')) {
            $sql .= ' AND (branch_id = :bid OR branch_id IS NULL)';
            $params['bid'] = $branchId;
        }

        if ($afterId > 0) {
            if ($hasUpdated && $afterUpdated !== '') {
                $sql .= ' AND (updated_at > :u OR (updated_at = :u2 AND id > :aid))';
                $params['u'] = $afterUpdated;
                $params['u2'] = $afterUpdated;
                $params['aid'] = $afterId;
            } else {
                $sql .= ' AND id > :aid';
                $params['aid'] = $afterId;
            }
        }

        if ($hasUpdated) {
            $sql .= ' ORDER BY updated_at ASC, id ASC LIMIT ' . $safeLimit;
        } else {
            $sql .= ' ORDER BY id ASC LIMIT ' . $safeLimit;
        }

        $db = Database::connection();
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $items = [];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'active');
            $active = $status === 'active';
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'company_id' => (int) ($row['company_id'] ?? 0),
                'branch_id' => isset($row['branch_id']) && $row['branch_id'] !== null && $row['branch_id'] !== ''
                    ? (int) $row['branch_id'] : null,
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'contact_name' => (string) ($row['contact_name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'phone' => (string) ($row['phone'] ?? ''),
                'country_code' => (string) ($row['country_code'] ?? ''),
                'status' => $status,
                'active' => $active,
                'deleted' => !$active,
                'updated_at' => $row['updated_at'] ?? ($row['created_at'] ?? null),
                'version' => max(1, (int) ($row['id'] ?? 1)),
            ];
        }

        $nextCursor = $cursorToken;
        if ($items !== []) {
            $last = $items[count($items) - 1];
            $nextCursor = OfflineDeltaCursorCodec::encode(
                (int) $last['id'],
                (string) ($last['updated_at'] ?? '')
            );
            $this->persistCursor($companyId, $branchId, $nextCursor);
        }

        return [
            'entity_type' => self::ENTITY,
            'items' => $items,
            'cursor_token' => $nextCursor,
            'has_more' => count($items) >= $safeLimit,
            'stub' => false,
            'read_only' => true,
        ];
    }

    private function persistCursor(int $companyId, ?int $branchId, string $token): void
    {
        if (!OfflineSchema::hasColumn('rateb_offline_entity_cursors', 'id')) {
            return;
        }
        $params = ['cid' => $companyId, 'et' => self::ENTITY];
        $sql = 'SELECT id FROM rateb_offline_entity_cursors
                WHERE company_id = :cid AND entity_type = :et';
        if ($branchId !== null && $branchId > 0) {
            $sql .= ' AND branch_id = :bid';
            $params['bid'] = $branchId;
        } else {
            $sql .= ' AND branch_id IS NULL';
        }
        $sql .= ' LIMIT 1';
        $existing = $this->cursors()->queryOne($sql, $params);
        if ($existing !== null) {
            $this->cursors()->update((int) $existing['id'], [
                'cursor_token' => substr($token, 0, 128),
            ]);

            return;
        }
        $this->cursors()->create([
            'company_id' => $companyId,
            'branch_id' => ($branchId !== null && $branchId > 0) ? $branchId : null,
            'entity_type' => self::ENTITY,
            'cursor_token' => substr($token, 0, 128),
        ]);
    }

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) (TenantContext::companyId() ?? 0);
    }
}
