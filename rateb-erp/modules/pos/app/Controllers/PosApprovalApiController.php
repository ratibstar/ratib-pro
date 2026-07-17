<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Pos\Services\PosSupervisorApprovalService;
use Rateb\App\Pos\Services\Bridge\PosInventoryBridgeService;
use Rateb\App\Services\BiometricAuthService;

final class PosApprovalApiController extends PosBaseController
{
    public function requestApproval(): void
    {
        $this->runPosJsonAction(function (): void {
            $this->bootstrapPos();
            $this->guardPosView('pos/register');
            $this->requireSessionCsrfOrAbort();
            $body = $this->jsonBody();
            $actionType = trim((string) ($body['action_type'] ?? ''));
            $payload = is_array($body['payload'] ?? null) ? $body['payload'] : [];
            if ($actionType === '') {
                $this->json(['ok' => false, 'error' => __('invalid_request')], 422);
                return;
            }

            $companyId = $this->companyId();
            if ($companyId < 1) {
                $this->json(['ok' => false, 'error' => __('invalid_request')], 422);
                return;
            }

            $session = (new PosSessionService())->snapshot();
            $requestId = (new PosSupervisorApprovalService())->createRequest(
                $companyId,
                $this->userId(),
                $actionType,
                $payload,
                isset($session['db_session_id']) ? (int) $session['db_session_id'] : null
            );

            $this->json(['ok' => true, 'approval_request_id' => $requestId]);
        }, 'approval-request');
    }

    public function grantApproval(): void
    {
        $this->runPosJsonAction(function (): void {
            $this->bootstrapPos();
            $this->guardPosView('pos/register');
            $this->requireSessionCsrfOrAbort();
            $body = $this->jsonBody();
            $requestId = (int) ($body['approval_request_id'] ?? 0);

            $bio = new BiometricAuthService();
            $finish = $bio->finishWebAuthn($body, 0, true);
            if (empty($finish['ok'])) {
                $this->json(['ok' => false, 'error' => (string) ($finish['error'] ?? __('pos_biometric_failed'))], 401);
                return;
            }

            $supervisorId = (int) ($finish['user_id'] ?? $this->userId());
            $token = (new PosSupervisorApprovalService())->grantRequest(
                $requestId,
                $supervisorId,
                $this->companyId()
            );
            if ($token === null) {
                $this->json(['ok' => false, 'error' => __('access_denied')], 403);
                return;
            }

            $this->json([
                'ok' => true,
                'approval_token' => $token,
                'expires_in' => 60,
            ]);
        }, 'approval-grant');
    }

    public function inventoryAdjust(): void
    {
        $this->runPosJsonAction(function (): void {
            $this->bootstrapPos();
            $this->guardPosPermission('pos.inventory.adjust', 'pos/register');
            $this->requireSessionCsrfOrAbort();

            $approval = new PosSupervisorApprovalService();
            $approval->requireApprovalOrAbort('stock_adjustment', $this->companyId());

            $body = $this->jsonBody();
            $inventoryId = (int) ($body['inventory_id'] ?? $body['product_id'] ?? 0);
            $qtyDelta = (float) ($body['quantity_delta'] ?? $body['delta'] ?? $body['qty'] ?? 0);
            $warehouseId = (int) ($body['warehouse_id'] ?? 0);
            $reason = trim((string) ($body['reason'] ?? ''));

            if ($inventoryId < 1 || abs($qtyDelta) < 0.0001) {
                $this->json(['ok' => false, 'error' => __('invalid_request')], 422);
                return;
            }

            $session = (new PosSessionService())->snapshot();
            if ($warehouseId < 1) {
                $warehouseId = (int) ($session['warehouse_id'] ?? 0);
            }

            $movementId = (new PosInventoryBridgeService())->recordMovement([
                'inventory_id' => $inventoryId,
                'warehouse_id' => $warehouseId > 0 ? $warehouseId : null,
                'movement_type' => 'adjustment',
                'quantity' => $qtyDelta,
                'notes' => $reason !== '' ? $reason : 'POS stock adjustment',
                'company_id' => $this->companyId(),
                'created_by' => $this->userId(),
            ]);
            $this->json(['ok' => true, 'movement_id' => $movementId]);
        }, 'approval-inventory-adjust');
    }
}
