<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm;

use Ratib\ContactCenter\App\Core\Database;

final class CrmContactRepository
{
    /** @return list<array<string, mixed>> */
    public function list(int $tenantId, ?int $accountId = null, ?string $search = null, int $limit = 100): array
    {
        $sql = 'SELECT c.*, a.name AS account_name FROM rcc_contacts c
                LEFT JOIN rcc_accounts a ON a.id = c.account_id AND a.tenant_id = c.tenant_id
                WHERE c.tenant_id = :tid AND c.deleted_at IS NULL';
        $params = ['tid' => $tenantId];
        if ($accountId !== null && $accountId > 0) {
            $sql .= ' AND c.account_id = :aid';
            $params['aid'] = $accountId;
        }
        if ($search !== null && $search !== '') {
            $sql .= ' AND (c.full_name LIKE :q OR c.email LIKE :q OR c.phone_primary LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY c.updated_at DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT c.*, a.name AS account_name FROM rcc_contacts c
             LEFT JOIN rcc_accounts a ON a.id = c.account_id AND a.tenant_id = c.tenant_id
             WHERE c.tenant_id = :tid AND c.id = :id AND c.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['tid' => $tenantId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function save(int $tenantId, array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = Database::connection()->prepare(
                'UPDATE rcc_contacts SET full_name=:name, email=:email, phone_primary=:phone,
                 company_id=:cid, account_id=:aid, contact_type=:ctype, status=:status
                 WHERE tenant_id=:tid AND id=:id'
            );
            $stmt->execute([
                'tid' => $tenantId, 'id' => $id,
                'name' => (string) ($data['full_name'] ?? ''),
                'email' => $data['email'] ?? null,
                'phone' => $data['phone_primary'] ?? null,
                'cid' => isset($data['company_id']) ? (int) $data['company_id'] : null,
                'aid' => isset($data['account_id']) ? (int) $data['account_id'] : null,
                'ctype' => (string) ($data['contact_type'] ?? 'standard'),
                'status' => (string) ($data['status'] ?? 'active'),
            ]);
            return $id;
        }
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_contacts (tenant_id, full_name, email, phone_primary, company_id, account_id, contact_type, status)
             VALUES (:tid, :name, :email, :phone, :cid, :aid, :ctype, :status)'
        );
        $stmt->execute([
            'tid' => $tenantId,
            'name' => (string) ($data['full_name'] ?? 'Contact'),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone_primary'] ?? null,
            'cid' => isset($data['company_id']) ? (int) $data['company_id'] : null,
            'aid' => isset($data['account_id']) ? (int) $data['account_id'] : null,
            'ctype' => (string) ($data['contact_type'] ?? 'standard'),
            'status' => (string) ($data['status'] ?? 'active'),
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}
