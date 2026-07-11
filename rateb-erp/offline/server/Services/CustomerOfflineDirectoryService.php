<?php

declare(strict_types=1);

namespace Rateb\App\Offline\Services;

/** Phase 13 — Customers master-data delta (read-only, field-allowlisted). */
final class CustomerOfflineDirectoryService extends AbstractMasterDataDirectoryService
{
    protected function entityType(): string
    {
        return 'customer_directory';
    }

    protected function table(): string
    {
        return 'rateb_customers';
    }

    protected function selectColumns(): array
    {
        $cols = ['id', 'company_id', 'code', 'name', 'phone', 'email', 'is_active'];
        if (OfflineSchema::hasColumn($this->table(), 'name_ar')) {
            $cols[] = 'name_ar';
        }
        if (OfflineSchema::hasColumn($this->table(), 'tax_id')) {
            $cols[] = 'tax_id';
        }

        return $cols;
    }

    protected function mapItem(array $row): array
    {
        $isActive = isset($row['is_active']) ? (int) $row['is_active'] : 1;
        $deleted = OfflineDeltaCursorCodec::isInactiveStatus('', $isActive);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'company_id' => (int) ($row['company_id'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'name_ar' => (string) ($row['name_ar'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'tax_id' => (string) ($row['tax_id'] ?? ''),
            'is_active' => $isActive,
            'active' => !$deleted,
            'deleted' => $deleted,
            'updated_at' => $row['updated_at'] ?? ($row['created_at'] ?? null),
            'version' => max(1, (int) ($row['id'] ?? 1)),
        ];
    }
}
