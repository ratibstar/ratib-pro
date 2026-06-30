<?php
declare(strict_types=1);

namespace Rateb\App\Controllers;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Csrf;
use Rateb\App\Core\Model;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\AuditService;
use Rateb\App\Services\DocumentService;
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
    /** After create/update, redirect to record show page instead of list. */
    protected bool $redirectToShowAfterSave = false;
    /** @var array<int, string> */
    protected array $tenantForeignKeys = [];

    public function index(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $page = max(1, (int) $this->input('page', 1));
        $limit = rateb_list_per_page();
        $offset = ($page - 1) * $limit;
        $search = trim((string) $this->input('q', ''));

        $this->view($this->viewPrefix . '/index', $this->applyPermissionFlags($this->indexViewData($limit, $offset, $page, $search)), $this->layout());
    }

    public function export(): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $search = trim((string) ($_GET['q'] ?? ''));
        $items = $this->localizeCatalogItems($this->model->all(5000, 0, [], $search));
        $columns = [];
        foreach ($this->resolveIndexFields() as $col) {
            $columns[] = [
                'name' => (string) ($col['name'] ?? ''),
                'label' => rateb_label((string) ($col['label'] ?? $col['name'] ?? '')),
                'type' => (string) ($col['type'] ?? ''),
                'lookup' => (string) ($col['lookup'] ?? ''),
            ];
        }
        \Rateb\App\Controllers\Shared\ExportController::send(
            preg_replace('/[^\w\-]+/', '_', $this->permissionResourceKey()),
            $columns,
            $items,
            __($this->entityName),
            $this->permissionResourceKey()
        );
    }

    protected function indexViewData(int $limit, int $offset, int $page, string $search = ''): array
    {
        $items = $this->model->all($limit, $offset, [], $search);
        return [
            'title' => __($this->entityName),
            'items' => $this->localizeCatalogItems($this->enrichItemsWithDocumentCounts($items)),
            'total' => $this->model->count([], $search),
            'page' => $page,
            'limit' => $limit,
            'search' => $search,
            'routePrefix' => $this->routePrefix,
            'exportRoute' => rateb_url($this->routePrefix . '/export'),
            'fields' => $this->resolveIndexFields(),
            'csrf' => Csrf::token(),
            'bulkEnabled' => $this->bulkEnabled,
            'createEnabled' => $this->createEnabled,
            'actionsEnabled' => $this->actionsEnabled,
            'documentEntityType' => $this->filesEnabled ? $this->resolveDocumentEntityType() : '',
        ];
    }

    /** @param array<int, array<string, mixed>> $items */
    protected function enrichItemsWithDocumentCounts(array $items): array
    {
        if (!$this->filesEnabled || $items === []) {
            return $items;
        }
        $docSvc = new DocumentService();
        $entityType = $this->resolveDocumentEntityType();
        foreach ($items as &$row) {
            $companyId = $this->resolveDocumentCompanyId($row);
            $entityId = (int) ($row['id'] ?? 0);
            $row['document_count'] = ($companyId > 0 && $entityId > 0)
                ? $docSvc->countForEntity($entityType, $entityId, $companyId)
                : 0;
        }
        unset($row);
        return $items;
    }

    /** @param array<int, array<string, mixed>> $items */
    protected function localizeCatalogItems(array $items): array
    {
        if ($items === []) {
            return $items;
        }
        $catalog = match ($this->entityName) {
            'hr_job_titles' => 'hr_job_titles',
            'leave_types' => 'leave_types',
            default => '',
        };
        if ($catalog === '') {
            return $items;
        }
        $lookup = new \Rateb\App\Services\FormLookupService();
        foreach ($items as &$row) {
            if (is_array($row) && array_key_exists('name', $row)) {
                $row['name'] = $lookup->localizeLookupRow($catalog, $row);
            }
        }
        unset($row);
        return $items;
    }

    /** @return array<int, array<string, mixed>> */
    protected function resolveIndexFields(): array
    {
        $cols = $this->indexFields !== []
            ? $this->indexFields
            : $this->inferIndexFieldsFromForm();
        return $this->enrichIndexFields($cols);
    }

    /** @return array<int, array<string, mixed>> */
    protected function inferIndexFieldsFromForm(): array
    {
        $skipTypes = ['textarea', 'wysiwyg'];
        $skipPrefixes = [
            'content_', 'body_', 'excerpt_', 'meta_description', 'description_',
            'quote_', 'answer_', 'bio_', 'summary_', 'message_', 'links_lines',
            'address_', 'subtitle_', 'html_body', 'body_html',
        ];
        $out = [];

        foreach ($this->fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $type = (string) ($field['type'] ?? 'text');
            if (in_array($type, $skipTypes, true)) {
                continue;
            }
            $skip = false;
            foreach ($skipPrefixes as $prefix) {
                if (strpos($name, $prefix) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            $colType = 'clip';
            $lookup = (string) ($field['lookup'] ?? '');
            if ($type === 'slug' || $name === 'slug') {
                $colType = 'slug';
            } elseif (in_array($type, ['bidi_text', 'html_preview', 'barcode'], true)) {
                $colType = $type;
            } elseif ($name === 'status' || ($lookup !== '' && str_ends_with($lookup, '_statuses'))) {
                $colType = 'status';
            } elseif ($lookup === 'yes_no') {
                $colType = 'fk';
            } elseif (in_array($type, ['date', 'datetime-local', 'time', 'month', 'week'], true)) {
                $colType = match ($type) {
                    'datetime-local' => 'datetime',
                    default => $type,
                };
            } elseif ($type === 'fk' || $lookup !== '') {
                $colType = 'fk';
            } elseif (function_exists('rateb_date_column_kind')) {
                $dateKind = rateb_date_column_kind($name, $type);
                if ($dateKind !== '') {
                    $colType = $dateKind;
                }
            }

            $col = [
                'name' => $name,
                'label' => $field['label'] ?? $name,
                'type' => $colType,
            ];
            if ($lookup !== '') {
                $col['lookup'] = $lookup;
            }
            $out[] = $col;
        }

        return $out;
    }

    /** @param array<int, array<string, mixed>> $cols */
    protected function enrichIndexFields(array $cols): array
    {
        if (!function_exists('rateb_enrich_index_columns')) {
            return $cols;
        }
        $fieldByName = [];
        foreach ($this->fields as $field) {
            $n = (string) ($field['name'] ?? '');
            if ($n !== '') {
                $fieldByName[$n] = $field;
            }
        }
        return rateb_enrich_index_columns($cols, $fieldByName);
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
            if ($this->isDocumentsModalRequest()) {
                $this->rejectDocumentsModal((string) __('access_denied'));
            }
            SessionManager::flash('error', __('access_denied'));
            $this->redirect(rateb_url($this->routePrefix));
        }
    }

    protected function rejectDocumentsModal(string $message, int $count = 0): void
    {
        if (!$this->isDocumentsModalRequest()) {
            return;
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'count' => $count,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** @return array<string, mixed> */
    protected function formViewData(array $extra = []): array
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $fields = $extra['fields'] ?? $this->fields;
        $lookupSvc = new \Rateb\App\Services\FormLookupService();
        $item = $extra['item'] ?? null;
        $lookups = $lookupSvc->forFields($fields);
        if (is_array($item)) {
            $lookups = $lookupSvc->withMissingItemOptions($lookups, $fields, $item);
        }
        $data = array_merge([
            'routePrefix' => $this->routePrefix,
            'fields' => $fields,
            'csrf' => Csrf::token(),
            'lookups' => $lookups,
        ], $extra);
        if ($this->filesEnabled && empty($data['attachment'])) {
            $data['multipart'] = true;
            $data['attachment'] = $this->attachmentFieldData(is_array($item) ? $item : null);
            $entityId = is_array($item) ? (int) ($item['id'] ?? 0) : 0;
            $companyId = (int) ($data['attachment']['companyId'] ?? 0);
            if ($entityId > 0 && $companyId > 0 && !isset($data['existingDocuments'])) {
                $data['existingDocuments'] = (new DocumentService())->listForEntity(
                    $this->resolveDocumentEntityType(),
                    $entityId,
                    $companyId
                );
            }
        }
        if (!isset($data['existingDocuments'])) {
            $data['existingDocuments'] = [];
        }
        return $data;
    }

    /** @param array<string, mixed>|null $item */
    protected function attachmentFieldData(?array $item): array
    {
        $entityId = is_array($item) ? (int) ($item['id'] ?? 0) : 0;
        $companyId = is_array($item) ? $this->resolveDocumentCompanyId($item) : 0;
        return [
            'entityType' => $this->resolveDocumentEntityType(),
            'entityId' => $entityId,
            'companyId' => $companyId,
            'documentPath' => '',
            'inputName' => 'entity_attachment',
            'label' => __('attach_document'),
        ];
    }

    protected function saveEntityAttachment(int $entityId, ?array $item = null): bool
    {
        if (!$this->filesEnabled || $entityId < 1) {
            return true;
        }
        if (!isset($_FILES['entity_attachment'])
            || ($_FILES['entity_attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return true;
        }
        $item = $item ?? $this->model->find($entityId);
        if (!is_array($item)) {
            return true;
        }
        $companyId = $this->resolveDocumentCompanyId($item);
        $title = trim((string) $this->input('doc_title', ''));
        if ($title === '') {
            $title = $this->recordLabel($item);
        }
        if ($title === '') {
            $title = (string) __('attach_document');
        }
        $upload = \Rateb\App\Helpers\EntityAttachment::handleOptionalFile(
            'entity_attachment',
            $companyId,
            $this->resolveDocumentEntityType(),
            $entityId,
            $title
        );
        if (!($upload['success'] ?? false)) {
            SessionManager::flash('error', (string) ($upload['error'] ?? __('save_ok_attachment_failed')));
            return false;
        }
        return true;
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
        $this->ensureTenantCompanyForWrite($data);
        try {
            TenantFkValidator::validate($data, $this->tenantForeignKeys);
        } catch (\RuntimeException $e) {
            SessionManager::flash('error', $e->getMessage());
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        try {
            $id = $this->model->create($data);
            $item = $this->model->find($id);
            $attachmentOk = $this->saveEntityAttachment($id, is_array($item) ? $item : null);
            (new AuditService())->log('create', $this->entityName, $id, $data);
            if ($attachmentOk) {
                SessionManager::flash('success', __('saved_ok'));
            }
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/create'));
        }
        $this->redirectAfterSave($id);
    }

    protected function redirectAfterSave(int $id): void
    {
        if ($this->redirectToShowAfterSave && $id > 0) {
            $this->redirect(rateb_url($this->routePrefix . '/' . $id));
            return;
        }
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

    public function show(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404']);
            return;
        }
        $this->view($this->viewPrefix . '/form', $this->formViewData([
            'title' => __('view') . ' ' . __($this->entityName),
            'item' => $item,
            'readonly' => true,
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
        try {
            $this->model->update($id, $data);
            $item = $this->model->find($id);
            $attachmentOk = $this->saveEntityAttachment($id, is_array($item) ? $item : null);
            (new AuditService())->log('update', $this->entityName, $id, $data);
            if ($attachmentOk) {
                SessionManager::flash('success', __('saved_ok'));
            }
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/edit'));
        }
        $this->redirectAfterSave($id);
    }

    public function destroy(array $params): void
    {
        $this->guardManage();
        if (!$this->validateCsrf()) {
            SessionManager::flash('error', 'Invalid CSRF token');
            $this->redirect(rateb_url($this->routePrefix));
        }

        $id = (int) ($params['id'] ?? 0);
        try {
            $this->model->delete($id);
            (new AuditService())->log('delete', $this->entityName, $id);
            SessionManager::flash('success', __('delete') . ' OK');
        } catch (\Throwable $e) {
            SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
        }
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

    /** @return array<string, mixed>|null */
    protected function documentsViewData(int $id): ?array
    {
        $item = $this->model->find($id);
        if (!$item) {
            return null;
        }
        $entityType = $this->resolveDocumentEntityType();
        $companyId = $this->resolveDocumentCompanyId($item);
        $canManage = function_exists('rateb_can_manage_entity')
            ? rateb_can_manage_entity($this->permissionResourceKey())
            : true;

        $documents = [];
        if ($companyId > 0) {
            try {
                $documents = (new DocumentService())->listForEntity($entityType, $id, $companyId);
            } catch (\Throwable $e) {
                error_log('documentsViewData: ' . $e->getMessage());
                SessionManager::flash('error', \Rateb\App\Services\DatabaseErrorService::userMessage($e));
            }
        }

        return [
            'title' => __('entity_documents') . ' — ' . $this->recordLabel($item),
            'entityName' => $this->entityName,
            'item' => $item,
            'entityType' => $entityType,
            'entityId' => $id,
            'companyId' => $companyId,
            'documents' => $documents,
            'routePrefix' => $this->routePrefix,
            'backLabel' => __($this->entityName),
            'csrf' => Csrf::token(),
            'canManage' => $canManage,
        ];
    }

    protected function isDocumentsModalRequest(): bool
    {
        return (string) $this->input('rateb_doc_modal', '') === '1'
            || (($_SERVER['HTTP_X_RATEB_DOC_MODAL'] ?? '') === '1');
    }

    /** @param array<string, mixed> $item */
    protected function respondDocumentsAction(int $entityId, array $item, bool $success, string $message): void
    {
        if (!$this->isDocumentsModalRequest()) {
            return;
        }
        $companyId = $this->resolveDocumentCompanyId($item);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'count' => ($companyId > 0 && $entityId > 0)
                ? (new DocumentService())->countForEntity($this->resolveDocumentEntityType(), $entityId, $companyId)
                : 0,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function documentsPanel(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->documentsViewData($id);
        if ($data === null) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');
            echo '<p class="text-danger p-3 mb-0">' . htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8') . '</p>';
            return;
        }
        $data['modalMode'] = true;
        header('Content-Type: text/html; charset=UTF-8');
        \Rateb\App\Core\View::partial('entity-documents-panel', $data);
    }

    public function documents(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $id = (int) ($params['id'] ?? 0);
        $data = $this->documentsViewData($id);
        if ($data === null) {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], $this->layout());
            return;
        }
        $this->view('shared/entity-documents', $data, $this->layout());
    }

    public function storeDocument(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->guardManage();
        if (!$this->validateCsrf()) {
            $this->rejectDocumentsModal((string) __('invalid_request'));
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $id = (int) ($params['id'] ?? 0);
        $item = $this->model->find($id);
        if (!$item) {
            $this->rejectDocumentsModal((string) __('no_records'));
            SessionManager::flash('error', __('no_records'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $companyId = $this->resolveDocumentCompanyId($item);
        $title = trim((string) $this->input('doc_title', $this->recordLabel($item)));
        $upload = \Rateb\App\Helpers\EntityAttachment::handleOptionalFile(
            'entity_attachment',
            $companyId,
            $this->resolveDocumentEntityType(),
            $id,
            $title !== '' ? $title : __('attachment')
        );
        if (!($upload['success'] ?? false)) {
            $msg = (string) ($upload['error'] ?? __('upload_failed'));
            $this->respondDocumentsAction($id, $item, false, $msg);
            SessionManager::flash('error', $msg);
        } else {
            $this->respondDocumentsAction($id, $item, true, (string) __('file_uploaded'));
            SessionManager::flash('success', __('file_uploaded'));
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $id . '/documents'));
    }

    public function updateDocument(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->guardManage();
        if (!$this->validateCsrf()) {
            $this->rejectDocumentsModal((string) __('invalid_request'));
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $entityId = (int) ($params['id'] ?? 0);
        $docId = (int) ($params['docId'] ?? 0);
        $item = $this->model->find($entityId);
        if (!$item || $docId < 1) {
            $this->rejectDocumentsModal((string) __('no_records'));
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
            $msg = (string) ($result['error'] ?? __('upload_failed'));
            $this->respondDocumentsAction($entityId, $item, false, $msg);
            SessionManager::flash('error', $msg);
        } else {
            (new AuditService())->log('update_document', $this->entityName, $entityId, ['document_id' => $docId]);
            $this->respondDocumentsAction($entityId, $item, true, (string) __('file_updated'));
            SessionManager::flash('success', __('file_updated'));
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $entityId . '/documents'));
    }

    public function destroyDocument(array $params): void
    {
        if (function_exists('rateb_bootstrap_ops_tenant')) {
            rateb_bootstrap_ops_tenant();
        }
        $this->guardManage();
        if (!$this->validateCsrf()) {
            $this->rejectDocumentsModal((string) __('invalid_request'));
            SessionManager::flash('error', __('invalid_request'));
            $this->redirect(rateb_url($this->routePrefix));
        }
        $entityId = (int) ($params['id'] ?? 0);
        $docId = (int) ($params['docId'] ?? 0);
        $item = $this->model->find($entityId);
        if (!$item || $docId < 1) {
            $this->rejectDocumentsModal((string) __('no_records'));
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
            $this->respondDocumentsAction($entityId, $item, true, (string) __('file_deleted'));
            SessionManager::flash('success', __('file_deleted'));
        } else {
            $this->respondDocumentsAction($entityId, $item, false, (string) __('access_denied'));
            SessionManager::flash('error', __('access_denied'));
        }
        $this->redirect(rateb_url($this->routePrefix . '/' . $entityId . '/documents'));
    }

    /** @param array<string, mixed> $item */
    protected function resolveDocumentCompanyId(array $item): int
    {
        $companyId = (int) ($item['company_id'] ?? 0);
        if ($companyId < 1 && $this->entityName === 'companies') {
            $companyId = (int) ($item['id'] ?? 0);
        }
        if ($companyId < 1) {
            $companyId = (int) (TenantContext::companyId() ?? 0);
        }
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = (int) rateb_resolve_ops_company_id();
        }
        return $companyId;
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
            'supplier_comms' => 'supplier_communication',
            'supplier_classifications' => 'supplier_classification',
            'product_categories' => 'product_category',
            'inventory_batches' => 'inventory_batch',
            'inventory' => 'inventory',
            'warehouses' => 'warehouse',
            'assets' => 'asset',
            'contracts' => 'contract',
            'tenders' => 'tender',
            'rfq' => 'rfq',
            'quotations' => 'quotation',
            'suppliers' => 'supplier',
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
        foreach (['batch_no', 'title', 'name', 'item_name', 'request_no', 'order_no', 'contract_no', 'code', 'item_code', 'evaluation_no'] as $key) {
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
            $type = (string) ($field['type'] ?? 'text');
            $raw = trim((string) $this->input($name, ''));
            if ($type === 'fk') {
                $data[$name] = ($raw === '' || $raw === '0') ? null : (int) $raw;
                continue;
            }
            if ($type === 'number') {
                $data[$name] = $raw === '' ? null : $raw;
                continue;
            }
            $data[$name] = $raw;
        }
        return $data;
    }

    /** @param array<string, mixed> $record */
    protected function applyTenantFromRecord(array $record): void
    {
        $companyId = (int) ($record['company_id'] ?? 0);
        if ($companyId < 1) {
            return;
        }
        if ((int) (TenantContext::companyId() ?? 0) < 1) {
            TenantContext::setCompanyId($companyId);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $record
     */
    protected function inheritTenantFromRecord(array &$data, array $record): void
    {
        $companyId = (int) ($record['company_id'] ?? 0);
        if ($companyId < 1) {
            return;
        }
        if ((int) ($data['company_id'] ?? 0) < 1) {
            $data['company_id'] = $companyId;
        }
        $this->applyTenantFromRecord($record);
    }

    /** @param array<string, mixed> $data */
    protected function ensureTenantCompanyForWrite(array &$data, ?string $failRedirect = null): void
    {
        if (!$this->model->isTenantScoped()) {
            return;
        }

        // Platform template rows (e.g. global chart of accounts) use explicit NULL for super admin.
        if (array_key_exists('company_id', $data) && $data['company_id'] === null && rateb_is_super_admin()) {
            return;
        }

        $companyId = (int) ($data['company_id'] ?? 0);
        if ($companyId < 1) {
            $companyId = (int) (TenantContext::companyId() ?? 0);
        }
        if ($companyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
            $companyId = rateb_resolve_ops_company_id();
        }
        if ($companyId < 1) {
            SessionManager::flash('error', __('select_company_ops'));
            $this->redirect($failRedirect ?? rateb_url($this->routePrefix . '/create'));
        }
        if (function_exists('rateb_ops_company_exists') && !rateb_ops_company_exists($companyId)) {
            if (function_exists('rateb_clear_ops_company_session')) {
                rateb_clear_ops_company_session();
            }
            SessionManager::flash('error', __('company_not_found_ops'));
            $this->redirect($failRedirect ?? rateb_url($this->routePrefix . '/create'));
        }

        $data['company_id'] = $companyId;
        TenantContext::setCompanyId($companyId);
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
