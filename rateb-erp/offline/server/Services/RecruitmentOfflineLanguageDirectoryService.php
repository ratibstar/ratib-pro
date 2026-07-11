<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/** Phase 15B — Languages directory delta (read-only; id cursor — no updated_at). */
final class RecruitmentOfflineLanguageDirectoryService extends AbstractMasterDataDirectoryService
{
    protected function entityType(): string
    {
        return 'recruitment_language_directory';
    }

    protected function table(): string
    {
        return 'rateb_recruitment_languages';
    }

    protected function requiresUpdatedAt(): bool
    {
        return false;
    }

    protected function selectColumns(): array
    {
        return ['id', 'company_id', 'name', 'code', 'status'];
    }

    protected function mapItem(array $row): array
    {
        $status = (string) ($row['status'] ?? 'active');
        $active = $status === 'active';

        return [
            'id' => (int) ($row['id'] ?? 0),
            'company_id' => (int) ($row['company_id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'code' => (string) ($row['code'] ?? ''),
            'status' => $status,
            'active' => $active,
            'deleted' => !$active,
            'updated_at' => $row['updated_at'] ?? ($row['created_at'] ?? null),
            'version' => max(1, (int) ($row['id'] ?? 1)),
        ];
    }
}
