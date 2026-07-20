<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\IpRateLimiter;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Company;
use Rateb\App\Models\Inventory;
use Rateb\App\Models\PurchaseOrder;
use Rateb\App\Models\PurchaseRequest;
use Rateb\App\Models\Supplier;
use Rateb\App\Services\AccountLockoutService;
use Rateb\App\Services\ApiBranchGuardService;
use Rateb\App\Services\ApiTokenService;
use Rateb\App\Services\DashboardService;
use Rateb\App\Services\DedicatedTenantPolicy;
use Rateb\App\Services\Logger;
use Rateb\App\Services\PlanLimitService;

final class ApiController extends Controller
{
    private ApiBranchGuardService $branchGuard;

    public function __construct()
    {
        $this->branchGuard = new ApiBranchGuardService();
    }

    public function index(): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!IpRateLimiter::attempt('api_index_' . md5($ip), 120, 60)) {
            Response::json(['success' => false, 'message' => 'Too many requests'], 429);
            return;
        }
        Response::json([
            'success' => true,
            'version' => 'v1',
            'name' => 'RTAB ERP API',
        ]);
    }

    public function createToken(): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ipKey = 'api_token_ip_' . md5($ip);

        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');
        $emailKey = 'api_token_email_' . md5(strtolower($email));

        // Count failures only (mirror web login) — do not burn quota on every POST.
        if (IpRateLimiter::isLimited($ipKey, 30) || IpRateLimiter::isLimited($emailKey, 15)) {
            Response::json(['success' => false, 'message' => 'Too many attempts'], 429);
            return;
        }

        $userModel = new \Rateb\App\Models\User();
        $preUser = $userModel->findByEmail($email);
        $lockout = new AccountLockoutService();
        if ($lockout->isLocked($preUser)) {
            Logger::warning('API token auth blocked (locked)', ['email' => $email, 'ip' => $ip]);
            Response::json(['success' => false, 'message' => 'Account temporarily locked'], 403);
            return;
        }

        $user = $preUser;
        if (!$user || !password_verify($password, (string) $user['password'])) {
            IpRateLimiter::attempt($ipKey, 30, 900);
            if ($email !== '') {
                IpRateLimiter::attempt($emailKey, 15, 900);
            }
            $lockout->recordFailure($email);
            Logger::warning('API token auth failed', ['email' => $email, 'ip' => $ip]);
            Response::json(['success' => false, 'message' => 'Invalid credentials'], 401);
            return;
        }
        $lockout->clearLock((int) $user['id']);
        IpRateLimiter::reset($ipKey);
        IpRateLimiter::reset($emailKey);
        if ((string) ($user['status'] ?? '') !== 'active') {
            Response::json(['success' => false, 'message' => 'Account inactive'], 403);
            return;
        }
        // ESS / mobile API tokens are company-scoped. ApiAuthMiddleware always
        // forces TenantContext::setSuperAdmin(false). Platform SA rows often have
        // null company_id — bind primary tenant like web Auth::establishSession.
        $companyId = (int) ($user['company_id'] ?? 0);
        $isSa = (int) ($user['is_super_admin'] ?? 0) === 1;
        if ($companyId < 1 && $isSa) {
            $companyId = (int) DedicatedTenantPolicy::primaryCompanyId();
        }
        if ($companyId < 1) {
            Response::json([
                'success' => false,
                'code' => 'no_company',
                'message' => 'No company linked',
            ], 403);
            return;
        }
        $company = (new Company())->find($companyId);
        if (!$company || (string) ($company['status'] ?? '') !== 'active') {
            Response::json(['success' => false, 'message' => 'Company access denied'], 403);
            return;
        }
        // Mirror web SA login: skip subscription gate for super-admin tokens.
        if (!$isSa && !(new PlanLimitService())->companyAccessAllowed($companyId)) {
            Response::json(['success' => false, 'message' => 'Company access denied'], 403);
            return;
        }

        $token = (new ApiTokenService())->createToken((int) $user['id'], 'API Token', 90, $companyId);
        Response::json(['success' => true, 'token' => $token['token'], 'expires_at' => $token['expires_at']]);
    }

    public function dashboard(): void
    {
        if (TenantContext::isSuperAdmin()) {
            Response::json(['success' => false, 'message' => 'Forbidden'], 403);
            return;
        }
        if (!$this->branchGuard->assertCompanyContext()) {
            return;
        }
        $companyId = (int) TenantContext::companyId();
        $service = new DashboardService();
        Response::json(['success' => true, 'data' => $service->companyMetrics($companyId)]);
    }

    public function listCompanies(): void
    {
        Response::json(['success' => false, 'message' => 'Forbidden'], 403);
    }

    public function getCompany(array $params): void
    {
        Response::json(['success' => false, 'message' => 'Forbidden'], 403);
    }

    public function createCompany(): void
    {
        Response::json(['success' => false, 'message' => 'Forbidden'], 403);
    }

    public function listSuppliers(): void
    {
        if (!$this->branchGuard->assertCompanyContext()) {
            return;
        }
        Response::json(['success' => true, 'data' => (new Supplier())->all(100, 0)]);
    }

    public function createSupplier(): void
    {
        if (!$this->branchGuard->assertCompanyContext()) {
            return;
        }
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        if (!$this->branchGuard->rejectForeignBranchId($body)) {
            return;
        }
        $payload = $this->branchGuard->stampCreate([
            'name' => trim((string) ($body['name'] ?? '')),
            'email' => trim((string) ($body['email'] ?? '')),
            'status' => 'active',
            'branch_id' => isset($body['branch_id']) ? (int) $body['branch_id'] : null,
        ]);
        $id = (new Supplier())->create($payload);
        Response::json(['success' => true, 'id' => $id], 201);
    }

    public function listPurchaseRequests(): void
    {
        if (!$this->branchGuard->assertCompanyContext()) {
            return;
        }
        Response::json(['success' => true, 'data' => (new PurchaseRequest())->all(100, 0)]);
    }

    public function createPurchaseRequest(): void
    {
        if (!$this->branchGuard->assertCompanyContext()) {
            return;
        }
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        if (!$this->branchGuard->rejectForeignBranchId($body)) {
            return;
        }
        $model = new PurchaseRequest();
        $payload = $this->branchGuard->stampCreate([
            'request_no' => $model->generateRequestNo(),
            'title' => trim((string) ($body['title'] ?? '')),
            'status' => 'draft',
            'priority' => $body['priority'] ?? 'medium',
            'branch_id' => isset($body['branch_id']) ? (int) $body['branch_id'] : null,
        ]);
        $id = $model->create($payload);
        Response::json(['success' => true, 'id' => $id], 201);
    }

    public function listPurchaseOrders(): void
    {
        if (!$this->branchGuard->assertCompanyContext()) {
            return;
        }
        Response::json(['success' => true, 'data' => (new PurchaseOrder())->all(100, 0)]);
    }

    public function createPurchaseOrder(): void
    {
        if (!$this->branchGuard->assertCompanyContext()) {
            return;
        }
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        if (!$this->branchGuard->rejectForeignBranchId($body)) {
            return;
        }
        $model = new PurchaseOrder();
        $payload = $this->branchGuard->stampCreate([
            'order_no' => $model->generateOrderNo(),
            'order_date' => date('Y-m-d'),
            'status' => 'draft',
            'total_amount' => (float) ($body['total_amount'] ?? 0),
            'branch_id' => isset($body['branch_id']) ? (int) $body['branch_id'] : null,
        ]);
        $id = $model->create($payload);
        Response::json(['success' => true, 'id' => $id], 201);
    }

    public function listInventory(): void
    {
        if (!$this->branchGuard->assertCompanyContext()) {
            return;
        }
        Response::json(['success' => true, 'data' => (new Inventory())->all(100, 0)]);
    }

    public function createInventory(): void
    {
        if (!$this->branchGuard->assertCompanyContext()) {
            return;
        }
        $body = json_decode((string) file_get_contents('php://input'), true) ?: [];
        if (!$this->branchGuard->rejectForeignBranchId($body)) {
            return;
        }
        $payload = $this->branchGuard->stampCreate([
            'item_name' => trim((string) ($body['item_name'] ?? '')),
            'quantity' => (float) ($body['quantity'] ?? 0),
            'unit_cost' => (float) ($body['unit_cost'] ?? 0),
            'status' => 'active',
            'branch_id' => isset($body['branch_id']) ? (int) $body['branch_id'] : null,
        ]);
        $id = (new Inventory())->create($payload);
        Response::json(['success' => true, 'id' => $id], 201);
    }
}
