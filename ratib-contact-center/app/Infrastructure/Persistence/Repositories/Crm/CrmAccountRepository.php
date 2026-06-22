<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Infrastructure\Persistence\Repositories\Crm;

use Ratib\ContactCenter\App\Core\Database;

final class CrmAccountRepository
{
    /** @return list<array<string, mixed>> */
    public function list(int $tenantId, ?string $search = null, int $limit = 100): array
    {
        $sql = 'SELECT * FROM rcc_accounts WHERE tenant_id = :tid';
        $params = ['tid' => $tenantId];
        if ($search !== null && $search !== '') {
            $sql .= ' AND (name LIKE :q OR account_no LIKE :q OR email LIKE :q OR phone LIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM rcc_accounts WHERE tenant_id = :tid AND id = :id LIMIT 1');
        $stmt->execute(['tid' => $tenantId, 'id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** @param array<string, mixed> $data */
    public function save(int $tenantId, array $data, ?int $id = null): int
    {
        if ($id !== null && $id > 0) {
            $stmt = Database::connection()->prepare(
                'UPDATE rcc_accounts SET name=:name, name_ar=:name_ar, account_type=:atype, industry=:ind,
                 tier=:tier, phone=:phone, email=:email, website=:web, erp_company_id=:erp, billing_address=:addr,
                 owner_agent_id=:owner, status=:status, metadata_json=:meta
                 WHERE tenant_id=:tid AND id=:id'
            );
            $stmt->execute([
                'tid' => $tenantId, 'id' => $id,
                'name' => (string) ($data['name'] ?? ''),
                'name_ar' => $data['name_ar'] ?? null,
                'atype' => (string) ($data['account_type'] ?? 'company'),
                'ind' => $data['industry'] ?? null,
                'tier' => $data['tier'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'web' => $data['website'] ?? null,
                'erp' => isset($data['erp_company_id']) ? (int) $data['erp_company_id'] : null,
                'addr' => $data['billing_address'] ?? null,
                'owner' => isset($data['owner_agent_id']) ? (int) $data['owner_agent_id'] : null,
                'status' => (string) ($data['status'] ?? 'active'),
                'meta' => isset($data['metadata_json']) ? json_encode($data['metadata_json'], JSON_UNESCAPED_UNICODE) : null,
            ]);
            return $id;
        }
        $no = (string) ($data['account_no'] ?? ('ACC-' . strtoupper(bin2hex(random_bytes(3)))));
        $stmt = Database::connection()->prepare(
            'INSERT INTO rcc_accounts (tenant_id, account_no, name, name_ar, account_type, industry, tier, phone, email, website, erp_company_id, billing_address, owner_agent_id, status, metadata_json)
             VALUES (:tid, :no, :name, :name_ar, :atype, :ind, :tier, :phone, :email, :web, :erp, :addr, :owner, :status, :meta)'
        );
        $stmt->execute([
            'tid' => $tenantId, 'no' => $no,
            'name' => (string) ($data['name'] ?? 'Account'),
            'name_ar' => $data['name_ar'] ?? null,
            'atype' => (string) ($data['account_type'] ?? 'company'),
            'ind' => $data['industry'] ?? null,
            'tier' => $data['tier'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'web' => $data['website'] ?? null,
            'erp' => isset($data['erp_company_id']) ? (int) $data['erp_company_id'] : null,
            'addr' => $data['billing_address'] ?? null,
            'owner' => isset($data['owner_agent_id']) ? (int) $data['owner_agent_id'] : null,
            'status' => (string) ($data['status'] ?? 'active'),
            'meta' => isset($data['metadata_json']) ? json_encode($data['metadata_json'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}
