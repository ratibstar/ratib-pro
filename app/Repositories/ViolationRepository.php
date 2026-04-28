<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Contracts\ViolationRepositoryInterface;
use App\Models\BaseModel;

final class ViolationRepository extends BaseModel implements ViolationRepositoryInterface
{
    public function create(array $data): int
    {
        $sql = 'INSERT INTO violations (worker_id, violation_type, severity, details, detected_at, created_at)
                VALUES (:worker_id, :violation_type, :severity, :details, :detected_at, NOW())';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':worker_id' => $data['worker_id'],
            ':violation_type' => $data['violation_type'],
            ':severity' => $data['severity'] ?? 'medium',
            ':details' => $data['details'] ?? null,
            ':detected_at' => $data['detected_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->lastInsertId();
    }
}
