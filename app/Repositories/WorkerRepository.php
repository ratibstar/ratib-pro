<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Contracts\WorkerRepositoryInterface;
use App\Models\BaseModel;

final class WorkerRepository extends BaseModel implements WorkerRepositoryInterface
{
    public function create(array $data): int
    {
        $sql = 'INSERT INTO workers (name, passport_number, employer_id, status, created_at, updated_at)
                VALUES (:name, :passport_number, :employer_id, :status, NOW(), NOW())';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $data['name'],
            ':passport_number' => $data['passport_number'],
            ':employer_id' => $data['employer_id'] ?? null,
            ':status' => $data['status'] ?? 'active',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM workers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
