<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Company;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\DocumentService;
use Rateb\App\Services\StockMovementService;
use Rateb\App\Services\WorkflowService;
use Rateb\App\Controllers\Shared\ExportController;

final class StockMovementsController extends Controller
{
    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $service = new StockMovementService();
        $this->view('company/stock-movements/index', [
            'title' => __('stock_movements'),
            'items' => $service->listRecent(100),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('stock-movements'),
            'exportEnabled' => rateb_can_export_entity('stock-movements'),
        ], 'main');
    }

    public function store(): void
    {
        if (!rateb_can_manage_entity('stock-movements')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('stock-movements'));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url('stock-movements'));
        }
        try {
            $payload = [
                'inventory_id' => (int) $this->input('inventory_id', 0),
                'warehouse_id' => (int) $this->input('warehouse_id', 0) ?: null,
                'movement_type' => (string) $this->input('movement_type', 'in'),
                'quantity' => (float) $this->input('quantity', 0),
                'notes' => trim((string) $this->input('notes', '')),
            ];
            \Rateb\App\Services\TenantFkValidator::validate(
                ['inventory_id' => $payload['inventory_id'], 'warehouse_id' => $payload['warehouse_id'] ?? 0],
                ['inventory_id', 'warehouse_id']
            );
            $id = (new StockMovementService())->record($payload);
            (new AuditService())->log('create', 'stock_movement', $id);
            SessionManager::flash('success', __('save') . ' OK');
        } catch (\Throwable $e) {
            SessionManager::flash('error', $e->getMessage());
        }
        $this->redirect(rateb_app_url('stock-movements'));
    }

    public function export(): void
    {
        $items = (new StockMovementService())->listRecent(500);
        ExportController::send('stock_movements', [
            ['name' => 'movement_no', 'label' => __('movement_no')],
            ['name' => 'movement_type', 'label' => __('movement_type')],
            ['name' => 'item_name', 'label' => __('item_name')],
            ['name' => 'quantity', 'label' => __('quantity')],
            ['name' => 'created_at', 'label' => __('created_at')],
        ], $items, __('stock_movements'), 'stock-movements');
    }

    public function bulkDestroy(): void
    {
        if (!rateb_can_manage_entity('stock-movements')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('stock-movements'));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_app_url('stock-movements'));
        }
        $raw = $this->input('ids', []);
        $ids = is_array($raw)
            ? array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)))
            : [];
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            $this->redirect(rateb_app_url('stock-movements'));
        }
        $deleted = (new \Rateb\App\Models\StockMovement())->deleteMany($ids);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_delete', 'stock_movement', $id);
        }
        SessionManager::flash('success', __('bulk_deleted', ['count' => $deleted]));
        $this->redirect(rateb_app_url('stock-movements'));
    }
}

final class DocumentsController extends Controller
{
    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = function_exists('rateb_resolve_ops_company_id')
            ? rateb_resolve_ops_company_id()
            : (int) SessionManager::get('rateb_company_id');
        if ($companyId > 0) {
            TenantContext::setCompanyId($companyId);
        }
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare('SELECT * FROM rateb_documents WHERE company_id = :cid ORDER BY id DESC LIMIT 100');
        $stmt->execute(['cid' => $companyId]);
        $this->view('company/documents/index', [
            'title' => __('documents'),
            'items' => $stmt->fetchAll(),
            'csrf' => Csrf::token(),
            'canManage' => rateb_can_manage_entity('documents'),
        ], 'main');
    }

    public function store(): void
    {
        if (!rateb_can_manage_entity('documents')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('documents'));
        }
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('documents'));
        }
        $result = (new DocumentService())->storeUpload(
            $_FILES['document'] ?? [],
            (string) $this->input('entity_type', 'general'),
            (int) $this->input('entity_id', 0),
            trim((string) $this->input('title', ''))
        );
        if ($result['success']) {
            SessionManager::flash('success', __('save') . ' OK');
        } else {
            SessionManager::flash('error', $result['error'] ?? __('invalid_request'));
        }
        $this->redirect(rateb_app_url('documents'));
    }
}

final class WorkflowsController extends Controller
{
    public function index(): void
    {
        $companyId = (int) SessionManager::get('rateb_company_id');
        $svc = new WorkflowService();
        $db = \Rateb\App\Core\Database::connection();
        $pending = $db->prepare(
            'SELECT i.*, w.name AS workflow_name FROM rateb_approval_instances i
             JOIN rateb_approval_workflows w ON w.id = i.workflow_id
             WHERE i.company_id = :cid AND i.status = :st ORDER BY i.id DESC LIMIT 50'
        );
        $pending->execute(['cid' => $companyId, 'st' => 'pending']);
        $this->view('company/workflows/index', [
            'title' => __('workflows'),
            'workflows' => $svc->listWorkflows($companyId),
            'pending' => $pending->fetchAll(),
            'csrf' => Csrf::token(),
            'canApprove' => rateb_can('workflows.approve'),
        ], 'main');
    }

    public function approve(array $params): void
    {
        if (!rateb_can('workflows.approve')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('workflows'));
        }
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('workflows'));
        }
        $id = (int) ($params['id'] ?? 0);
        (new WorkflowService())->approve($id, trim((string) $this->input('comment', '')));
        (new AuditService())->log('approve', 'workflow_instance', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('workflows'));
    }

    public function reject(array $params): void
    {
        if (!rateb_can('workflows.approve')) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_app_url('workflows'));
        }
        if (!$this->validateCsrf()) {
            $this->redirect(rateb_app_url('workflows'));
        }
        $id = (int) ($params['id'] ?? 0);
        (new WorkflowService())->reject($id, trim((string) $this->input('comment', '')));
        (new AuditService())->log('reject', 'workflow_instance', $id);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_app_url('workflows'));
    }
}

final class ProductCategoriesController extends \Rateb\App\Controllers\CrudController
{
    public function __construct()
    {
        $this->model = new \Rateb\App\Models\ProductCategory();
        $this->viewPrefix = 'company/product-categories';
        $this->routePrefix = rateb_app_route('product-categories');
        $this->entityName = 'product_categories';
        $this->permissionResource = 'product-categories';
        $this->indexFields = [
            ['name' => 'image_thumb', 'label' => 'category_image', 'type' => 'image'],
            ['name' => 'code', 'label' => 'code'],
            ['name' => 'name', 'label' => 'name'],
            ['name' => 'parent_label', 'label' => 'parent_category'],
            ['name' => 'product_count', 'label' => 'product_count'],
            ['name' => 'sort_order', 'label' => 'sort_order'],
            ['name' => 'is_active', 'label' => 'status', 'type' => 'status'],
            ['name' => 'is_visible', 'label' => 'visibility', 'type' => 'status'],
        ];
        $this->fields = [
            ['name' => 'code', 'label' => 'category_code', 'type' => 'text', 'col' => 'col-md-4'],
            ['name' => 'parent_id', 'label' => 'parent_category', 'type' => 'fk', 'lookup' => 'product_category_parents', 'col' => 'col-md-8'],
            ['name' => 'name', 'label' => 'name', 'type' => 'text', 'required' => true, 'col' => 'col-md-6'],
            ['name' => 'name_ar', 'label' => 'name_ar', 'type' => 'text', 'col' => 'col-md-6'],
            ['name' => 'description_en', 'label' => 'description_en', 'type' => 'textarea', 'col' => 'col-md-6', 'rows' => 3],
            ['name' => 'description_ar', 'label' => 'description_ar', 'type' => 'textarea', 'col' => 'col-md-6', 'rows' => 3],
            ['name' => 'icon', 'label' => 'category_icon', 'type' => 'text', 'col' => 'col-md-4', 'attrs' => ['placeholder' => 'fa-tags']],
            ['name' => 'sort_order', 'label' => 'sort_order', 'type' => 'number', 'min' => '0', 'col' => 'col-md-4'],
            ['name' => 'is_active', 'label' => 'active', 'type' => 'select', 'lookup' => 'yes_no', 'translate_options' => true, 'col' => 'col-md-2'],
            ['name' => 'is_visible', 'label' => 'category_visible', 'type' => 'select', 'lookup' => 'yes_no', 'translate_options' => true, 'col' => 'col-md-2'],
        ];
    }

    public function create(): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        if (rateb_is_super_admin() && rateb_resolve_ops_company_id() < 1) {
            SessionManager::flash('error', __('select_company_ops'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        parent::create();
    }

    public function index(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) (TenantContext::companyId() ?? rateb_resolve_ops_company_id());
        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $offset = ($page - 1) * $limit;
        $search = trim((string) $this->input('q', ''));
        $svc = new \Rateb\App\Services\ProductCategoryService();
        $items = $svc->enrichRows($this->model->all($limit, $offset, [], $search), $companyId);
        foreach ($items as &$row) {
            $row['is_active'] = !empty($row['is_active']) ? 'yes' : 'no';
            $row['is_visible'] = !empty($row['is_visible']) ? 'yes' : 'no';
        }
        unset($row);
        $items = $this->enrichItemsWithDocumentCounts($items);
        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags([
            'title' => __($this->entityName),
            'items' => $items,
            'total' => $this->model->count([], $search),
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->indexFields,
            'csrf' => \Rateb\App\Core\Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
            'documentEntityType' => $this->filesEnabled ? $this->resolveDocumentEntityType() : '',
            'stats' => $svc->stats($companyId),
            'categoryTree' => $svc->tree($companyId),
            'mostUsed' => $svc->mostUsed($companyId, 5),
            'reportRoute' => rateb_app_url('product-categories/report'),
            'exportRoute' => rateb_app_url('product-categories/export'),
        ]), $this->layout());
    }

    protected function formViewData(array $extra = []): array
    {
        $data = parent::formViewData($extra);
        $item = $data['item'] ?? null;
        if (is_array($item) && !empty($item['id'])) {
            $data['breadcrumbs'] = (new \Rateb\App\Services\ProductCategoryService())->breadcrumb((int) $item['id']);
            $data['excludeParentId'] = (int) $item['id'];
        }
        $data['categoryTree'] = (new \Rateb\App\Services\ProductCategoryService())->tree(
            (int) (TenantContext::companyId() ?? rateb_resolve_ops_company_id())
        );
        $data['multipart'] = true;
        $categoryId = is_array($item) ? (int) ($item['id'] ?? 0) : 0;
        $svc = new \Rateb\App\Services\ProductCategoryService();
        $hasImage = is_array($item) && !empty($item['image_path']);
        $data['categoryImage'] = [
            'inputName' => 'category_image',
            'label' => __('category_image'),
            'imageUrl' => $hasImage ? $svc->imageUrl($categoryId, (string) ($item['image_path'] ?? '')) : '',
            'hasImage' => $hasImage,
        ];
        return $data;
    }

    protected function collectData(): array
    {
        $data = parent::collectData();
        $this->assignDocumentCode($data, \Rateb\App\Services\DocumentCodeService::PREFIX_PRODUCT_CATEGORY, 'code');
        if (!isset($data['sort_order']) || $data['sort_order'] === null || $data['sort_order'] === '') {
            $data['sort_order'] = 0;
        }
        if (!isset($data['is_active']) || $data['is_active'] === null || $data['is_active'] === '') {
            $data['is_active'] = 1;
        }
        if (!isset($data['is_visible']) || $data['is_visible'] === null || $data['is_visible'] === '') {
            $data['is_visible'] = 1;
        }
        $parentId = (int) ($data['parent_id'] ?? 0);
        $data['parent_id'] = $parentId > 0 ? $parentId : null;
        return $data;
    }

    public function store(): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $data = $this->collectData();
        $this->ensureTenantCompanyForWrite($data);
        try {
            $this->validateParentId($data, 0);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        try {
            $id = $this->model->create($data);
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        if ($id < 1) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        try {
            $this->persistCategoryImage($id);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $item = $this->model->find($id);
        $attachmentOk = $this->saveEntityAttachment($id, is_array($item) ? $item : null);
        (new \Rateb\App\Services\AuditService())->log('create', $this->entityName, $id, $data);
        if ($attachmentOk) {
            SessionManager::flash('success', __('category_saved'));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function update(array $params): void
    {
        $this->guardManage();
        rateb_bootstrap_ops_tenant();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectData();
        try {
            $this->validateParentId($data, $id);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        try {
            $this->model->update($id, $data);
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        try {
            $this->persistCategoryImage($id);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $item = $this->model->find($id);
        $attachmentOk = $this->saveEntityAttachment($id, is_array($item) ? $item : null);
        (new \Rateb\App\Services\AuditService())->log('update', $this->entityName, $id, $data);
        if ($attachmentOk) {
            SessionManager::flash('success', __('category_saved'));
        }
        $this->redirect(rateb_url($this->routePrefix));
    }

    /** @param array<string, mixed> $data */
    private function validateParentId(array $data, int $editId): void
    {
        $parentId = (int) ($data['parent_id'] ?? 0);
        if ($parentId < 1) {
            return;
        }
        if ($editId > 0 && $parentId === $editId) {
            throw new \RuntimeException(__('category_parent_self'));
        }
        $seen = [$editId];
        $current = $parentId;
        while ($current > 0) {
            if (in_array($current, $seen, true)) {
                throw new \RuntimeException(__('category_parent_cycle'));
            }
            $seen[] = $current;
            $row = $this->model->find($current);
            if (!$row) {
                throw new \RuntimeException(__('invalid_request'));
            }
            $current = (int) ($row['parent_id'] ?? 0);
        }
    }

    public function copy(array $params): void
    {
        $this->guardManage();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        unset($item['id'], $item['created_at'], $item['image_path']);
        $item['name'] = trim((string) ($item['name'] ?? '')) . ' (copy)';
        if (!empty($item['name_ar'])) {
            $item['name_ar'] = trim((string) $item['name_ar']) . ' (نسخة)';
        }
        $item['code'] = '';
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('copy_category'),
            'item' => $item,
        ]), $this->layout());
    }

    public function image(array $params): void
    {
        rateb_bootstrap_ops_tenant();
        $id = (int) ($params['id'] ?? 0);
        if ($id < 1) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        if (function_exists('rateb_can_view_entity') && !rateb_can_view_entity('product-categories')) {
            http_response_code(403);
            echo __('access_denied');
            return;
        }
        (new \Rateb\App\Services\ProductCategoryService())->sendImage($id);
    }

    public function report(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) (TenantContext::companyId() ?? rateb_resolve_ops_company_id());
        $svc = new \Rateb\App\Services\ProductCategoryService();
        $this->view($this->viewPrefix . '/report', [
            'title' => __('category_products_report'),
            'rows' => $svc->productsByCategoryReport($companyId),
            'routePrefix' => $this->routePrefix,
            'exportRoute' => rateb_app_url('product-categories/export'),
            'exportEnabled' => function_exists('rateb_can_export_entity') ? rateb_can_export_entity('product-categories') : true,
        ], $this->layout());
    }

    public function export(): void
    {
        rateb_bootstrap_ops_tenant();
        $companyId = (int) (TenantContext::companyId() ?? rateb_resolve_ops_company_id());
        $rows = (new \Rateb\App\Services\ProductCategoryService())->productsByCategoryReport($companyId);
        $export = [];
        foreach ($rows as $row) {
            $export[] = [
                'category_code' => $row['category_code'] ?? '',
                'category_name' => $row['category_name'] ?? '',
                'product_count' => $row['product_count'] ?? 0,
                'stock_value' => $row['stock_value'] ?? 0,
            ];
        }
        \Rateb\App\Controllers\Shared\ExportController::send('product_categories', [
            ['name' => 'category_code', 'label' => __('category_code')],
            ['name' => 'category_name', 'label' => __('name')],
            ['name' => 'product_count', 'label' => __('product_count')],
            ['name' => 'stock_value', 'label' => __('stock_value')],
        ], $export, __('category_products_report'), 'product-categories');
    }

    public function destroy(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $reason = (new \Rateb\App\Services\ProductCategoryService())->deleteBlockedReason($id);
        if ($reason !== null) {
            SessionManager::flash('error', $reason);
            $this->redirect(rateb_url($this->routePrefix));
        }
        $this->deleteStoredImage($id);
        parent::destroy($params);
    }

    public function bulkDestroy(): void
    {
        $this->guardManage();
        if (!$this->bulkEnabled) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $ids = $this->parseBulkIds();
        $svc = new \Rateb\App\Services\ProductCategoryService();
        foreach ($ids as $id) {
            if ($svc->deleteBlockedReason($id) !== null) {
                SessionManager::flash('error', __('category_delete_blocked'));
                $this->redirect(rateb_url($this->routePrefix));
            }
            $this->deleteStoredImage($id);
        }
        parent::bulkDestroy();
    }

    private function persistCategoryImage(int $id): void
    {
        $svc = new \Rateb\App\Services\ProductCategoryService();
        if (!empty($_POST['remove_category_image'])) {
            $item = $this->model->find($id);
            if (is_array($item) && !empty($item['image_path'])) {
                $svc->deleteImageFile((string) $item['image_path']);
                $this->model->update($id, ['image_path' => null]);
            }
        }
        if (!isset($_FILES['category_image'])) {
            return;
        }
        $companyId = (int) (TenantContext::companyId() ?? rateb_resolve_ops_company_id());
        $result = $svc->storeImageUpload($_FILES['category_image'], $companyId);
        if (!$result['success']) {
            throw new \RuntimeException((string) ($result['error'] ?? __('upload_failed')));
        }
        if (empty($result['path'])) {
            return;
        }
        $item = $this->model->find($id);
        if (is_array($item) && !empty($item['image_path'])) {
            $svc->deleteImageFile((string) $item['image_path']);
        }
        $this->model->update($id, ['image_path' => $result['path']]);
    }

    private function deleteStoredImage(int $id): void
    {
        $item = $this->model->find($id);
        if (is_array($item) && !empty($item['image_path'])) {
            (new \Rateb\App\Services\ProductCategoryService())->deleteImageFile((string) $item['image_path']);
        }
    }

    protected function layout(): string
    {
        return 'main';
    }
}
