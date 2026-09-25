<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use PDO;
use Rateb\App\Core\Database;

final class PdoPortalUserAdminStore implements PortalUserAdminStore
{
    private const PUBLIC_COLUMNS = 'id, company_id, portal_type, email, full_name, phone, organization_name,
        crm_company_id, erp_customer_id, status, app_access_approved_at, last_login_at, created_at, updated_at';

    /** Columns an admin update may touch. company_id and id are never updatable. */
    private const UPDATABLE = [
        'portal_type', 'email', 'full_name', 'phone', 'organization_name',
        'crm_company_id', 'erp_customer_id', 'password_hash', 'status', 'app_access_approved_at',
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    public function listForCompany(int $companyId, int $limit): array
    {
        $limit = max(1, min($limit, 1000));
        $stmt = $this->pdo->prepare(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM rateb_website_portal_users
             WHERE company_id = :cid ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute(['cid' => $companyId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    public function findById(int $companyId, int $id): ?array
    {
        return $this->one(
            'SELECT ' . self::PUBLIC_COLUMNS . ' FROM rateb_website_portal_users
             WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $id, 'cid' => $companyId]
        );
    }

    public function findIdByEmail(int $companyId, string $portalType, string $email): ?int
    {
        $row = $this->one(
            'SELECT id FROM rateb_website_portal_users
             WHERE company_id = :cid AND portal_type = :ptype AND email = :email LIMIT 1',
            ['cid' => $companyId, 'ptype' => $portalType, 'email' => $email]
        );

        return $row !== null ? (int) $row['id'] : null;
    }

    public function insert(int $companyId, array $row): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO rateb_website_portal_users
             (company_id, portal_type, email, password_hash, full_name, phone, organization_name,
              crm_company_id, erp_customer_id, status, app_access_approved_at)
             VALUES (:cid, :ptype, :email, :hash, :name, :phone, :org, :crm, :cust, 'active', NULL)"
        );
        $stmt->execute([
            'cid' => $companyId,
            'ptype' => (string) $row['portal_type'],
            'email' => (string) $row['email'],
            'hash' => (string) $row['password_hash'],
            'name' => (string) $row['full_name'],
            'phone' => $row['phone'] ?? null,
            'org' => $row['organization_name'] ?? null,
            'crm' => $row['crm_company_id'] ?? null,
            'cust' => $row['erp_customer_id'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $companyId, int $id, array $fields): int
    {
        $sets = [];
        $params = ['id' => $id, 'cid' => $companyId];
        foreach ($fields as $column => $value) {
            if (!in_array($column, self::UPDATABLE, true)) {
                throw new \InvalidArgumentException('column_not_updatable');
            }
            $sets[] = $column . ' = :f_' . $column;
            $params['f_' . $column] = $value;
        }
        if ($sets === []) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE rateb_website_portal_users SET ' . implode(', ', $sets)
            . ' WHERE id = :id AND company_id = :cid'
        );
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function tokenStats(int $companyId, array $userIds, string $now): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $v): bool => $v > 0)));
        if ($userIds === []) {
            return [];
        }
        $params = ['cid' => $companyId, 'now' => $now];
        $in = [];
        foreach ($userIds as $i => $uid) {
            $in[] = ':u' . $i;
            $params['u' . $i] = $uid;
        }
        $stmt = $this->pdo->prepare(
            'SELECT portal_user_id,
                    SUM(CASE WHEN revoked_at IS NULL AND expires_at > :now THEN 1 ELSE 0 END) AS active_tokens,
                    MAX(last_used_at) AS last_used_at
             FROM rateb_customer_portal_tokens
             WHERE company_id = :cid AND portal_user_id IN (' . implode(', ', $in) . ')
             GROUP BY portal_user_id'
        );
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) $row['portal_user_id']] = [
                'active' => (int) $row['active_tokens'],
                'last_used_at' => $row['last_used_at'] !== null ? (string) $row['last_used_at'] : null,
            ];
        }

        return $out;
    }

    public function crmCompanyExists(int $companyId, int $crmCompanyId): bool
    {
        return $this->one(
            'SELECT id FROM rateb_crm_companies WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $crmCompanyId, 'cid' => $companyId]
        ) !== null;
    }

    public function customerExists(int $companyId, int $customerId): bool
    {
        return $this->one(
            'SELECT id FROM rateb_customers WHERE id = :id AND company_id = :cid LIMIT 1',
            ['id' => $customerId, 'cid' => $companyId]
        ) !== null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function one(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }
}
