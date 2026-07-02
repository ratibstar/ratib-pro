<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class User extends Model
{
    protected string $table = 'rateb_users';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'name', 'email', 'password', 'phone', 'avatar_path',
        'is_super_admin', 'status', 'two_factor_secret', 'two_factor_enabled', 'locale', 'login_barcode',
    ];

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM rateb_users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM rateb_users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Email or short username (e.g. admin → admin@local). */
    public function findByLogin(string $login): ?array
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }
        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return $this->findByEmail($login);
        }
        $localEmail = strtolower($login) . '@local';
        $stmt = $this->db->prepare(
            'SELECT * FROM rateb_users WHERE email = :local OR name = :name OR email = :login LIMIT 1'
        );
        $stmt->execute(['local' => $localEmail, 'name' => $login, 'login' => $login]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updateLastLogin(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE rateb_users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function forCompany(int $companyId, int $limit = 100, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM rateb_users WHERE company_id = :cid ORDER BY id DESC LIMIT :lim OFFSET :off'
        );
        $stmt->bindValue(':cid', $companyId, \PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
