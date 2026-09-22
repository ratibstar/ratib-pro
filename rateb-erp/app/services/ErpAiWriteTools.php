<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Database;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\Rfq;
use Rateb\App\Models\SupplierQuotation;
use Rateb\App\Models\Inventory;

/**
 * Shared confirmed-write helpers for RATEB AI across ERP domains.
 * Always tenant-scoped; never creates login credentials or posts ledger entries.
 */
final class ErpAiWriteTools
{
    /**
     * @param array<string, mixed> $args
     * @return array{success: bool, data?: mixed, error?: string|null, error_code?: string|null}
     */
    public static function createDraftPurchaseOrder(array $args, int $companyId, int $userId): array
    {
        $notes = trim((string) ($args['notes'] ?? $args['title'] ?? ''));
        $model = new PurchaseOrder();
        $data = [
            'company_id' => $companyId,
            'order_no' => $model->generateOrderNo(),
            'order_date' => date('Y-m-d'),
            'status' => 'draft',
            'total_amount' => max(0, (float) ($args['total_amount'] ?? 0)),
            'currency' => trim((string) ($args['currency'] ?? 'SAR')) ?: 'SAR',
            'notes' => $notes !== '' ? mb_substr($notes, 0, 500) : 'AI draft PO',
        ];
        $supplierId = (int) ($args['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $data['supplier_id'] = $supplierId;
        }
        $branchId = self::branchId();
        if ($branchId > 0) {
            $data['branch_id'] = $branchId;
        }
        $id = (int) $model->create($data);
        if ($id < 1) {
            return self::fail('create_failed');
        }
        return self::ok(['id' => $id, 'order_no' => $data['order_no'], 'status' => 'draft']);
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function createDraftRfq(array $args, int $companyId, int $userId): array
    {
        $title = trim((string) ($args['title'] ?? $args['name'] ?? ''));
        if ($title === '') {
            return self::fail('title_required');
        }
        $model = new Rfq();
        $no = 'RF-' . date('ymd') . '-' . substr((string) time(), -4);
        try {
            (new DocumentCodeService())->assignIfEmpty(
                $data = ['company_id' => $companyId],
                $model,
                DocumentCodeService::PREFIX_RFQ,
                'rfq_no'
            );
            if (!empty($data['rfq_no'])) {
                $no = (string) $data['rfq_no'];
            }
        } catch (\Throwable $e) {
            // keep generated fallback
        }
        $data = [
            'company_id' => $companyId,
            'rfq_no' => $no,
            'title' => mb_substr($title, 0, 190),
            'status' => 'draft',
            'description' => trim((string) ($args['description'] ?? $args['notes'] ?? '')),
        ];
        $deadline = trim((string) ($args['deadline'] ?? ''));
        if ($deadline !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadline)) {
            $data['deadline'] = $deadline;
        }
        $branchId = self::branchId();
        if ($branchId > 0) {
            $data['branch_id'] = $branchId;
        }
        $id = (int) $model->create($data);
        if ($id < 1) {
            return self::fail('create_failed');
        }
        return self::ok(['id' => $id, 'rfq_no' => $no, 'title' => $title, 'status' => 'draft']);
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function createDraftQuotation(array $args, int $companyId, int $userId): array
    {
        $model = new SupplierQuotation();
        $no = 'QT-' . date('YmdHis');
        $data = [
            'company_id' => $companyId,
            'quotation_no' => $no,
            'amount' => max(0, (float) ($args['amount'] ?? 0)),
            'status' => 'draft',
            'notes' => trim((string) ($args['notes'] ?? $args['title'] ?? 'AI draft quotation')),
        ];
        $rfqId = (int) ($args['rfq_id'] ?? 0);
        if ($rfqId > 0) {
            $data['rfq_id'] = $rfqId;
        }
        $supplierId = (int) ($args['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $data['supplier_id'] = $supplierId;
        }
        $branchId = self::branchId();
        if ($branchId > 0) {
            $data['branch_id'] = $branchId;
        }
        $id = (int) $model->create($data);
        if ($id < 1) {
            return self::fail('create_failed');
        }
        return self::ok(['id' => $id, 'quotation_no' => $no, 'status' => 'draft']);
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function createSalesOrder(array $args, int $companyId, int $userId): array
    {
        $branchId = self::branchId();
        if ($branchId < 1) {
            $rows = (new Inventory())->query(
                'SELECT id FROM rateb_branches WHERE company_id = :cid ORDER BY id ASC LIMIT 1',
                ['cid' => $companyId]
            );
            $branchId = (int) ($rows[0]['id'] ?? 0);
        }
        if ($branchId < 1) {
            return self::fail('branch_required');
        }
        $orderNo = 'SO-' . date('YmdHis');
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_pos_orders
                (company_id, branch_id, order_no, order_type, status, customer_id, subtotal, discount_total, tax, total, created_by)
             VALUES
                (:cid, :bid, :ono, :otype, \'draft\', :cust, 0, 0, 0, :total, :uid)'
        );
        $customerId = (int) ($args['customer_id'] ?? 0);
        $total = max(0, (float) ($args['total'] ?? 0));
        $stmt->execute([
            'cid' => $companyId,
            'bid' => $branchId,
            'ono' => $orderNo,
            'otype' => in_array((string) ($args['order_type'] ?? 'sale'), ['sale', 'quote', 'return'], true)
                ? (string) $args['order_type'] : 'sale',
            'cust' => $customerId > 0 ? $customerId : null,
            'total' => $total,
            'uid' => $userId > 0 ? $userId : null,
        ]);
        $id = (int) $db->lastInsertId();
        if ($id < 1) {
            return self::fail('create_failed');
        }
        return self::ok(['id' => $id, 'order_no' => $orderNo, 'status' => 'draft']);
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function createCrmOpportunity(array $args, int $companyId, int $userId): array
    {
        $name = trim((string) ($args['name'] ?? $args['title'] ?? ''));
        if ($name === '') {
            return self::fail('name_required');
        }
        try {
            $created = (new OpportunityService())->create([
                'name' => $name,
                'amount' => (float) ($args['amount'] ?? 0),
                'notes' => trim((string) ($args['notes'] ?? '')),
                'customer_id' => (int) ($args['customer_id'] ?? 0) ?: null,
                'lead_id' => (int) ($args['lead_id'] ?? 0) ?: null,
                'owner_user_id' => $userId > 0 ? $userId : null,
            ]);
            return self::ok([
                'id' => (int) ($created['id'] ?? 0),
                'opportunity_no' => (string) ($created['opportunity_no'] ?? ''),
                'name' => $name,
            ]);
        } catch (\Throwable $e) {
            return self::fail('create_failed');
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function createLeaveRequest(array $args, int $companyId, int $userId): array
    {
        $employeeId = (int) ($args['employee_id'] ?? 0);
        if ($employeeId < 1) {
            $empName = trim((string) ($args['employee_name'] ?? $args['name'] ?? ''));
            if ($empName !== '') {
                $rows = Database::connection()->prepare(
                    'SELECT id FROM rateb_employees WHERE company_id = :cid AND name LIKE :q ORDER BY id DESC LIMIT 1'
                );
                $rows->execute(['cid' => $companyId, 'q' => '%' . $empName . '%']);
                $employeeId = (int) ($rows->fetchColumn() ?: 0);
            }
        }
        $leaveTypeId = (int) ($args['leave_type_id'] ?? 0);
        if ($leaveTypeId < 1) {
            $st = Database::connection()->prepare(
                "SELECT id FROM rateb_leave_types WHERE company_id = :cid AND (status IS NULL OR status <> 'inactive') ORDER BY id ASC LIMIT 1"
            );
            $st->execute(['cid' => $companyId]);
            $leaveTypeId = (int) ($st->fetchColumn() ?: 0);
        }
        $start = trim((string) ($args['start_date'] ?? ''));
        $end = trim((string) ($args['end_date'] ?? $start));
        if ($start === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            return self::fail('start_date_required');
        }
        if ($end === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
            $end = $start;
        }
        $days = (float) ($args['days'] ?? 0);
        if ($days <= 0) {
            $days = max(1, (float) ((strtotime($end) - strtotime($start)) / 86400) + 1);
        }
        if ($employeeId < 1 || $leaveTypeId < 1) {
            return self::fail('employee_and_leave_type_required');
        }
        try {
            $id = (new HrService())->createPendingLeaveRequest(
                $companyId,
                $employeeId,
                $leaveTypeId,
                $start,
                $end,
                $days,
                trim((string) ($args['reason'] ?? $args['notes'] ?? '')) ?: null,
                self::branchId() ?: null,
                $userId > 0 ? $userId : null
            );
            return self::ok([
                'id' => $id,
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'start_date' => $start,
                'end_date' => $end,
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            return self::fail('create_failed');
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function updateShipmentStatus(array $args, int $companyId, int $userId): array
    {
        $id = (int) ($args['id'] ?? $args['shipment_id'] ?? 0);
        $status = trim((string) ($args['status'] ?? ''));
        $allowed = ['draft', 'pending', 'picked', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'cancelled'];
        if ($id < 1) {
            return self::fail('shipment_id_required');
        }
        if ($status === '' || !in_array($status, $allowed, true)) {
            return self::fail('invalid_shipment_status');
        }
        if (!ErpAiDb::tableExists('rateb_logistics_shipments')) {
            return self::fail('tool_not_implemented');
        }
        $db = Database::connection();
        $chk = $db->prepare('SELECT id, status FROM rateb_logistics_shipments WHERE id = :id AND company_id = :cid LIMIT 1');
        $chk->execute(['id' => $id, 'cid' => $companyId]);
        $row = $chk->fetch(\PDO::FETCH_ASSOC);
        if (!$row) {
            return self::fail('shipment_not_found');
        }
        $upd = $db->prepare('UPDATE rateb_logistics_shipments SET status = :st, updated_at = NOW() WHERE id = :id AND company_id = :cid');
        $upd->execute(['st' => $status, 'id' => $id, 'cid' => $companyId]);
        return self::ok([
            'id' => $id,
            'previous_status' => (string) ($row['status'] ?? ''),
            'status' => $status,
            'updated_by' => $userId > 0 ? $userId : null,
        ]);
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function createProductionOrder(array $args, int $companyId, int $userId): array
    {
        $title = trim((string) ($args['title'] ?? $args['name'] ?? ''));
        if ($title === '') {
            return self::fail('title_required');
        }
        $productId = (int) ($args['product_id'] ?? 0);
        if ($productId < 1) {
            $st = Database::connection()->prepare(
                'SELECT id FROM rateb_mfg_products WHERE company_id = :cid AND deleted_at IS NULL ORDER BY id ASC LIMIT 1'
            );
            try {
                $st->execute(['cid' => $companyId]);
                $productId = (int) ($st->fetchColumn() ?: 0);
            } catch (\Throwable $e) {
                $productId = 0;
            }
        }
        if ($productId < 1) {
            return self::fail('product_id_required');
        }
        try {
            $created = (new ProductionOrderService())->create([
                'title' => $title,
                'product_id' => $productId,
                'qty_planned' => max(1, (float) ($args['qty_planned'] ?? $args['quantity'] ?? 1)),
                'notes' => trim((string) ($args['notes'] ?? '')),
            ]);
            return self::ok([
                'id' => (int) ($created['id'] ?? 0),
                'code' => (string) ($created['code'] ?? ''),
                'title' => $title,
                'product_id' => $productId,
            ]);
        } catch (\Throwable $e) {
            return self::fail('create_failed');
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function createQualityInspection(array $args, int $companyId, int $userId): array
    {
        $title = trim((string) ($args['title'] ?? $args['name'] ?? ''));
        if ($title === '') {
            return self::fail('title_required');
        }
        try {
            $created = (new QualityInspectionService())->create([
                'title' => $title,
                'notes' => trim((string) ($args['notes'] ?? '')),
                'inspector_user_id' => $userId > 0 ? $userId : null,
            ]);
            return self::ok([
                'id' => (int) ($created['id'] ?? 0),
                'code' => (string) ($created['code'] ?? ''),
                'title' => $title,
            ]);
        } catch (\Throwable $e) {
            return self::fail('create_failed');
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function createNonconformity(array $args, int $companyId, int $userId): array
    {
        $title = trim((string) ($args['title'] ?? $args['name'] ?? ''));
        if ($title === '') {
            return self::fail('title_required');
        }
        try {
            $created = (new QualityNonconformityService())->create([
                'title' => $title,
                'description' => trim((string) ($args['description'] ?? $args['notes'] ?? '')),
                'severity' => trim((string) ($args['severity'] ?? 'medium')) ?: 'medium',
                'notes' => trim((string) ($args['notes'] ?? '')),
            ]);
            return self::ok([
                'id' => (int) ($created['id'] ?? 0),
                'code' => (string) ($created['code'] ?? ''),
                'title' => $title,
            ]);
        } catch (\Throwable $e) {
            return self::fail('create_failed');
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function createMarketplaceOrder(array $args, int $companyId, int $userId): array
    {
        if (!ErpAiDb::tableExists('rateb_mp_orders')) {
            return self::fail('tool_not_implemented');
        }
        $itemName = trim((string) ($args['item_name'] ?? $args['title'] ?? $args['name'] ?? 'Marketplace order'));
        $orderNo = 'MPO-' . date('YmdHis');
        $uuid = self::uuid();
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO rateb_mp_orders
                (public_uuid, company_id, order_no, status, currency_code, subtotal, tax_amount, total_amount, notes, created_by)
             VALUES
                (:uuid, :cid, :ono, \'draft\', \'SAR\', :sub, 0, :tot, :notes, :uid)'
        );
        $total = max(0, (float) ($args['total_amount'] ?? $args['amount'] ?? 0));
        $stmt->execute([
            'uuid' => $uuid,
            'cid' => $companyId,
            'ono' => $orderNo,
            'sub' => $total,
            'tot' => $total,
            'notes' => $itemName,
            'uid' => $userId > 0 ? $userId : null,
        ]);
        $id = (int) $db->lastInsertId();
        if ($id < 1) {
            return self::fail('create_failed');
        }
        if (ErpAiDb::tableExists('rateb_mp_order_items')) {
            $ins = $db->prepare(
                'INSERT INTO rateb_mp_order_items
                    (public_uuid, company_id, order_id, item_name, quantity, unit_price, line_total, created_by)
                 VALUES
                    (:uuid, :cid, :oid, :name, 1, :price, :tot, :uid)'
            );
            $ins->execute([
                'uuid' => self::uuid(),
                'cid' => $companyId,
                'oid' => $id,
                'name' => mb_substr($itemName, 0, 190),
                'price' => $total,
                'tot' => $total,
                'uid' => $userId > 0 ? $userId : null,
            ]);
        }
        return self::ok(['id' => $id, 'order_no' => $orderNo, 'status' => 'draft', 'item_name' => $itemName]);
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function approveApprovalRequest(array $args, int $companyId, int $userId): array
    {
        return self::decisionOnApproval($args, $companyId, $userId, 'approved');
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function rejectApprovalRequest(array $args, int $companyId, int $userId): array
    {
        return self::decisionOnApproval($args, $companyId, $userId, 'rejected');
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function createPayrollCycle(array $args, int $companyId, int $userId): array
    {
        $name = trim((string) ($args['name'] ?? $args['title'] ?? ''));
        if ($name === '') {
            $name = 'Payroll cycle ' . date('Y-m');
        }
        try {
            $created = (new PayrollCycleService())->create([
                'name' => $name,
                'frequency' => trim((string) ($args['frequency'] ?? 'monthly')) ?: 'monthly',
                'notes' => trim((string) ($args['notes'] ?? '')),
            ]);
            return self::ok([
                'id' => (int) ($created['id'] ?? 0),
                'code' => (string) ($created['code'] ?? ''),
                'name' => $name,
            ]);
        } catch (\Throwable $e) {
            return self::fail('create_failed');
        }
    }

    /**
     * @param array<string, mixed> $args
     */
    private static function decisionOnApproval(array $args, int $companyId, int $userId, string $to): array
    {
        $id = (int) ($args['id'] ?? $args['request_id'] ?? 0);
        if ($id < 1) {
            return self::fail('approval_request_id_required');
        }
        try {
            $wf = new ApprovalWorkflowService();
            $result = $wf->transition($id, $to, trim((string) ($args['reason'] ?? $args['comment'] ?? '')) ?: null);
            (new ApprovalActionService())->record([
                'request_id' => $id,
                'action_type' => $to === 'approved' ? 'approve' : 'reject',
                'comment' => trim((string) ($args['comment'] ?? $args['reason'] ?? '')),
                'actor_user_id' => $userId > 0 ? $userId : null,
            ]);
            return self::ok([
                'id' => $id,
                'workflow_status' => $to,
                'result' => is_array($result) ? $result : ['ok' => true],
            ]);
        } catch (\Throwable $e) {
            return self::fail('approval_action_failed');
        }
    }

    private static function branchId(): int
    {
        if (function_exists('rateb_resolve_create_branch_id')) {
            return (int) rateb_resolve_create_branch_id();
        }
        return 0;
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /**
     * @return array{success: true, data: mixed, error: null}
     */
    private static function ok(mixed $data): array
    {
        return ['success' => true, 'data' => $data, 'error' => null];
    }

    /**
     * @return array{success: false, data: null, error: string, error_code: string, error_message: string}
     */
    private static function fail(string $code): array
    {
        $key = 'ai_tool_err_' . $code;
        $translated = function_exists('__') ? __($key) : $code;
        $message = (is_string($translated) && $translated !== '' && $translated !== $key) ? $translated : $code;
        return [
            'success' => false,
            'data' => null,
            'error' => $code,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }
}
