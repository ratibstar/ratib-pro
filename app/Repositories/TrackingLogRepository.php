<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Contracts\TrackingLogRepositoryInterface;
use App\Models\BaseModel;

final class TrackingLogRepository extends BaseModel implements TrackingLogRepositoryInterface
{
    public function create(array $data): int
    {
        $sql = 'INSERT INTO tracking_logs (worker_id, latitude, longitude, location_name, moved_at, created_at)
                VALUES (:worker_id, :latitude, :longitude, :location_name, :moved_at, NOW())';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':worker_id' => $data['worker_id'],
            ':latitude' => $data['latitude'],
            ':longitude' => $data['longitude'],
            ':location_name' => $data['location_name'] ?? null,
            ':moved_at' => $data['moved_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->lastInsertId();
    }
}
