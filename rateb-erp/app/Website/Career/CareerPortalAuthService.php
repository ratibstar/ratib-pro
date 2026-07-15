<?php
declare(strict_types=1);

namespace Rateb\App\Website\Career;

use Rateb\App\Core\SessionManager;
use Rateb\App\Website\TenantWebsiteRepository;

/**
 * Phase WEBSITE-06 — Candidate portal auth (isolated from ERP users).
 */
final class CareerPortalAuthService
{
    private const SESSION_USER = 'rateb_career_portal_user_id';
    private const SESSION_COMPANY = 'rateb_career_portal_company_id';

    private TenantWebsiteRepository $repo;

    public function __construct(?TenantWebsiteRepository $repo = null)
    {
        $this->repo = $repo ?? new TenantWebsiteRepository();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, user_id?: int, error?: string}
     */
    public function register(array $data): array
    {
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

        $existing = $this->findByEmail($email);
        if ($existing !== null) {
            return ['ok' => false, 'error' => 'email_taken'];
        }

        $cid = $this->repo->companyId();
        $this->repo->execute(
            'INSERT INTO rateb_website_career_portal_users
             (company_id, email, password_hash, full_name, phone, nationality, country_code, city, locale, status)
             VALUES (:cid, :email, :hash, :name, :phone, :nat, :cc, :city, :loc, :st)',
            [
                'cid' => $cid,
                'email' => $email,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'nat' => $this->countryCode($data['nationality'] ?? null),
                'cc' => $this->countryCode($data['country_code'] ?? null),
                'city' => trim((string) ($data['city'] ?? '')) ?: null,
                'loc' => in_array((string) ($data['locale'] ?? 'en'), ['en', 'ar'], true) ? $data['locale'] : 'en',
                'st' => 'active',
            ]
        );
        $userId = (int) $this->repo->lastInsertId();
        $this->loginById($userId);

        return ['ok' => true, 'user_id' => $userId];
    }

    /** @return array{ok: bool, error?: string} */
    public function login(string $email, string $password): array
    {
        $row = $this->findByEmail(strtolower(trim($email)));
        if ($row === null || (string) ($row['status'] ?? '') !== 'active') {
            return ['ok' => false, 'error' => 'invalid_credentials'];
        }
        if (!password_verify($password, (string) ($row['password_hash'] ?? ''))) {
            return ['ok' => false, 'error' => 'invalid_credentials'];
        }
        $this->establishSession((int) $row['id']);

        return ['ok' => true];
    }

    public function logout(): void
    {
        SessionManager::forget(self::SESSION_USER);
        SessionManager::forget(self::SESSION_COMPANY);
    }

    /** @return array<string, mixed>|null */
    public function currentUser(): ?array
    {
        $uid = (int) SessionManager::get(self::SESSION_USER, 0);
        $cid = (int) SessionManager::get(self::SESSION_COMPANY, 0);
        if ($uid < 1 || $cid !== $this->repo->companyId()) {
            return null;
        }

        return $this->repo->fetchOne(
            "SELECT id, company_id, email, full_name, phone, nationality, country_code, city,
                    linkedin_url, portfolio_url, resume_media_id, resume_path, locale, status
             FROM rateb_website_career_portal_users
             WHERE id = :id AND company_id = :cid AND status = :st LIMIT 1",
            ['id' => $uid, 'cid' => $cid, 'st' => 'active']
        );
    }

    public function isLoggedIn(): bool
    {
        return $this->currentUser() !== null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateProfile(int $userId, array $data): void
    {
        $user = $this->assertUser($userId);
        $patch = [];
        foreach (['full_name', 'phone', 'city', 'linkedin_url', 'portfolio_url'] as $k) {
            if (array_key_exists($k, $data)) {
                $v = trim((string) $data[$k]);
                $patch[$k] = $v !== '' ? $v : null;
            }
        }
        if (array_key_exists('nationality', $data)) {
            $patch['nationality'] = $this->countryCode($data['nationality']);
        }
        if (array_key_exists('country_code', $data)) {
            $patch['country_code'] = $this->countryCode($data['country_code']);
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
            'UPDATE rateb_website_career_portal_users SET ' . implode(', ', $sets)
            . ' WHERE id = :id AND company_id = :cid',
            $params
        );
    }

    public function updateResume(int $userId, int $mediaId, string $path): void
    {
        $user = $this->assertUser($userId);
        $this->repo->execute(
            'UPDATE rateb_website_career_portal_users
             SET resume_media_id = :mid, resume_path = :path
             WHERE id = :id AND company_id = :cid',
            ['mid' => $mediaId, 'path' => $path, 'id' => $userId, 'cid' => (int) $user['company_id']]
        );
    }

    /** @return array<string, mixed> */
    private function assertUser(int $userId): array
    {
        $row = $this->currentUser();
        if ($row === null || (int) ($row['id'] ?? 0) !== $userId) {
            throw new \RuntimeException('portal_user_required');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    private function findByEmail(string $email): ?array
    {
        if ($email === '') {
            return null;
        }
        [$where, $params] = $this->repo->companyWhere();
        $params['email'] = $email;

        return $this->repo->fetchOne(
            "SELECT * FROM rateb_website_career_portal_users WHERE {$where} AND email = :email LIMIT 1",
            $params
        );
    }

    private function loginById(int $userId): void
    {
        $row = $this->repo->fetchOne(
            'SELECT id, company_id FROM rateb_website_career_portal_users WHERE id = :id LIMIT 1',
            ['id' => $userId]
        );
        if ($row === null) {
            throw new \RuntimeException('portal_user_not_found');
        }
        $this->establishSession((int) $row['id']);
    }

    private function establishSession(int $userId): void
    {
        SessionManager::set(self::SESSION_USER, $userId);
        SessionManager::set(self::SESSION_COMPANY, $this->repo->companyId());
    }

    private function countryCode(mixed $v): ?string
    {
        $c = strtoupper(substr(trim((string) $v), 0, 2));

        return $c !== '' ? $c : null;
    }
}
