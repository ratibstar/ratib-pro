<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories;

use Ratib\ContactCenter\App\Core\Database;

final class CustomerIdentityRepository
{
    /** @return array<string, mixed>|null */
    public function findByPhone(int $tenantId, string $phone): ?array
    {
        return $this->findByColumn($tenantId, 'phone', $phone);
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(int $tenantId, string $email): ?array
    {
        return $this->findByColumn($tenantId, 'email', strtolower($email));
    }

    /** @return array<string, mixed>|null */
    public function findByErpCustomerId(int $tenantId, int $erpCustomerId): ?array
    {
        return $this->findByColumn($tenantId, 'erp_customer_id', (string) $erpCustomerId);
    }

    public function upsert(
        int $tenantId,
        ?int $contactId,
        ?string $phone,
        ?string $email,
        ?int $erpCustomerId,
        string $matchedBy,
        float $confidence
    ): void {
        try {
            $existing = null;
            if ($phone !== null && $phone !== '') {
                $existing = $this->findByPhone($tenantId, $phone);
            }
            if ($existing === null && $email !== null && $email !== '') {
                $existing = $this->findByEmail($tenantId, $email);
            }

            if ($existing !== null) {
                $stmt = Database::connection()->prepare(
                    'UPDATE rcc_customer_identity_map SET
                        contact_id = COALESCE(:cid, contact_id),
                        phone = COALESCE(:phone, phone),
                        email = COALESCE(:email, email),
                        erp_customer_id = COALESCE(:erp, erp_customer_id),
                        matched_by = :matched,
                        confidence = :conf,
                        updated_at = NOW()
                     WHERE id = :id'
                );
                $stmt->execute([
                    'cid' => $contactId,
                    'phone' => $phone,
                    'email' => $email,
                    'erp' => $erpCustomerId,
                    'matched' => $matchedBy,
                    'conf' => $confidence,
                    'id' => (int) $existing['id'],
                ]);
                return;
            }

            $stmt = Database::connection()->prepare(
                'INSERT INTO rcc_customer_identity_map
                 (tenant_id, contact_id, phone, email, erp_customer_id, matched_by, confidence)
                 VALUES (:tid, :cid, :phone, :email, :erp, :matched, :conf)'
            );
            $stmt->execute([
                'tid' => $tenantId,
                'cid' => $contactId,
                'phone' => $phone,
                'email' => $email,
                'erp' => $erpCustomerId,
                'matched' => $matchedBy,
                'conf' => $confidence,
            ]);
        } catch (\Throwable $e) {
            error_log('[RCC Identity] upsert failed: ' . $e->getMessage());
        }
    }

    /** @return array<string, mixed>|null */
    private function findByColumn(int $tenantId, string $column, string $value): ?array
    {
        if (!in_array($column, ['phone', 'email', 'erp_customer_id'], true)) {
            return null;
        }
        try {
            $stmt = Database::connection()->prepare(
                "SELECT id, tenant_id, contact_id, phone, email, erp_customer_id, confidence, matched_by
                 FROM rcc_customer_identity_map
                 WHERE tenant_id = :tid AND {$column} = :val LIMIT 1"
            );
            $stmt->execute(['tid' => $tenantId, 'val' => $value]);
            $row = $stmt->fetch();
            return $row !== false ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
