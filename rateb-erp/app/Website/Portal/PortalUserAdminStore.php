<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

/**
 * Company-scoped persistence for admin management of rateb_website_portal_users.
 * Every method takes the tenant company explicitly; implementations must never widen scope.
 */
interface PortalUserAdminStore
{
    /** @return list<array<string, mixed>> never includes password_hash */
    public function listForCompany(int $companyId, int $limit): array;

    /** @return array<string, mixed>|null never includes password_hash */
    public function findById(int $companyId, int $id): ?array;

    public function findIdByEmail(int $companyId, string $portalType, string $email): ?int;

    /**
     * Inserts an active account without app access.
     *
     * @param array<string, mixed> $row portal_type, email, password_hash, full_name, phone, organization_name, crm_company_id, erp_customer_id
     */
    public function insert(int $companyId, array $row): int;

    /** @param array<string, mixed> $fields */
    public function update(int $companyId, int $id, array $fields): int;

    /**
     * @param list<int> $userIds
     * @return array<int, array{active: int, last_used_at: ?string}>
     */
    public function tokenStats(int $companyId, array $userIds, string $now): array;

    public function crmCompanyExists(int $companyId, int $crmCompanyId): bool;

    public function customerExists(int $companyId, int $customerId): bool;
}
