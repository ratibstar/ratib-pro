<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Controllers;

use Rateb\App\Core\Csrf;
use Rateb\App\Core\Database;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Pos\Models\PosOrder;
use Rateb\App\Pos\Support\PosBranchScope;

final class PosOrdersController extends PosBaseController
{
    public function index(): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/orders');
        $companyId = $this->companyId();
        TenantContext::setCompanyId($companyId);
        $db = Database::connection();
        [$branchSql, $branchParams] = PosBranchScope::readFilterSql('o');
        $stmt = $db->prepare(
            'SELECT o.* FROM rateb_pos_orders o
             WHERE o.company_id = :cid AND o.status = :st' . $branchSql . '
             ORDER BY o.id DESC LIMIT 100'
        );
        $stmt->execute(array_merge(['cid' => $companyId, 'st' => 'completed'], $branchParams));
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $this->posView('orders/index', [
            'title' => __('pos_orders'),
            'items' => $items,
            'csrf' => Csrf::token(),
        ]);
    }

    public function show(array $params): void
    {
        $this->bootstrapPos();
        $this->guardPosView('pos/orders');
        $id = (int) ($params['id'] ?? 0);
        $companyId = $this->companyId();
        $order = (new PosOrder())->find($id);
        if (!$order || (int) ($order['company_id'] ?? 0) !== $companyId) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_app_url('pos/orders'));
            return;
        }
        try {
            PosBranchScope::assertOrderReadable($order);
        } catch (\Throwable $e) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('pos/orders'));
            return;
        }
        $db = Database::connection();
        $lines = $db->prepare('SELECT * FROM rateb_pos_order_lines WHERE order_id = :oid AND company_id = :cid ORDER BY line_no');
        $lines->execute(['oid' => $id, 'cid' => $companyId]);
        $payments = $db->prepare('SELECT * FROM rateb_pos_payments WHERE order_id = :oid AND company_id = :cid');
        $payments->execute(['oid' => $id, 'cid' => $companyId]);
        $refunds = $db->prepare('SELECT * FROM rateb_pos_refunds WHERE order_id = :oid AND company_id = :cid');
        $refunds->execute(['oid' => $id, 'cid' => $companyId]);
        $receipt = null;
        if (!empty($order['receipt_json'])) {
            $decoded = json_decode((string) $order['receipt_json'], true);
            $receipt = is_array($decoded) ? $decoded : null;
        }
        $this->posView('orders/show', [
            'title' => __('pos_orders') . ' — ' . ($order['order_no'] ?? ''),
            'order' => $order,
            'lines' => $lines->fetchAll(\PDO::FETCH_ASSOC) ?: [],
            'payments' => $payments->fetchAll(\PDO::FETCH_ASSOC) ?: [],
            'refunds' => $refunds->fetchAll(\PDO::FETCH_ASSOC) ?: [],
            'receipt' => $receipt,
            'csrf' => Csrf::token(),
        ]);
    }
}
