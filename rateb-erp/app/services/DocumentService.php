<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Helpers\StorageHelper;

final class DocumentService
{
    /** @var array<int, string> */
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];

    /** @var array<int, string> */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/pjpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.ms-word',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
        'application/x-zip-compressed',
        'application/CDFV2',
        'application/x-ole-storage',
    ];

    /** @var array<string, string> */
    private const EXTENSION_MIMES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /** @return array{success:bool,path?:string,error?:string} */
    public function storeUpload(array $file, string $entityType, int $entityId, string $title = ''): array
    {
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError === UPLOAD_ERR_NO_FILE) {
            return ['success' => true];
        }
        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->uploadErrorMessage($uploadError)];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size < 1) {
            return ['success' => true];
        }
        if ($size > 10 * 1024 * 1024) {
            return ['success' => false, 'error' => __('file_too_large')];
        }

        $companyId = TenantContext::companyId();
        if ($companyId === null && !TenantContext::isSuperAdmin()) {
            return ['success' => false, 'error' => __('billing_company_required')];
        }
        if ($companyId !== null && !(new PlanLimitService())->canUploadBytes((int) $companyId, $size)) {
            return ['success' => false, 'error' => __('storage_limit_exceeded')];
        }
        if ($companyId === null) {
            return ['success' => false, 'error' => __('billing_company_required')];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['success' => false, 'error' => __('upload_failed')];
        }

        $originalName = (string) ($file['name'] ?? 'file');
        $mime = $this->resolveMimeType($tmpName, $originalName);
        if ($mime === null) {
            return ['success' => false, 'error' => __('file_type_not_allowed')];
        }

        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
        $subdir = 'company_' . $companyId . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $entityType);
        $uploadsRoot = StorageHelper::uploadsRoot();
        $destDir = $uploadsRoot . '/' . $subdir;
        $dirError = StorageHelper::ensureWritableDir($destDir);
        if ($dirError !== null) {
            return ['success' => false, 'error' => $dirError];
        }

        $relative = 'uploads/' . $subdir . '/' . $safeName;
        $full = $destDir . '/' . $safeName;
        if (!move_uploaded_file($tmpName, $full)) {
            return ['success' => false, 'error' => __('upload_save_failed')];
        }

        $displayName = $this->safeStoredName($originalName, $safeName);
        $docTitle = $title !== '' ? $title : $displayName;

        try {
            $db = \Rateb\App\Core\Database::connection();
            $db->prepare(
                'INSERT INTO rateb_documents (company_id, entity_type, entity_id, title, file_name, file_path, mime_type, file_size, uploaded_by)
                 VALUES (:cid, :et, :eid, :title, :fn, :fp, :mime, :sz, :uid)'
            )->execute([
                'cid' => (int) $companyId,
                'et' => $entityType,
                'eid' => $entityId,
                'title' => $docTitle,
                'fn' => $displayName,
                'fp' => $relative,
                'mime' => $mime,
                'sz' => $size,
                'uid' => SessionManager::get('rateb_user_id'),
            ]);
        } catch (\Throwable $e) {
            @unlink($full);
            return ['success' => false, 'error' => DatabaseErrorService::userMessage($e)];
        }

        return ['success' => true, 'path' => $relative];
    }

    /**
     * Store generated binary content (e.g. issued letter PDF) into rateb_documents.
     *
     * @return array{success:bool,document_id?:int,path?:string,error?:string}
     */
    public function storeGeneratedBytes(
        int $companyId,
        string $entityType,
        int $entityId,
        string $bytes,
        string $fileName,
        string $mimeType,
        string $title = ''
    ): array {
        if ($companyId < 1 || $entityId < 1 || $bytes === '') {
            return ['success' => false, 'error' => __('invalid_request')];
        }
        $size = strlen($bytes);
        if ($size > 10 * 1024 * 1024) {
            return ['success' => false, 'error' => __('file_too_large')];
        }
        if (!(new PlanLimitService())->canUploadBytes($companyId, $size)) {
            return ['success' => false, 'error' => __('storage_limit_exceeded')];
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            $ext = 'pdf';
            $fileName .= '.pdf';
        }
        $safeName = bin2hex(random_bytes(8)) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
        $subdir = 'company_' . $companyId . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $entityType);
        $uploadsRoot = StorageHelper::uploadsRoot();
        $destDir = $uploadsRoot . '/' . $subdir;
        $dirError = StorageHelper::ensureWritableDir($destDir);
        if ($dirError !== null) {
            return ['success' => false, 'error' => $dirError];
        }
        $relative = 'uploads/' . $subdir . '/' . $safeName;
        $full = $destDir . '/' . $safeName;
        if (file_put_contents($full, $bytes) === false) {
            return ['success' => false, 'error' => __('upload_save_failed')];
        }

        $displayName = $this->safeStoredName($fileName, $safeName);
        $docTitle = $title !== '' ? $title : $displayName;
        try {
            $db = \Rateb\App\Core\Database::connection();
            $db->prepare(
                'INSERT INTO rateb_documents (company_id, entity_type, entity_id, title, file_name, file_path, mime_type, file_size, uploaded_by)
                 VALUES (:cid, :et, :eid, :title, :fn, :fp, :mime, :sz, :uid)'
            )->execute([
                'cid' => $companyId,
                'et' => $entityType,
                'eid' => $entityId,
                'title' => $docTitle,
                'fn' => $displayName,
                'fp' => $relative,
                'mime' => $mimeType !== '' ? $mimeType : 'application/pdf',
                'sz' => $size,
                'uid' => SessionManager::get('rateb_user_id'),
            ]);
            $docId = (int) $db->lastInsertId();
        } catch (\Throwable $e) {
            @unlink($full);
            return ['success' => false, 'error' => DatabaseErrorService::userMessage($e)];
        }

        return ['success' => true, 'document_id' => $docId, 'path' => $relative];
    }

    /** @return array<int, array<string, mixed>> */
    public function listForEntity(string $entityType, int $entityId, int $companyId): array
    {
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM rateb_documents WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid ORDER BY id DESC'
        );
        $stmt->execute(['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]);
        return $stmt->fetchAll();
    }

    public function countForEntity(string $entityType, int $entityId, int $companyId): int
    {
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare(
            'SELECT COUNT(*) AS c FROM rateb_documents WHERE company_id = :cid AND entity_type = :et AND entity_id = :eid'
        );
        $stmt->execute(['cid' => $companyId, 'et' => $entityType, 'eid' => $entityId]);
        return (int) ($stmt->fetch()['c'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare('SELECT * FROM rateb_documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function latestImageForEntity(int $companyId, string $entityType, int $entityId): ?array
    {
        if ($companyId < 1 || $entityId < 1) {
            return null;
        }
        foreach ($this->listForEntity($entityType, $entityId, $companyId) as $row) {
            $mime = (string) ($row['mime_type'] ?? '');
            if ($mime !== '' && str_starts_with($mime, 'image/')) {
                return $row;
            }
            $name = (string) ($row['file_name'] ?? '');
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                return $row;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function latestForEntity(int $companyId, string $entityType, int $entityId): ?array
    {
        if ($companyId < 1 || $entityId < 1) {
            return null;
        }
        $rows = $this->listForEntity($entityType, $entityId, $companyId);
        return $rows[0] ?? null;
    }

    public function sendDownload(int $documentId): void
    {
        $this->sendFile($documentId, false);
    }

    public function sendView(int $documentId): void
    {
        $this->sendFile($documentId, true);
    }

    /** @return array{success:bool,error?:string} */
    public function updateDocument(int $documentId, string $title, ?array $file = null): array
    {
        $doc = $this->findById($documentId);
        if (!$doc) {
            return ['success' => false, 'error' => __('no_records')];
        }
        if (!$this->canDownload($doc)) {
            return ['success' => false, 'error' => __('access_denied')];
        }

        $title = trim($title);
        if ($title === '') {
            return ['success' => false, 'error' => __('title_required')];
        }

        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('UPDATE rateb_documents SET title = :title WHERE id = :id')
            ->execute(['title' => $title, 'id' => $documentId]);

        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $replace = $this->replaceDocumentFile($documentId, $file, $doc);
            if (!($replace['success'] ?? false)) {
                return $replace;
            }
        }

        return ['success' => true];
    }

    /** @param array<string, mixed> $doc */
    /** @return array{success:bool,error?:string} */
    private function replaceDocumentFile(int $documentId, array $file, array $doc): array
    {
        $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($uploadError !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->uploadErrorMessage($uploadError)];
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size < 1) {
            return ['success' => false, 'error' => __('upload_failed')];
        }
        if ($size > 10 * 1024 * 1024) {
            return ['success' => false, 'error' => __('file_too_large')];
        }

        $companyId = (int) ($doc['company_id'] ?? 0);
        if ($companyId < 1) {
            return ['success' => false, 'error' => __('billing_company_required')];
        }
        if (!(new PlanLimitService())->canUploadBytes($companyId, $size)) {
            return ['success' => false, 'error' => __('storage_limit_exceeded')];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['success' => false, 'error' => __('upload_failed')];
        }

        $originalName = (string) ($file['name'] ?? 'file');
        $mime = $this->resolveMimeType($tmpName, $originalName);
        if ($mime === null) {
            return ['success' => false, 'error' => __('file_type_not_allowed')];
        }

        $entityType = (string) ($doc['entity_type'] ?? '');
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext) : '');
        $subdir = 'company_' . $companyId . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $entityType);
        $destDir = StorageHelper::uploadsRoot() . '/' . $subdir;
        $dirError = StorageHelper::ensureWritableDir($destDir);
        if ($dirError !== null) {
            return ['success' => false, 'error' => $dirError];
        }

        $relative = 'uploads/' . $subdir . '/' . $safeName;
        $full = $destDir . '/' . $safeName;
        if (!move_uploaded_file($tmpName, $full)) {
            return ['success' => false, 'error' => __('upload_save_failed')];
        }

        $oldPath = StorageHelper::resolveFilePath((string) ($doc['file_path'] ?? ''));
        $db = \Rateb\App\Core\Database::connection();
        $displayName = $this->safeStoredName($originalName, $safeName);
        $db->prepare(
            'UPDATE rateb_documents SET file_name = :fn, file_path = :fp, mime_type = :mime, file_size = :sz WHERE id = :id'
        )->execute([
            'fn' => $displayName,
            'fp' => $relative,
            'mime' => $mime,
            'sz' => $size,
            'id' => $documentId,
        ]);

        if ($oldPath !== '' && is_file($oldPath)) {
            @unlink($oldPath);
        }

        return ['success' => true];
    }

    public function deleteDocument(int $documentId): bool
    {
        $doc = $this->findById($documentId);
        if (!$doc || !$this->canDownload($doc)) {
            return false;
        }
        $path = StorageHelper::resolveFilePath((string) ($doc['file_path'] ?? ''));
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }
        $db = \Rateb\App\Core\Database::connection();
        $db->prepare('DELETE FROM rateb_documents WHERE id = :id')->execute(['id' => $documentId]);

        return true;
    }

    /** @param array<string, mixed> $doc */
    public function belongsToEntity(array $doc, string $entityType, int $entityId): bool
    {
        return (string) ($doc['entity_type'] ?? '') === $entityType
            && (int) ($doc['entity_id'] ?? 0) === $entityId;
    }

    private function sendFile(int $documentId, bool $inline): void
    {
        $doc = $this->findById($documentId);
        if (!$doc) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        if (!$this->canDownload($doc)) {
            http_response_code(403);
            echo __('access_denied');
            return;
        }
        $relative = (string) ($doc['file_path'] ?? '');
        if ($relative === '' || strpos($relative, '..') !== false) {
            http_response_code(404);
            echo 'Not found';
            return;
        }
        $full = StorageHelper::resolveFilePath($relative);
        if ($full === '' || !is_file($full)) {
            http_response_code(404);
            echo 'File missing';
            return;
        }
        $mime = (string) ($doc['mime_type'] ?? 'application/octet-stream');
        $name = (string) ($doc['file_name'] ?? basename($full));
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Transfer-Encoding: binary');
        $disposition = $inline ? 'inline' : 'attachment';
        $asciiName = $this->safeFilename($name);
        $utfName = rawurlencode($name);
        header(
            'Content-Disposition: ' . $disposition
            . '; filename="' . $asciiName . '"'
            . "; filename*=UTF-8''" . $utfName
        );
        header('Content-Length: ' . (string) filesize($full));
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($full);
        exit;
    }

    /** @param array<string, mixed> $doc */
    private function canDownload(array $doc): bool
    {
        if (!\Rateb\App\Core\Auth::check()) {
            return false;
        }
        if (TenantContext::isSuperAdmin()) {
            return true;
        }
        $sessionCompany = (int) SessionManager::get('rateb_company_id', 0);
        if ($sessionCompany < 1 || $sessionCompany !== (int) ($doc['company_id'] ?? 0)) {
            return false;
        }
        if (function_exists('rateb_can_view_entity') && rateb_can_view_entity('documents')) {
            return true;
        }
        $resource = $this->resourceForEntityType((string) ($doc['entity_type'] ?? ''));
        if ($resource !== '' && function_exists('rateb_can_view_entity')) {
            return rateb_can_view_entity($resource);
        }
        return function_exists('rateb_can') && rateb_can('documents.view');
    }

    private function resourceForEntityType(string $entityType): string
    {
        static $map = [
            'purchase_request' => 'purchase-requests',
            'purchase_order' => 'purchase-orders',
            'supplier_evaluation' => 'supplier-evaluations',
            'supplier_communication' => 'supplier-comms',
            'supplier_classification' => 'supplier-classifications',
            'product_category' => 'product-categories',
            'inventory_batch' => 'inventory-batches',
            'inventory_audit' => 'inventory-audits',
            'stock_movement' => 'stock-movements',
            'warehouse_transfer' => 'warehouse-transfers',
            'medical_device' => 'medical-devices',
            'chart_of_account' => 'chart-of-accounts',
            'journal_entry' => 'journal-entries',
            'cash_voucher' => 'cash-vouchers',
            'bank_account' => 'bank-accounts',
            'cost_center' => 'cost-centers',
            'fiscal_period' => 'fiscal-periods',
            'contract' => 'contracts',
            'asset' => 'assets',
            'supplier' => 'suppliers',
            'inventory' => 'inventory',
            'warehouse' => 'warehouses',
            'rfq' => 'rfq',
            'quotation' => 'quotations',
            'tender' => 'tenders',
            'invoice' => 'invoices',
            'payment' => 'payments',
            'asset_depreciation' => 'asset-depreciation',
            'supplier_payment' => 'supplier-payments',
        ];
        if (isset($map[$entityType])) {
            return $map[$entityType];
        }
        if ($entityType === '') {
            return '';
        }
        return str_replace('_', '-', $entityType);
    }

    private function safeFilename(string $name): string
    {
        $name = preg_replace('/[^\w\.\-]+/u', '_', $name) ?? 'file';
        return $name !== '' ? $name : 'file';
    }

    private function uploadErrorMessage(int $code): string
    {
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return __('file_too_large');
        }
        if ($code === UPLOAD_ERR_PARTIAL) {
            return __('upload_failed');
        }
        return __('upload_failed');
    }

    private function resolveMimeType(string $tmpName, string $originalName): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detected = strtolower(trim((string) ($finfo->file($tmpName) ?: '')));
        if ($detected !== '' && in_array($detected, self::ALLOWED_MIMES, true)) {
            return $detected;
        }

        $ext = strtolower(preg_replace('/[^a-z0-9]/', '', pathinfo($originalName, PATHINFO_EXTENSION)) ?? '');
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }

        $fallbackMime = self::EXTENSION_MIMES[$ext] ?? '';
        if ($fallbackMime === '') {
            return null;
        }

        $generic = ['application/octet-stream', 'application/zip', 'application/x-zip-compressed', ''];
        if ($detected === '' || in_array($detected, $generic, true)) {
            return $fallbackMime;
        }

        if ($ext === 'docx' && str_contains($detected, 'zip')) {
            return $fallbackMime;
        }
        if ($ext === 'doc' && (str_contains($detected, 'msword') || str_contains($detected, 'ole') || str_contains($detected, 'cdf'))) {
            return $fallbackMime;
        }

        return null;
    }

    private function safeStoredName(string $originalName, string $fallback): string
    {
        $name = trim($originalName);
        if ($name === '') {
            return $fallback;
        }
        if (!mb_check_encoding($name, 'UTF-8')) {
            return $fallback;
        }
        return mb_substr($name, 0, 255);
    }
}
