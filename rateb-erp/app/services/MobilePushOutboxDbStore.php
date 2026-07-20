<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\MobilePushOutbox;
use PDO;

final class MobilePushOutboxDbStore implements MobilePushOutboxStoreInterface
{
    public function insertIgnoreDuplicate(array $row): int
    {
        $db = \Rateb\App\Core\Database::connection();
        $sql = 'INSERT IGNORE INTO rateb_mobile_push_outbox
            (company_id, user_id, client_app, notification_id, title, body, data_json, status, attempts, created_at)
            VALUES
            (:cid, :uid, :app, :nid, :title, :body, :data, :st, 0, NOW())';
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'cid' => (int) ($row['company_id'] ?? 0),
            'uid' => (int) ($row['user_id'] ?? 0),
            'app' => (string) ($row['client_app'] ?? ''),
            'nid' => $row['notification_id'] ?? null,
            'title' => (string) ($row['title'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'data' => $row['data_json'] ?? null,
            'st' => (string) ($row['status'] ?? 'pending'),
        ]);
        if ($stmt->rowCount() < 1) {
            return 0;
        }

        return (int) $db->lastInsertId();
    }

    public function claimPending(int $limit): array
    {
        $db = \Rateb\App\Core\Database::connection();
        $safe = max(1, min(100, $limit));
        $stmt = $db->prepare(
            'SELECT id, company_id, user_id, client_app, notification_id, title, body, data_json,
                    status, attempts, last_error, created_at, sent_at
             FROM rateb_mobile_push_outbox
             WHERE status = :st
             ORDER BY id ASC
             LIMIT ' . $safe
        );
        $stmt->execute(['st' => 'pending']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $claimed = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $upd = $db->prepare(
                'UPDATE rateb_mobile_push_outbox
                 SET status = :proc, attempts = attempts + 1
                 WHERE id = :id AND status = :pending'
            );
            $upd->execute([
                'proc' => 'processing',
                'id' => $id,
                'pending' => 'pending',
            ]);
            if ($upd->rowCount() === 1) {
                $row['status'] = 'processing';
                $row['attempts'] = (int) ($row['attempts'] ?? 0) + 1;
                $claimed[] = $row;
            }
        }

        return $claimed;
    }

    public function update(int $id, array $patch): void
    {
        (new MobilePushOutbox())->update($id, $patch);
    }

    public function findById(int $id): ?array
    {
        return (new MobilePushOutbox())->queryOne(
            'SELECT id, company_id, user_id, client_app, notification_id, title, body, data_json,
                    status, attempts, last_error, created_at, sent_at
             FROM rateb_mobile_push_outbox WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public function countByNotification(int $notificationId): int
    {
        $row = (new MobilePushOutbox())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_mobile_push_outbox WHERE notification_id = :nid',
            ['nid' => $notificationId]
        );

        return (int) ($row['c'] ?? 0);
    }
}
