<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Domain\Contracts\WorkerRepositoryInterface;
use App\Models\BaseModel;
use InvalidArgumentException;
use PDO;

final class WorkerRepository extends BaseModel implements WorkerRepositoryInterface
{
    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? $data['worker_name'] ?? $data['full_name'] ?? ''));
        $passport = trim((string) ($data['passport_number'] ?? ''));
        if ($name === '' || $passport === '') {
            throw new InvalidArgumentException('Worker name and passport number are required.');
        }

        $nameColumn = $this->resolveWorkerNameColumn();
        $sql = "INSERT INTO workers ({$nameColumn}, passport_number, status, created_at, updated_at)
                VALUES (:name, :passport_number, :status, NOW(), NOW())";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':passport_number' => $passport,
            ':status' => $data['status'] ?? 'active',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM workers WHERE id = :id AND COALESCE(status, \'\') <> \'deleted\' LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? self::normalizeWorkerRow($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeWorkerRow(array $row): array
    {
        $name = trim((string) ($row['worker_name'] ?? $row['full_name'] ?? $row['name'] ?? ''));
        if ($name !== '') {
            $row['name'] = $name;
        }
        if (!isset($row['id']) && isset($row['ID'])) {
            $row['id'] = $row['ID'];
        }

        return $row;
    }

    private function resolveWorkerNameColumn(): string
    {
        static $column = null;
        if ($column !== null) {
            return $column;
        }
        $stmt = $this->db->query('SHOW COLUMNS FROM workers');
        $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        if (in_array('worker_name', $cols, true)) {
            $column = 'worker_name';
        } elseif (in_array('full_name', $cols, true)) {
            $column = 'full_name';
        } else {
            $column = 'name';
        }

        return $column;
    }
}
