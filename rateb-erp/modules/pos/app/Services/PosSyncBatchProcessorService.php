<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosSyncQueueItem;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;

/** Processes pending rows in rateb_pos_sync_queue (Phase 3–4 batch worker). */
final class PosSyncBatchProcessorService
{
    private const MAX_RETRIES = 5;

    private const DEFERRED_ACTIONS = ['complete_sale', 'checkout', 'process_return', 'process_exchange'];

    public function __construct(
        private PosSyncQueueItem $model = new PosSyncQueueItem(),
        private PosSyncQueueService $queue = new PosSyncQueueService(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
    ) {
    }

    /** @return array<string, int> */
    public function processPending(?int $companyId = null, int $limit = 50): array
    {
        if (!$this->queue->isAvailable()) {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'conflicts' => 0, 'skipped' => 0];
        }

        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId < 1) {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0, 'conflicts' => 0, 'skipped' => 0];
        }

        $safeLimit = max(1, min(50, $limit));
        $rows = $this->model->query(
            'SELECT * FROM rateb_pos_sync_queue
             WHERE company_id = :cid AND status IN (:pending, :failed) AND retry_count < :max_retry
             ORDER BY created_at ASC, id ASC
             LIMIT ' . $safeLimit,
            [
                'cid' => $companyId,
                'pending' => 'pending',
                'failed' => 'failed',
                'max_retry' => self::MAX_RETRIES,
            ]
        );

        $stats = ['processed' => 0, 'synced' => 0, 'failed' => 0, 'conflicts' => 0, 'skipped' => 0];
        foreach ($rows as $row) {
            $result = $this->processRow($row);
            $stats['processed']++;
            $status = (string) ($result['status'] ?? 'skipped');
            if (isset($stats[$status])) {
                $stats[$status]++;
            } else {
                $stats['skipped']++;
            }
        }

        if ($stats['synced'] > 0) {
            $this->audit->log('pos.sync.batch_processed', 'pos_sync_queue', null, $stats);
        }

        return $stats;
    }

    /** @param array<string, mixed> $row @return array{status: string, error?: string} */
    private function processRow(array $row): array
    {
        $queueId = (int) ($row['id'] ?? 0);
        $decoded = $this->decodePayload($row);
        $action = (string) ($decoded['action'] ?? 'unknown');

        if (!in_array($action, self::DEFERRED_ACTIONS, true)) {
            $this->markSynced($queueId);

            return ['status' => 'synced'];
        }

        return match ($action) {
            'checkout', 'complete_sale' => $this->processCheckout($row, $decoded),
            'process_return' => $this->processReturn($row, $decoded),
            'process_exchange' => $this->processExchange($row, $decoded),
            default => $this->markSynced($queueId),
        };
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $decoded */
    private function processCheckout(array $row, array $decoded): array
    {
        $queueId = (int) ($row['id'] ?? 0);
        $retryCount = (int) ($row['retry_count'] ?? 0);
        $inner = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        $scope = $this->buildScope($row, $inner);

        if ($scope['company_id'] < 1 || $scope['branch_id'] < 1 || $scope['user_id'] < 1) {
            return $this->markFailed($queueId, $retryCount, 'missing_register_scope');
        }

        $lines = is_array($inner['lines'] ?? null) ? $inner['lines'] : [];
        $payments = is_array($inner['payments'] ?? null) ? $inner['payments'] : [];
        $invoiceDiscount = is_array($inner['invoice_discount'] ?? null) ? $inner['invoice_discount'] : [];
        $customer = is_array($inner['customer'] ?? null) ? $inner['customer'] : null;
        $taxRate = (float) ($inner['tax_rate'] ?? 0.15);

        if ($lines === [] || $payments === []) {
            return $this->markFailed($queueId, $retryCount, 'empty_checkout_payload');
        }

        try {
            TenantContext::setCompanyId($scope['company_id']);
            (new PosCheckoutService())->complete(
                $lines,
                $payments,
                $invoiceDiscount,
                $scope,
                $customer,
                $taxRate,
                (bool) $scope['gift_receipt']
            );
            $this->markSynced($queueId);

            return ['status' => 'synced'];
        } catch (\Throwable $e) {
            return $this->markFailed($queueId, $retryCount, $e->getMessage());
        }
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $decoded */
    private function processReturn(array $row, array $decoded): array
    {
        $queueId = (int) ($row['id'] ?? 0);
        $retryCount = (int) ($row['retry_count'] ?? 0);
        $inner = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        $scope = $this->buildScope($row, $inner);

        if ($scope['company_id'] < 1 || $scope['branch_id'] < 1 || $scope['user_id'] < 1) {
            return $this->markFailed($queueId, $retryCount, 'missing_register_scope');
        }

        $returnLines = is_array($inner['return_lines'] ?? null) ? $inner['return_lines'] : [];
        $refunds = is_array($inner['refunds'] ?? null) ? $inner['refunds'] : [];
        $originalOrderId = (int) ($inner['original_order_id'] ?? 0);
        $customer = is_array($inner['customer'] ?? null) ? $inner['customer'] : null;

        if ($originalOrderId < 1 || $returnLines === []) {
            return $this->markFailed($queueId, $retryCount, 'empty_return_payload');
        }

        try {
            TenantContext::setCompanyId($scope['company_id']);
            (new PosReturnService())->process($originalOrderId, $returnLines, $refunds, $scope, $customer);
            $this->markSynced($queueId);

            return ['status' => 'synced'];
        } catch (\Throwable $e) {
            return $this->markFailed($queueId, $retryCount, $e->getMessage());
        }
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $decoded */
    private function processExchange(array $row, array $decoded): array
    {
        $queueId = (int) ($row['id'] ?? 0);
        $retryCount = (int) ($row['retry_count'] ?? 0);
        $inner = is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [];
        $scope = $this->buildScope($row, $inner);

        if ($scope['company_id'] < 1 || $scope['branch_id'] < 1 || $scope['user_id'] < 1) {
            return $this->markFailed($queueId, $retryCount, 'missing_register_scope');
        }

        $returnLines = is_array($inner['return_lines'] ?? null) ? $inner['return_lines'] : [];
        $saleLines = is_array($inner['sale_lines'] ?? null) ? $inner['sale_lines'] : [];
        $payments = is_array($inner['payments'] ?? null) ? $inner['payments'] : [];
        $refunds = is_array($inner['refunds'] ?? null) ? $inner['refunds'] : [];
        $invoiceDiscount = is_array($inner['invoice_discount'] ?? null) ? $inner['invoice_discount'] : [];
        $customer = is_array($inner['customer'] ?? null) ? $inner['customer'] : null;
        $originalOrderId = (int) ($inner['original_order_id'] ?? 0);

        if ($originalOrderId < 1 || $returnLines === [] || $saleLines === []) {
            return $this->markFailed($queueId, $retryCount, 'empty_exchange_payload');
        }

        $scope['coupon_code'] = trim((string) ($inner['coupon_code'] ?? ''));
        $scope['points_redeem'] = (float) ($inner['points_redeem'] ?? 0);

        try {
            TenantContext::setCompanyId($scope['company_id']);
            (new PosExchangeService())->processExchange(
                $originalOrderId,
                $returnLines,
                $saleLines,
                $payments,
                $refunds,
                $scope,
                $customer,
                $invoiceDiscount
            );
            $this->markSynced($queueId);

            return ['status' => 'synced'];
        } catch (\Throwable $e) {
            return $this->markFailed($queueId, $retryCount, $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $inner
     * @return array<string, mixed>
     */
    private function buildScope(array $row, array $inner): array
    {
        $scopeMeta = is_array($inner['scope'] ?? null) ? $inner['scope'] : [];

        return [
            'company_id' => (int) ($row['company_id'] ?? 0),
            'branch_id' => (int) ($row['branch_id'] ?? $scopeMeta['branch_id'] ?? 0),
            'terminal_id' => (int) ($row['terminal_id'] ?? $scopeMeta['terminal_id'] ?? 0) ?: null,
            'shift_id' => (int) ($scopeMeta['shift_id'] ?? 0) ?: null,
            'warehouse_id' => isset($scopeMeta['warehouse_id']) ? (int) $scopeMeta['warehouse_id'] : null,
            'session_id' => isset($scopeMeta['session_id']) ? (int) $scopeMeta['session_id'] : null,
            'user_id' => (int) ($scopeMeta['user_id'] ?? 0),
            'idempotency_key' => (string) ($row['idempotency_key'] ?? ''),
            'coupon_code' => trim((string) ($inner['coupon_code'] ?? '')),
            'points_redeem' => (float) ($inner['points_redeem'] ?? 0),
            'gift_receipt' => !empty($inner['gift_receipt']),
        ];
    }

    /** @return array{status: string, error?: string} */
    private function markFailed(int $queueId, int $retryCount, string $message): array
    {
        $nextRetry = $retryCount + 1;
        $this->model->update($queueId, [
            'status' => $nextRetry >= self::MAX_RETRIES ? 'failed' : 'pending',
            'retry_count' => $nextRetry,
            'last_error' => mb_substr($message, 0, 2000),
        ]);

        return ['status' => 'failed', 'error' => $message];
    }

    private function markSynced(int $queueId): void
    {
        $this->model->update($queueId, [
            'status' => 'synced',
            'synced_at' => date('Y-m-d H:i:s'),
            'last_error' => null,
        ]);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function decodePayload(array $row): array
    {
        $raw = $row['payload'] ?? '';
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function resolveCompanyId(?int $companyId): int
    {
        if ($companyId !== null && $companyId > 0) {
            return $companyId;
        }

        return (int) (TenantContext::companyId() ?? 0);
    }
}
