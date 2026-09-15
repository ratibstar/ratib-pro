<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\Supplier;
use Rateb\App\Services\WorkflowSubmissionService;
use Rateb\App\Services\WorkflowService;
use Rateb\App\Core\Database;
use Rateb\App\Services\AuditService;

/**
 * Procurement Tool Executor
 * Delegates tool calls to existing Models/Services. No business logic here.
 */
final class ProcurementToolExecutor
{
    /**
     * @param array<string, mixed> $arguments
     * @return array{success: bool, data: mixed, error: string|null}
     */
    public static function execute(string $toolName, array $arguments, ProcurementAgentContext $ctx): array
    {
        $companyId = $ctx->companyId;

        try {
            switch ($toolName) {
                case 'list_purchase_requests':
                    return self::listPurchaseRequests($arguments, $companyId);
                case 'get_purchase_request':
                    return self::getPurchaseRequest($arguments, $companyId);
                case 'list_purchase_orders':
                    return self::listPurchaseOrders($arguments, $companyId);
                case 'search_suppliers':
                    return self::searchSuppliers($arguments, $companyId);
                case 'list_pending_approvals':
                    return self::listPendingApprovals($arguments, $companyId);
                case 'get_approval_detail':
                    return self::getApprovalDetail($arguments, $companyId);
                case 'create_draft_purchase_request':
                    return self::createDraftPurchaseRequest($arguments, $companyId, $ctx->userId);
                case 'submit_purchase_request':
                    return self::submitPurchaseRequest($arguments, $companyId, $ctx->userId);
                default:
                    return ['success' => false, 'data' => null, 'error' => "Tool not implemented: {$toolName}"];
            }
        } catch (\Throwable $e) {
            return ['success' => false, 'data' => null, 'error' => $e->getMessage()];
        }
    }

    private static function listPurchaseRequests(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $search = (string) ($args['search'] ?? '');
        $status = (string) ($args['status'] ?? '');
        $filters = [];
        if ($status !== '') {
            $filters['status'] = $status;
        }

        $model = new PurchaseRequest();
        $data = $model->all($limit, $offset, $filters, $search);
        return ['success' => true, 'data' => $data, 'error' => null];
    }

    private static function getPurchaseRequest(array $args, int $companyId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return ['success' => false, 'data' => null, 'error' => 'Invalid purchase request ID'];
        }

        $model = new PurchaseRequest();
        $pr = $model->find($id);

        if (!$pr) {
            return ['success' => false, 'data' => null, 'error' => 'Purchase request not found'];
        }

        if ((int) ($pr['company_id'] ?? 0) !== $companyId) {
            return ['success' => false, 'data' => null, 'error' => 'Purchase request not found'];
        }

        $lineItems = \Rateb\App\Helpers\LineItems::loadPurchaseRequestItems($id);
        $pr['line_items'] = $lineItems;

        return ['success' => true, 'data' => $pr, 'error' => null];
    }

    private static function listPurchaseOrders(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $search = (string) ($args['search'] ?? '');
        $status = (string) ($args['status'] ?? '');
        $filters = [];
        if ($status !== '') {
            $filters['status'] = $status;
        }

        $model = new PurchaseOrder();
        $data = $model->all($limit, $offset, $filters, $search);
        return ['success' => true, 'data' => $data, 'error' => null];
    }

    private static function searchSuppliers(array $args, int $companyId): array
    {
        $limit = max(1, min(100, (int) ($args['limit'] ?? 50)));
        $offset = max(0, (int) ($args['offset'] ?? 0));
        $search = (string) ($args['search'] ?? '');
        $status = (string) ($args['status'] ?? '');
        $filters = [];
        if ($status !== '') {
            $filters['status'] = $status;
        }

        $model = new Supplier();
        $data = $model->all($limit, $offset, $filters, $search);
        return ['success' => true, 'data' => $data, 'error' => null];
    }

    private static function listPendingApprovals(array $args, int $companyId): array
    {
        $limit = max(1, min(200, (int) ($args['limit'] ?? 50)));

        $workflowService = new WorkflowService();
        $allPending = $workflowService->listPending($companyId, $limit);

        $filtered = array_filter($allPending, function ($item) {
            $entityType = (string) ($item['entity_type'] ?? '');
            return $entityType === 'purchase_request' || $entityType === 'purchase_order';
        });

        return ['success' => true, 'data' => array_values($filtered), 'error' => null];
    }

    private static function getApprovalDetail(array $args, int $companyId): array
    {
        $instanceId = (int) ($args['instance_id'] ?? 0);
        if ($instanceId < 1) {
            return ['success' => false, 'data' => null, 'error' => 'Invalid approval instance ID'];
        }

        $workflowService = new WorkflowService();
        $history = $workflowService->history($instanceId);

        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT entity_type, entity_id FROM rateb_approval_instances WHERE id = :id AND company_id = :cid LIMIT 1'
        );
        $stmt->execute(['id' => $instanceId, 'cid' => $companyId]);
        $instance = $stmt->fetch();

        if (!$instance) {
            return ['success' => false, 'data' => null, 'error' => 'Approval instance not found'];
        }

        $entityType = (string) ($instance['entity_type'] ?? '');
        if ($entityType !== 'purchase_request' && $entityType !== 'purchase_order') {
            return ['success' => false, 'data' => null, 'error' => 'Approval instance not found'];
        }

        return [
            'success' => true,
            'data' => [
                'instance_id' => $instanceId,
                'entity_type' => $entityType,
                'entity_id' => (int) ($instance['entity_id'] ?? 0),
                'history' => $history,
            ],
            'error' => null,
        ];
    }

    private static function createDraftPurchaseRequest(array $args, int $companyId, int $userId): array
    {
        $model = new PurchaseRequest();

        $data = [
            'request_no' => $model->generateRequestNo(),
            'title' => trim((string) ($args['title'] ?? '')),
            'department' => trim((string) ($args['department'] ?? '')),
            'priority' => (string) ($args['priority'] ?? 'medium'),
            'expected_date' => (string) ($args['expected_date'] ?? ''),
            'currency' => (string) ($args['currency'] ?? 'SAR'),
            'total_estimated' => (float) ($args['total_estimated'] ?? 0),
            'notes' => trim((string) ($args['notes'] ?? '')),
            'status' => 'draft',
            'company_id' => $companyId,
        ];

        $branchId = function_exists('rateb_resolve_create_branch_id')
            ? (int) rateb_resolve_create_branch_id()
            : 0;
        if ($branchId > 0) {
            $data['branch_id'] = $branchId;
        }

        if ($data['title'] === '') {
            return ['success' => false, 'data' => null, 'error' => 'Title is required'];
        }

        $prId = $model->create($data);

        $lineItems = $args['line_items'] ?? [];
        if ($lineItems !== []) {
            \Rateb\App\Helpers\LineItems::syncPurchaseRequestItems($prId, $lineItems);
            $agg = \Rateb\App\Helpers\LineItems::aggregateTotals($lineItems);
            $model->update($prId, ['total_estimated' => $agg['total']]);
        }

        (new AuditService())->log('create', 'purchase_requests', $prId, $data);

        return ['success' => true, 'data' => ['id' => $prId, 'request_no' => $data['request_no']], 'error' => null];
    }

    private static function submitPurchaseRequest(array $args, int $companyId, int $userId): array
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id < 1) {
            return ['success' => false, 'data' => null, 'error' => 'Invalid purchase request ID'];
        }

        $model = new PurchaseRequest();
        $pr = $model->find($id);

        if (!$pr) {
            return ['success' => false, 'data' => null, 'error' => 'Purchase request not found'];
        }

        if ((int) ($pr['company_id'] ?? 0) !== $companyId) {
            return ['success' => false, 'data' => null, 'error' => 'Purchase request not found'];
        }

        $currentStatus = (string) ($pr['status'] ?? '');
        if ($currentStatus !== 'draft') {
            return ['success' => false, 'data' => null, 'error' => 'Only draft purchase requests can be submitted'];
        }

        $oldStatus = $currentStatus;
        $model->update($id, ['status' => 'submitted']);

        (new WorkflowSubmissionService())->handlePurchaseRequestStatus($id, 'submitted', $oldStatus);

        (new AuditService())->log('submit', 'purchase_requests', $id, ['status' => 'submitted']);

        return ['success' => true, 'data' => ['id' => $id, 'status' => 'submitted'], 'error' => null];
    }
}