<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Company;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\Supplier;
use Rateb\App\Services\ApiTokenService;
use Rateb\App\Services\DashboardService;

final class ApiController extends Controller
{
    public function index(): void
    {
        Response::json([
            'success' => true,
            'version' => 'v1',
            'name' => 'RATEB ERP API',
            'endpoints' => [
                'POST /api/v1/auth/token',
                'GET /api/v1/dashboard',
                'GET /api/v1/companies',
                'GET|POST /api/v1/companies/{id}',
                'GET|POST /api/v1/suppliers',
                'GET|POST /api/v1/purchase-requests',
                'GET|POST /api/v1/purchase-orders',
                'GET|POST /api/v1/inventory',
            ],
        ]);
    }

    public function createToken(): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        $userModel = new \Rateb\App\Models\User();
        $user = $userModel->findByEmail($email);
        if (!$user || !password_verify($password, (string) $user['password'])) {
            Response::json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        $token = (new ApiTokenService())->createToken((int) $user['id'], 'API Token');
        Response::json(['success' => true, 'token' => $token['token'], 'expires_at' => $token['expires_at']]);
    }

    public function dashboard(): void
    {
        if (TenantContext::isSuperAdmin()) {
            $service = new DashboardService();
            Response::json(['success' => true, 'data' => $service->adminMetrics()]);
        }
        $companyId = TenantContext::companyId();
        if (!$companyId) {
            Response::json(['success' => false, 'message' => 'No company context'], 403);
        }
        $service = new DashboardService();
        Response::json(['success' => true, 'data' => $service->companyMetrics($companyId)]);
    }

    public function listCompanies(): void
    {
        if (!TenantContext::isSuperAdmin()) {
            Response::json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $model = new Company();
        Response::json(['success' => true, 'data' => $model->all(100, 0)]);
    }

    public function getCompany(array $params): void
    {
        if (!TenantContext::isSuperAdmin()) {
            Response::json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $item = (new Company())->find((int) ($params['id'] ?? 0));
        if (!$item) {
            Response::json(['success' => false, 'message' => 'Not found'], 404);
        }
        Response::json(['success' => true, 'data' => $item]);
    }

    public function createCompany(): void
    {
        if (!TenantContext::isSuperAdmin()) {
            Response::json(['success' => false, 'message' => 'Forbidden'], 403);
        }
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $id = (new Company())->create([
            'name' => trim((string) ($body['name'] ?? '')),
            'slug' => trim((string) ($body['slug'] ?? '')),
            'email' => trim((string) ($body['email'] ?? '')),
            'status' => 'pending',
        ]);
        Response::json(['success' => true, 'id' => $id], 201);
    }

    public function listSuppliers(): void
    {
        Response::json(['success' => true, 'data' => (new Supplier())->all(100, 0)]);
    }

    public function createSupplier(): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $id = (new Supplier())->create([
            'name' => trim((string) ($body['name'] ?? '')),
            'email' => trim((string) ($body['email'] ?? '')),
            'status' => 'active',
        ]);
        Response::json(['success' => true, 'id' => $id], 201);
    }

    public function listPurchaseRequests(): void
    {
        Response::json(['success' => true, 'data' => (new PurchaseRequest())->all(100, 0)]);
    }

    public function createPurchaseRequest(): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $model = new PurchaseRequest();
        $id = $model->create([
            'request_no' => $model->generateRequestNo(),
            'title' => trim((string) ($body['title'] ?? '')),
            'status' => 'draft',
            'priority' => $body['priority'] ?? 'medium',
        ]);
        Response::json(['success' => true, 'id' => $id], 201);
    }

    public function listPurchaseOrders(): void
    {
        Response::json(['success' => true, 'data' => (new PurchaseOrder())->all(100, 0)]);
    }

    public function createPurchaseOrder(): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $model = new PurchaseOrder();
        $id = $model->create([
            'order_no' => $model->generateOrderNo(),
            'order_date' => date('Y-m-d'),
            'status' => 'draft',
            'total_amount' => (float) ($body['total_amount'] ?? 0),
        ]);
        Response::json(['success' => true, 'id' => $id], 201);
    }

    public function listInventory(): void
    {
        Response::json(['success' => true, 'data' => (new Inventory())->all(100, 0)]);
    }

    public function createInventory(): void
    {
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $id = (new Inventory())->create([
            'item_name' => trim((string) ($body['item_name'] ?? '')),
            'quantity' => (float) ($body['quantity'] ?? 0),
            'unit_cost' => (float) ($body['unit_cost'] ?? 0),
            'status' => 'active',
        ]);
        Response::json(['success' => true, 'id' => $id], 201);
    }
}
