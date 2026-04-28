<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Contracts\EmployerRepositoryInterface;
use App\Models\BaseModel;

final class EmployerRepository extends BaseModel implements EmployerRepositoryInterface
{
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM employers WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
