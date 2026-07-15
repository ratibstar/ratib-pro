<?php
declare(strict_types=1);

namespace Rateb\App\Website\Portal;

use Rateb\App\Core\SessionManager;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-07 — Isolated portal auth (employer / customer / partner).
 * Never uses ERP User accounts.
 */
final class PortalAuthService
{
    public const TYPE_EMPLOYER = 'employer';
    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_PARTNER = 'partner';

    private const SESSION_USER = 'rateb_website_portal_user_id';
    private const SESSION_COMPANY = 'rateb_website_portal_company_id';
    private const SESSION_TYPE = 'rateb_website_portal_type';

    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /** @return list<string> */
    public static function types(): array
    {
        return [self::TYPE_EMPLOYER, self::TYPE_CUSTOMER, self::TYPE_PARTNER];
    }

    public static function isValidType(string $type): bool
    {
        return in_array($type, self::types(), true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, user_id?: int, error?: string}
     */
    public function register(string $portalType, array $data): array
    {
        if (!self::isValidType($portalType)) {
            return ['ok' => false, 'error' => 'invalid_portal_type'];
        }
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $name = trim((string) ($data['full_name'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'invalid_email'];
        }
        if ($name === '') {
            return ['ok' => false, 'error' => 'full_name_required'];
        }
        if (strlen($password) < 8) {
            return ['ok' => false, 'error' => 'password_min'];
        }
        if ($this->findByEmail($portalType, $email) !== null) {
            return ['ok' => false, 'error' => 'email_taken'];
        }

        $this->repo->execute(
            'INSERT INTO rateb_website_portal_users
             (company_id, portal_type, email, password_hash, full_name, phone, organization_name, locale, status)
             VALUES (:cid, :ptype, :email, :hash, :name, :phone, :org, :loc, :st)',
            [
                'cid' => $this->repo->companyId(),
                'ptype' => $portalType,
                'email' => $email,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'org' => trim((string) ($data['organization_name'] ?? '')) ?: null,
                'loc' => in_array((string) ($data['locale'] ?? 'en'), ['en', 'ar'], true) ? (string) $data['locale'] : 'en',
                'st' => 'active',
            ]
        );
        $userId = (int) $this->repo->lastInsertId();
        $this->establishSession($userId, $portalType);

        return ['ok' => true, 'user_id' => $userId];
    }

    /** @return array{ok: bool, error?: string} */
    public function login(string $portalType, string $email, string $password): array
    {
        if (!self::isValidType($portalType)) {
            return ['ok' => false, 'error' => 'invalid_portal_type'];
        }
        $row = $this->findByEmail($portalType, strtolower(trim($email)));
        if ($row === null || (string) ($row['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'invalid_credentials'];
        }
        if (!password_verify($password, (string) ($row['password_hash'] ?? ''))) {
            return ['ok' => false, 'error' => 'invalid_credentials'];
        }
        $this->establishSession((int) $row['id'], $portalType);
        $this->repo->execute(
            'UPDATE rateb_website_portal_users SET last_login_at = NOW() WHERE id = :id AND company_id = :cid',
            ['id' => (int) $row['id'], 'cid' => $this->repo->companyId()]
        );

        return ['ok' => true];
    }

    public function logout(): void
    {
        SessionManager::forget(self::SESSION_USER);
        SessionManager::forget(self::SESSION_COMPANY);
        SessionManager::forget(self::SESSION_TYPE);
    }

    /** @return array<string, mixed>|null */
    public function currentUser(?string $expectedType = null): ?array
    {
        $uid = (int) SessionManager::get(self::SESSION_USER, 0);
        $cid = (int) SessionManager::get(self::SESSION_COMPANY, 0);
        $type = (string) SessionManager::get(self::SESSION_TYPE, '');
        if ($uid < 1 || $cid !== $this->repo->companyId()) {
            return null;
        }
        if ($expectedType !== null && $type !== $expectedType) {
            return null;
        }
        $row = $this->repo->fetchOne(
            'SELECT id, company_id, portal_type, email, full_name, phone, organization_name,
                    crm_company_id, erp_customer_id, locale, status, meta_json
             FROM rateb_website_portal_users
             WHERE id = :id AND company_id = :cid AND status = :st LIMIT 1',
            ['id' => $uid, 'cid' => $cid, 'st' => 'active']
        );
        if ($row === null) {
            return null;
        }
        if ($expectedType !== null && (string) ($row['portal_type'] ?? '') !== $expectedType) {
            return null;
        }

        return $row;
    }

    public function isLoggedIn(?string $expectedType = null): bool
    {
        return $this->currentUser($expectedType) !== null;
    }

    /** @param array<string, mixed> $data */
    public function updateProfile(int $userId, array $data): void
    {
        $user = $this->currentUser();
        if ($user === null || (int) ($user['id'] ?? 0) !== $userId) {
            throw new \RuntimeException('portal_user_required');
        }
        $patch = [];
        foreach (['full_name', 'phone', 'organization_name'] as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) $data[$k]);
                $patch[$k] = $v !== '' ? $v : null;
            }
        }
        if (isset($data['password']) && (string) $data['password'] !== '') {
            if (strlen((string) $data['password']) < 8) {
                throw new \InvalidArgumentException('password_min');
            }
            $patch['password_hash'] = password_hash((string) $data['password'], PASSWORD_DEFAULT);
        }
        if ($patch === []) {
            return;
        }
        $sets = [];
        $params = ['id' => $userId, 'cid' => (int) $user['company_id']];
        foreach ($patch as $k => $v) {
            $sets[] = $k . ' = :' . $k;
            $params[$k] = $v;
        }
        $this->repo->execute(
            'UPDATE rateb_website_portal_users SET ' . implode(', ', $sets)
            . ' WHERE id = :id AND company_id = :cid',
            $params
        );
    }

    public function linkCrmCompany(int $userId, int $crmCompanyId): void
    {
        $this->repo->execute(
            'UPDATE rateb_website_portal_users SET crm_company_id = :crm
             WHERE id = :id AND company_id = :cid',
            ['crm' => $crmCompanyId, 'id' => $userId, 'cid' => $this->repo->companyId()]
        );
    }

    /** @return array<string, mixed>|null */
    private function findByEmail(string $portalType, string $email): ?array
    {
        if ($email === '') {
            return null;
        }
        [$where, $params] = $this->repo->companyWhere();
        $params['email'] = $email;
        $params['ptype'] = $portalType;

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_website_portal_users WHERE {$where} AND portal_type = :ptype AND email = :email LIMIT 1",
            $params
        );
    }

    private function establishSession(int $userId, string $portalType): void
    {
        SessionManager::set(self::SESSION_USER, $userId);
        SessionManager::set(self::SESSION_COMPANY, $this->repo->companyId());
        SessionManager::set(self::SESSION_TYPE, $portalType);
    }
}
