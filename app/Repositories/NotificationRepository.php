<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Contracts\NotificationRepositoryInterface;
use App\Models\BaseModel;

final class NotificationRepository extends BaseModel implements NotificationRepositoryInterface
{
    public function create(array $data): int
    {
        $sql = 'INSERT INTO notifications (worker_id, channel, recipient, message, status, created_at)
                VALUES (:worker_id, :channel, :recipient, :message, :status, NOW())';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':worker_id' => $data['worker_id'],
            ':channel' => $data['channel'],
            ':recipient' => $data['recipient'],
            ':message' => $data['message'],
            ':status' => $data['status'] ?? 'queued',
        ]);
        return (int) $this->db->lastInsertId();
    }
}
