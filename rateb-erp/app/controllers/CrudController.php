<?php
declare(strict_types=1);

namespace Rateb\App\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Model;
use Rateb\App\Core\SessionManager;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\TenantFkValidator;

abstract class CrudController extends Controller
{
    protected Model $model;
    protected string $viewPrefix;
    protected string $routePrefix;
    protected array $fields = [];
    /** @var array<int, array<string, mixed>> */
    protected array $indexFields = [];
    protected string $entityName = 'record';
    protected bool $bulkEnabled = true;
    protected bool $createEnabled = true;
    protected bool $actionsEnabled = true;
    protected bool $filesEnabled = true;
    protected string $documentEntityType = '';
    protected string $permissionResource = '';
    /** @var array<int, string> */
    protected array $tenantForeignKeys = [];

    public function index(): void
    {
        $page = max(1, (int) $this->input('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags($this->indexViewData($limit, $offset, $page)), $this->layout());
    }

    /** @return array<string, mixed> */
    protected function indexViewData(int $limit, int $offset, int $page): array
    {
        return [
            'title' => __($this->entityName),
            'items' => $this->model->all($limit, $offset),
            'total' => $this->model->count(),
            'page' => $page,
            'limit' => $limit,
            'routePrefix' => $this->routePrefix,
            'fields' => $this->indexFields !== [] ? $this->indexFields : $this->fields,
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
            'documentEntityType' => $this->filesEnabled ? $this->resolveDocumentEntityType() : '',
        ];
    }

    /** @param array<string, mixed> $data */
    protected function applyPermissionFlags(array $data): array
    {
        if (!function_exists('rateb_can_manage_entity')) {
            return $data;
        }
        $resource = $this->permissionResourceKey();
        $canManage = rateb_can_manage_entity($resource);
        $data['createEnabled'] = ($data['createEnabled'] ?? true) && $canManage;
        $data['actionsEnabled'] = ($data['actionsEnabled'] ?? true) && $canManage;
        $data['bulkEnabled'] = ($data['bulkEnabled'] ?? true) && $canManage;
        $data['exportEnabled'] = rateb_can_export_entity($resource);
        $data['permissionResource'] = $resource;
        return $data;
    }

    protected function permissionResourceKey(): string
    {
        if ($this->permissionResource !== '') {
            return $this->permissionResource;
        }
        return (string) preg_replace('#^admin/(ops/)?#', '', $this->routePrefix);
    }

    protected function guardManage(): void
    {
        if (function_exists('rateb_can_manage_entity') && !rateb_can_manage_entity($this->permissionResourceKey())) {
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
    }

    /** @return array<string, mixed> */
    protected function formViewData(array $extra = []): array
    {
        $fields = $extra['fields'] ?? $this->fields;
        return array_merge([
            'routePrefix' => $this->routePrefix,
            'fields' => $fields,
            'csrf' => Csrf::token(),
            'lookups' => (new \Rateb\App\Services\FormLookupService())->forFields($fields),
        ], $extra);
    }

    public function create(): void
    {
        $this->guardManage();
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('create') . ' ' . __($this->entityName),
            'item' => null,
        ]), $this->layout());
    }

    public function store(): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $data = $this->collectData();
        try {
            TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $id = $this->model->create($data);
        (new AuditService())->log('create', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function edit(array $params): void
    {
        $this->guardManage();
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }

        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('edit') . ' ' . __($this->entityName),
            'item' => $item,
        ]), $this->layout());
    }

    public function update(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $id = (int) ($params['id'] ?? 0);
        $data = $this->collectData();
        try {
            TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->model->update($id, $data);
        (new AuditService())->log('update', $this->entityName, $id, $data);
        SessionManager::flash('success', __('save') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function destroy(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $id = (int) ($params['id'] ?? 0);
        $this->model->delete($id);
        (new AuditService())->log('delete', $this->entityName, $id);
        SessionManager::flash('success', __('delete') . ' OK');
        $this->redirect(rateb_url($this->routePrefix));
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
        if ($ids === []) {
            SessionManager::flash('error', __('bulk_none_selected'));
            $this->redirect(rateb_url($this->routePrefix));
        }

        $deleted = $this->model->deleteMany($ids);
        foreach ($ids as $id) {
            (new AuditService())->log('bulk_delete', $this->entityName, $id);
        }
        SessionManager::flash('success', __('bulk_deleted', ['count' => $deleted]));
        $this->redirect(rateb_url($this->routePrefix));
    }

    public function documents(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], $this->layout());
            return;
        }
        $entityType = $this->resolveDocumentEntityType();
        $companyId = (int) ($item['company_id'] ?? \Rateb\App\Core\TenantContext::companyId() ?? 0);
        $canManage = function_exists('rateb_can_manage_entity')
            ? rateb_can_manage_entity($this->permissionResourceKey())
            : true;
        $this->view('shared/entity-documents', [
            'title' => __('entity_documents') . ' — ' . $this->recordLabel($item),
            'entityName' => $this->entityName,
            'item' => $item,
            'entityType' => $entityType,
            'entityId' => $id,
            'companyId' => $companyId,
            'documents' => $companyId > 0
                ? (new \Rateb\App\Services\DocumentService())->listForEntity($entityType, $id, $companyId)
                : [],
            'routePrefix' => $this->routePrefix,
            'backLabel' => __($this->entityName),
            'csrf' => Csrf::token(),
            'canManage' => $canManage,
        ], $this->layout());
    }

    public function storeDocument(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $companyId = (int) ($item['company_id'] ?? \Rateb\App\Core\TenantContext::companyId() ?? 0);
        $title = trim((string) $this->input('doc_title', $this->recordLabel($item)));
        $upload = \Rateb\App\Helpers\EntityAttachment::handleOptionalFile(
            'entity_attachment',
            $companyId,
            $this->resolveDocumentEntityType(),
            $id,
            $title !== '' ? $title : __('attachment')
        );
        if (!($upload['success'] ?? false)) {
            SessionManager::flash('error', (string) ($upload['error'] ?? __('upload_failed')));
        } elseif (!empty($upload['path']) && array_key_exists('document_path', $item)) {
            $this->model->update($id, ['document_path' => (string) $upload['path']]);
            SessionManager::flash('success', __('file_uploaded'));
        } else {
            SessionManager::flash('success', __('file_uploaded'));
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/documents'));
    }

    public function updateDocument(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $entityId = (int) ($params['id'] ?? 0);
        $docId = (int) ($params['docId'] ?? 0);
        $item = $this->model->find($entityId);
        if (!$item || $docId < 1) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $svc = new \Rateb\App\Services\DocumentService();
        $doc = $svc->findById($docId);
        if (!$doc || !$svc->belongsToEntity($doc, $this->resolveDocumentEntityType(), $entityId)) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix . '/' . $entityId . '/documents'));
        }
        $title = trim((string) $this->input('doc_title', ''));
        $file = isset($_FILES['entity_attachment']) ? $_FILES['entity_attachment'] : null;
        $result = $svc->updateDocument($docId, $title, $file);
        if (!($result['success'] ?? false)) {
            SessionManager::flash('error', (string) ($result['error'] ?? __('upload_failed')));
        } else {
            (new AuditService())->log('update_document', $this->entityName, $entityId, ['document_id' => $docId]);
            SessionManager::flash('success', __('file_updated'));
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $entityId . '/documents'));
    }

    public function destroyDocument(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $entityId = (int) ($params['id'] ?? 0);
        $docId = (int) ($params['docId'] ?? 0);
        $item = $this->model->find($entityId);
        if (!$item || $docId < 1) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $svc = new \Rateb\App\Services\DocumentService();
        $doc = $svc->findById($docId);
        if (!$doc || !$svc->belongsToEntity($doc, $this->resolveDocumentEntityType(), $entityId)) {
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix . '/' . $entityId . '/documents'));
        }
        if ($svc->deleteDocument($docId)) {
            (new AuditService())->log('delete_document', $this->entityName, $entityId, ['document_id' => $docId]);
            SessionManager::flash('success', __('file_deleted'));
        } else {
            SessionManager::flash('error', __('access_denied'));
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $entityId . '/documents'));
    }

    protected function resolveDocumentEntityType(): string
    {
        if ($this->documentEntityType !== '') {
            return $this->documentEntityType;
        }
        static $map = [
            'purchase_requests' => 'purchase_request',
            'purchase_orders' => 'purchase_order',
            'supplier_evaluations' => 'supplier_evaluation',
            'supplier_classifications' => 'supplier_classification',
            'product_categories' => 'product_category',
            'inventory_batches' => 'inventory_batch',
            'medical_devices' => 'medical_device',
            'chart_of_accounts' => 'chart_of_account',
            'journal_entries' => 'journal_entry',
            'cash_vouchers' => 'cash_voucher',
            'bank_accounts' => 'bank_account',
            'cost_centers' => 'cost_center',
            'fiscal_periods' => 'fiscal_period',
        ];
        return $map[$this->entityName] ?? $this->entityName;
    }

    /** @param array<string, mixed> $item */
    protected function recordLabel(array $item): string
    {
        foreach (['title', 'name', 'item_name', 'request_no', 'order_no', 'contract_no', 'code', 'item_code', 'evaluation_no'] as $key) {
            if (!empty($item[$key])) {
                return (string) $item[$key];
            }
        }
        return '#' . (int) ($item['id'] ?? 0);
    }

    /** @return array<int, int> */
    protected function parseBulkIds(): array
    {
        $raw = $this->input('ids', []);
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $raw), static fn (int $id): bool => $id > 0)));
    }

    protected function collectData(): array
    {
        $data = [];
        foreach ($this->fields as $field) {
            $name = $field['name'];
            $data[$name] = trim((string) $this->input($name, ''));
        }
        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function assignDocumentCode(array &$data, string $prefix, string $column): void
    {
        (new \Rateb\App\Services\DocumentCodeService())->assignIfEmpty($data, $this->model, $prefix, $column);
    }

    protected function layout(): string
    {
        return 'main';
    }
}
