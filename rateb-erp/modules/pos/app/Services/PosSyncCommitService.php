<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;
use Rateb\App\Pos\Services\Bridge\PosAuditBridgeService;
use Throwable;

/**
 * Phase 13 — thin commit orchestrator.
 * Business transaction owner: PosCheckoutService::complete() only.
 * Never wraps complete() in an outer DB transaction.
 */
class PosSyncCommitService
{
    public function __construct(
        private PosSyncAcceptanceLifecycle $lifecycle = new PosSyncAcceptanceLifecycle(),
        private PosSyncAcceptanceMapper $mapper = new PosSyncAcceptanceMapper(),
        private PosSyncValidateService $validator = new PosSyncValidateService(),
        private PosCheckoutCompletePort $checkout = new PosCheckoutService(),
        private PosOrderIdempotencyLookup $orders = new PosOrderIdempotencyLookup(),
        private PosAuditBridgeService $audit = new PosAuditBridgeService(),
        private PosTaxSettingsService $tax = new PosTaxSettingsService(),
    ) {
    }

    public function isAvailable(): bool
    {
        return Database::liveTableHasColumn('rateb_pos_sync_acceptances', 'commit_token');
    }

    /**
     * @param array<string, mixed> $auth company_id, user_id, branch_id?
     * @return array<string, mixed>
     */
    public function commit(int $companyId, array $selector, array $auth): array
    {
        $started = hrtime(true);
        if (!$this->isAvailable()) {
            return $this->failResponse('migration_required', 'Commit columns unavailable', 503, $started);
        }
        if ($companyId < 1) {
            return $this->failResponse('missing_company_id', 'company_id required', 403, $started);
        }

        $row = $this->resolveRow($companyId, $selector);
        if ($row === null) {
            return $this->failResponse('not_found', 'Acceptance not found', 404, $started);
        }

        if ((string) ($row['status'] ?? '') === PosSyncAcceptanceLifecycle::COMMITTED) {
            $this->audit->log('COMMIT_DUPLICATE', 'pos.sync_acceptance', (int) $row['id'], [
                'sync_key' => $row['sync_key'] ?? null,
                'server_sync_id' => $row['server_sync_id'] ?? null,
                'order_id' => $row['order_id'] ?? null,
                'already_committed' => true,
            ]);

            return [
                'ok' => true,
                'accepted' => true,
                'already_committed' => true,
                'server_sync_id' => $row['server_sync_id'] ?? null,
                'sync_key' => $row['sync_key'] ?? null,
                'order_id' => $row['order_id'] ?? null,
                'status' => PosSyncAcceptanceLifecycle::COMMITTED,
                'processing_ms' => $this->elapsedMs($started),
                'http_status' => 200,
            ];
        }

        /* Pre-check: order already exists for sync_key → reconcile without double checkout. */
        $syncKey = trim((string) ($row['sync_key'] ?? ''));
        if ($syncKey !== '') {
            $existingOrder = $this->orders->findRaw($companyId, $syncKey, false);
            if ($existingOrder && (string) ($existingOrder['status'] ?? '') === 'completed') {
                $this->lifecycle->reconcileCommitted($companyId, (int) $row['id'], (int) $existingOrder['id']);
                $this->audit->log('COMMIT_DUPLICATE', 'pos.sync_acceptance', (int) $row['id'], [
                    'sync_key' => $syncKey,
                    'server_sync_id' => $row['server_sync_id'] ?? null,
                    'order_id' => (int) $existingOrder['id'],
                    'reconciled' => true,
                ]);

                return [
                    'ok' => true,
                    'accepted' => true,
                    'already_committed' => true,
                    'server_sync_id' => $row['server_sync_id'] ?? null,
                    'sync_key' => $syncKey,
                    'order_id' => (int) $existingOrder['id'],
                    'status' => PosSyncAcceptanceLifecycle::COMMITTED,
                    'processing_ms' => $this->elapsedMs($started),
                    'http_status' => 200,
                ];
            }
        }

        $claim = $this->lifecycle->claim($companyId, (int) $row['id']);
        if (!$claim['ok']) {
            if (!empty($claim['already_committed'])) {
                $this->audit->log('COMMIT_DUPLICATE', 'pos.sync_acceptance', (int) $row['id'], [
                    'sync_key' => $syncKey,
                    'server_sync_id' => $row['server_sync_id'] ?? null,
                ]);

                return [
                    'ok' => true,
                    'accepted' => true,
                    'already_committed' => true,
                    'server_sync_id' => $claim['row']['server_sync_id'] ?? $row['server_sync_id'] ?? null,
                    'sync_key' => $syncKey,
                    'order_id' => $claim['row']['order_id'] ?? null,
                    'status' => PosSyncAcceptanceLifecycle::COMMITTED,
                    'processing_ms' => $this->elapsedMs($started),
                    'http_status' => 200,
                ];
            }
            if (!empty($claim['in_progress'])) {
                return $this->failResponse('in_progress', 'Commit already in progress', 409, $started, [
                    'server_sync_id' => $row['server_sync_id'] ?? null,
                    'sync_key' => $syncKey,
                ]);
            }

            return $this->failResponse('claim_failed', (string) ($claim['reason'] ?? 'claim_failed'), 409, $started);
        }

        $claimed = $claim['row'];
        $commitToken = (string) ($claim['commit_token'] ?? '');
        $acceptanceId = (int) $claimed['id'];
        $retryCount = (int) ($claimed['retry_count'] ?? 1);

        if (!empty($claim['retried'])) {
            $this->audit->log('COMMIT_RETRY', 'pos.sync_acceptance', $acceptanceId, [
                'sync_key' => $syncKey,
                'server_sync_id' => $claimed['server_sync_id'] ?? null,
                'retry_count' => $retryCount,
            ]);
        }

        $this->audit->log('COMMIT_STARTED', 'pos.sync_acceptance', $acceptanceId, [
            'sync_key' => $syncKey,
            'server_sync_id' => $claimed['server_sync_id'] ?? null,
            'retry_count' => $retryCount,
        ]);

        $payload = $this->decodePayload($claimed['payload'] ?? null);
        $validation = $this->validator->validate($payload, ['company_id' => $companyId]);
        if (($validation['accepted'] ?? false) !== true) {
            $msg = 'validation_failed';
            $this->lifecycle->markFailed($companyId, $acceptanceId, $commitToken, [
                'last_error' => $msg,
                'error_code' => 'validation_failed',
                'processing_ms' => $this->elapsedMs($started),
            ]);
            $this->audit->log('COMMIT_FAILED', 'pos.sync_acceptance', $acceptanceId, [
                'sync_key' => $syncKey,
                'server_sync_id' => $claimed['server_sync_id'] ?? null,
                'retry_count' => $retryCount,
                'error_code' => 'validation_failed',
                'processing_ms' => $this->elapsedMs($started),
            ]);

            return $this->failResponse('validation_failed', $msg, 422, $started, [
                'conflicts' => $validation['conflicts'] ?? [],
                'server_sync_id' => $claimed['server_sync_id'] ?? null,
                'sync_key' => $syncKey,
            ]);
        }

        try {
            $mapped = $this->mapper->map($payload, [
                'company_id' => $companyId,
                'user_id' => (int) ($auth['user_id'] ?? 0),
            ]);
        } catch (Throwable $e) {
            $code = 'map_failed';
            $this->lifecycle->markFailed($companyId, $acceptanceId, $commitToken, [
                'last_error' => $e->getMessage(),
                'error_code' => $code,
                'processing_ms' => $this->elapsedMs($started),
            ]);
            $this->audit->log('COMMIT_FAILED', 'pos.sync_acceptance', $acceptanceId, [
                'sync_key' => $syncKey,
                'server_sync_id' => $claimed['server_sync_id'] ?? null,
                'retry_count' => $retryCount,
                'error_code' => $code,
                'error' => $e->getMessage(),
                'processing_ms' => $this->elapsedMs($started),
            ]);

            return $this->failResponse($code, $e->getMessage(), 422, $started, [
                'server_sync_id' => $claimed['server_sync_id'] ?? null,
                'sync_key' => $syncKey,
            ]);
        }

        /* Tenant / branch isolation */
        $authBranch = (int) ($auth['branch_id'] ?? 0);
        $mappedBranch = (int) ($mapped['scope']['branch_id'] ?? 0);
        if ($authBranch > 0 && $mappedBranch > 0 && $authBranch !== $mappedBranch) {
            $this->lifecycle->markFailed($companyId, $acceptanceId, $commitToken, [
                'last_error' => 'branch_mismatch',
                'error_code' => 'branch_isolation',
                'processing_ms' => $this->elapsedMs($started),
            ]);
            $this->audit->log('COMMIT_FAILED', 'pos.sync_acceptance', $acceptanceId, [
                'sync_key' => $syncKey,
                'error_code' => 'branch_isolation',
                'retry_count' => $retryCount,
                'processing_ms' => $this->elapsedMs($started),
            ]);

            return $this->failResponse('branch_isolation', 'Branch mismatch', 403, $started);
        }

        $deviceExpected = trim((string) ($auth['device_id'] ?? ''));
        $deviceActual = trim((string) ($payload['device_id'] ?? ''));
        if ($deviceExpected !== '' && $deviceActual !== '' && $deviceExpected !== $deviceActual) {
            $this->lifecycle->markFailed($companyId, $acceptanceId, $commitToken, [
                'last_error' => 'device_mismatch',
                'error_code' => 'device_isolation',
                'processing_ms' => $this->elapsedMs($started),
            ]);
            $this->audit->log('COMMIT_FAILED', 'pos.sync_acceptance', $acceptanceId, [
                'sync_key' => $syncKey,
                'error_code' => 'device_isolation',
                'retry_count' => $retryCount,
                'processing_ms' => $this->elapsedMs($started),
            ]);

            return $this->failResponse('device_isolation', 'Device mismatch', 403, $started);
        }

        $taxRate = $this->tax->resolveRate($companyId, (int) $mapped['scope']['branch_id']);

        try {
            /* Own transaction inside complete() — do not wrap. */
            $result = $this->checkout->complete(
                $mapped['lines'],
                $mapped['payments'],
                $mapped['invoice_discount'],
                $mapped['scope'],
                $mapped['customer'],
                $taxRate,
                false
            );
        } catch (Throwable $e) {
            $this->lifecycle->markFailed($companyId, $acceptanceId, $commitToken, [
                'last_error' => $e->getMessage(),
                'error_code' => 'checkout_failed',
                'processing_ms' => $this->elapsedMs($started),
            ]);
            $this->audit->log('COMMIT_FAILED', 'pos.sync_acceptance', $acceptanceId, [
                'sync_key' => $syncKey,
                'server_sync_id' => $claimed['server_sync_id'] ?? null,
                'retry_count' => $retryCount,
                'error_code' => 'checkout_failed',
                'error' => $e->getMessage(),
                'processing_ms' => $this->elapsedMs($started),
            ]);

            return $this->failResponse('checkout_failed', $e->getMessage(), 422, $started, [
                'server_sync_id' => $claimed['server_sync_id'] ?? null,
                'sync_key' => $syncKey,
            ]);
        }

        $orderId = (int) ($result['order_id'] ?? 0);
        $ms = $this->elapsedMs($started);
        if ($orderId < 1) {
            $this->lifecycle->markFailed($companyId, $acceptanceId, $commitToken, [
                'last_error' => 'checkout returned no order_id',
                'error_code' => 'missing_order_id',
                'processing_ms' => $ms,
            ]);
            $this->audit->log('COMMIT_FAILED', 'pos.sync_acceptance', $acceptanceId, [
                'sync_key' => $syncKey,
                'server_sync_id' => $claimed['server_sync_id'] ?? null,
                'retry_count' => $retryCount,
                'error_code' => 'missing_order_id',
                'processing_ms' => $ms,
            ]);

            return $this->failResponse('missing_order_id', 'Checkout returned no order_id', 500, $started, [
                'server_sync_id' => $claimed['server_sync_id'] ?? null,
                'sync_key' => $syncKey,
            ]);
        }

        $this->lifecycle->markCommitted($companyId, $acceptanceId, $commitToken, [
            'order_id' => $orderId,
            'processing_ms' => $ms,
        ]);
        $this->audit->log('COMMIT_SUCCESS', 'pos.sync_acceptance', $acceptanceId, [
            'sync_key' => $syncKey,
            'server_sync_id' => $claimed['server_sync_id'] ?? null,
            'retry_count' => $retryCount,
            'order_id' => $orderId,
            'processing_ms' => $ms,
            'idempotent' => !empty($result['idempotent']),
            'payment_policy' => $mapped['payment_policy'],
        ]);

        return [
            'ok' => true,
            'accepted' => true,
            'already_committed' => !empty($result['idempotent']),
            'server_sync_id' => $claimed['server_sync_id'] ?? null,
            'sync_key' => $syncKey,
            'order_id' => $orderId,
            'order_no' => $result['order_no'] ?? null,
            'status' => PosSyncAcceptanceLifecycle::COMMITTED,
            'payment_policy' => $mapped['payment_policy'],
            'processing_ms' => $ms,
            'http_status' => 200,
        ];
    }

    /**
     * @param array<string, mixed> $selector
     * @return array<string, mixed>|null
     */
    private function resolveRow(int $companyId, array $selector): ?array
    {
        if (!empty($selector['id'])) {
            return $this->lifecycle->fetchById($companyId, (int) $selector['id']);
        }
        if (!empty($selector['server_sync_id'])) {
            return $this->lifecycle->fetchByServerSyncId($companyId, (string) $selector['server_sync_id']);
        }
        if (!empty($selector['sync_key'])) {
            return $this->lifecycle->fetchBySyncKey($companyId, (string) $selector['sync_key']);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function decodePayload(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function failResponse(
        string $code,
        string $message,
        int $http,
        int|float $started,
        array $extra = []
    ): array {
        return array_merge([
            'ok' => false,
            'accepted' => false,
            'error_code' => $code,
            'error' => $message,
            'processing_ms' => $this->elapsedMs($started),
            'http_status' => $http,
        ], $extra);
    }

    private function elapsedMs(int|float $started): float
    {
        return round((hrtime(true) - $started) / 1_000_000, 2);
    }
}
