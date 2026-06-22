<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Application\Services\Recordings;

use Ratib\ContactCenter\App\Core\Database;

final class RecordingIndexer
{
    public function indexPending(int $tenantId, int $limit = 100): int
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, file_path FROM rcc_recordings WHERE tenant_id = :tid AND indexed_at IS NULL LIMIT ' . max(1, min(500, $limit))
        );
        $stmt->execute(['tid' => $tenantId]);
        $count = 0;
        foreach ($stmt->fetchAll() ?: [] as $row) {
            if (!is_file((string) $row['file_path'])) {
                continue;
            }
            Database::connection()->prepare(
                'UPDATE rcc_recordings SET indexed_at = NOW() WHERE tenant_id = :tid AND id = :id'
            )->execute(['tid' => $tenantId, 'id' => (int) $row['id']]);
            $count++;
        }
        return $count;
    }
}
