<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/** Phase 13 — Branches master-data delta (read-only, field-allowlisted). */
final class BranchOfflineDirectoryService extends AbstractMasterDataDirectoryService
{
    protected function entityType(): string
    {
        return 'branch_directory';
    }

    protected function table(): string
    {
        return 'rateb_branches';
    }

    protected function selectColumns(): array
    {
        $cols = ['id', 'company_id', 'name', 'code', 'address', 'phone', 'email', 'status'];
        if (OfflineSchema::hasColumn($this->table(), 'is_main')) {
            $cols[] = 'is_main';
        }

        return $cols;
    }

    protected function mapItem(array $row): array
    {
        $status = (string) ($row['status'] ?? '');
        $deleted = OfflineDeltaCursorCodec::isInactiveStatus($status);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'company_id' => (int) ($row['company_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
            'address' => (string) ($row['address'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'status' => $status,
            'is_main' => isset($row['is_main']) ? (int) $row['is_main'] : 0,
            'active' => !$deleted,
            'deleted' => $deleted,
            'updated_at' => $row['updated_at'] ?? ($row['created_at'] ?? null),
            'version' => max(1, (int) ($row['id'] ?? 1)),
        ];
    }
}
