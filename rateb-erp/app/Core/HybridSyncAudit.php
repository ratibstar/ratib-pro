<?php
declare(strict_types=1);

namespace Rateb\App\Core;

use PDO;

/** Phase C — sync audit log (device + batch timing/errors). */
final class HybridSyncAudit
{
    /** @param array<string, mixed> $detail */
    public static function log(PDO $pdo, string $event, string $batchUuid = '', array $detail = []): void
    {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO rateb_sync_audit (event, batch_uuid, detail_json, created_at)
                 VALUES (:e, :b, :d, :c)'
            );
            $stmt->execute([
                'e' => substr($event, 0, 120),
                'b' => substr($batchUuid, 0, 64),
                'd' => json_encode($detail, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                'c' => gmdate('c'),
            ]);
        } catch (\Throwable $e) {
            // never throw from audit
        }
    }
}
