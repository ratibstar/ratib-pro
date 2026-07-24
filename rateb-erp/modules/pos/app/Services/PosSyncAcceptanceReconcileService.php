<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;

/**
 * Phase 13 — reconcile COMMITTING/FAILED acceptances with existing POS orders.
 * Never creates orders. Matching: sync_key ≡ order.idempotency_key.
 */
final class PosSyncAcceptanceReconcileService
{
    public const DEFAULT_TTL_SECONDS = 120;

    public function __construct(
        private PosSyncAcceptanceLifecycle $lifecycle = new PosSyncAcceptanceLifecycle(),
        private PosOrderIdempotencyLookup $orders = new PosOrderIdempotencyLookup(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    /**
     * @return array{ok: bool, reconciled: int, interrupted: int, scanned: int}
     */
    public function reconcileCompany(int $companyId, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): array
    {
        if ($companyId < 1) {
            return ['ok' => false, 'reconciled' => 0, 'interrupted' => 0, 'scanned' => 0];
        }

        $reconciled = 0;
        $interrupted = 0;
        $rows = array_merge(
            $this->lifecycle->listStaleCommitting($companyId, $ttlSeconds),
            $this->lifecycle->listFailedWithoutOrder($companyId)
        );
        $seen = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id < 1 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $syncKey = trim((string) ($row['sync_key'] ?? ''));
            if ($syncKey === '') {
                continue;
            }
            $order = $this->orders->findRaw($companyId, $syncKey, false);
            if ($order && (string) ($order['status'] ?? '') === 'completed') {
                if ($this->lifecycle->reconcileCommitted($companyId, $id, (int) $order['id'])) {
                    $reconciled++;
                    $this->audit->log('COMMIT_DUPLICATE', 'pos.sync_acceptance', $id, [
                        'sync_key' => $syncKey,
                        'order_id' => (int) $order['id'],
                        'reconcile' => true,
                    ]);
                }
                continue;
            }
            if ((string) ($row['status'] ?? '') === PosSyncAcceptanceLifecycle::COMMITTING) {
                if ($this->lifecycle->reconcileInterrupted(
                    $companyId,
                    $id,
                    'commit_interrupted: no order for sync_key'
                )) {
                    $interrupted++;
                    $this->audit->log('COMMIT_FAILED', 'pos.sync_acceptance', $id, [
                        'sync_key' => $syncKey,
                        'error_code' => 'commit_interrupted',
                        'reconcile' => true,
                    ]);
                }
            }
        }

        return [
            'ok' => true,
            'reconciled' => $reconciled,
            'interrupted' => $interrupted,
            'scanned' => count($seen),
        ];
    }
}
