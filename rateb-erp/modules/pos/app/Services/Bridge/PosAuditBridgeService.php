<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Services\AuditService;

/** Audit bridge — all POS mutations logged via ERP AuditService. */
final class PosAuditBridgeService
{
    public function log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $payload = null): void
    {
        (new AuditService())->log($action, $entityType, $entityId, $payload);
    }
}
