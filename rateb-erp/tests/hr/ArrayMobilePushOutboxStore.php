<?php
declare(strict_types=1);

/**
 * In-memory push outbox for Phase I.2 tests.
 */
final class ArrayMobilePushOutboxStore implements \Rateb\App\Services\MobilePushOutboxStoreInterface
{
    /** @var array<int, array<string,mixed>> */
    public array $rows = [];
    private int $seq = 1;

    public function insertIgnoreDuplicate(array $row): int
    {
        $nid = $row['notification_id'] ?? null;
        $app = (string) ($row['client_app'] ?? '');
        $uid = (int) ($row['user_id'] ?? 0);
        foreach ($this->rows as $existing) {
            if (($existing['notification_id'] ?? null) == $nid
                && (string) ($existing['client_app'] ?? '') === $app
                && (int) ($existing['user_id'] ?? 0) === $uid) {
                return 0;
            }
        }
        $id = $this->seq++;
        $row['id'] = $id;
        $row['status'] = $row['status'] ?? 'pending';
        $row['attempts'] = (int) ($row['attempts'] ?? 0);
        $row['last_error'] = $row['last_error'] ?? null;
        $row['sent_at'] = $row['sent_at'] ?? null;
        $row['created_at'] = $row['created_at'] ?? date('Y-m-d H:i:s');
        $this->rows[$id] = $row;

        return $id;
    }

    public function claimPending(int $limit): array
    {
        $claimed = [];
        foreach ($this->rows as $id => $row) {
            if (count($claimed) >= $limit) {
                break;
            }
            if ((string) ($row['status'] ?? '') !== 'pending') {
                continue;
            }
            $row['status'] = 'processing';
            $row['attempts'] = (int) ($row['attempts'] ?? 0) + 1;
            $this->rows[$id] = $row;
            $claimed[] = $row;
        }

        return $claimed;
    }

    public function update(int $id, array $patch): void
    {
        if (!isset($this->rows[$id])) {
            return;
        }
        $this->rows[$id] = array_merge($this->rows[$id], $patch);
    }

    public function findById(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function countByNotification(int $notificationId): int
    {
        $n = 0;
        foreach ($this->rows as $row) {
            if ((int) ($row['notification_id'] ?? 0) === $notificationId) {
                $n++;
            }
        }

        return $n;
    }
}
