<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\Auth;
use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Helpers\StorageHelper;
use Rateb\App\Models\Inventory;

/** Serves inventory product images for ERP lists and POS tiles. */
final class InventoryImageService
{
    /** @var array<int, string> */
    private const IMAGE_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/pjpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    public function imageUrl(int $inventoryId): string
    {
        if ($inventoryId < 1) {
            return '';
        }

        return rateb_app_url('inventory/' . $inventoryId . '/image');
    }

    /** @param list<int> $inventoryIds @return array<int, true> */
    public function inventoryIdsWithImageDocs(int $companyId, array $inventoryIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $inventoryIds), static fn (int $id): bool => $id > 0)));
        if ($companyId < 1 || $ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = 'SELECT entity_id, MAX(id) AS doc_id
                FROM rateb_documents
                WHERE company_id = ? AND entity_type = ? AND entity_id IN (' . $placeholders . ")
                  AND mime_type LIKE 'image/%'
                GROUP BY entity_id";
        $db = \Rateb\App\Core\Database::connection();
        $stmt = $db->prepare($sql);
        $params = array_merge([$companyId, 'inventory'], $ids);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [] as $row) {
            $eid = (int) ($row['entity_id'] ?? 0);
            if ($eid > 0) {
                $out[$eid] = true;
            }
        }

        return $out;
    }

    public function hasImage(array $row, bool $hasImageDoc = false): bool
    {
        $doc = trim((string) ($row['document_path'] ?? ''));
        if ($doc !== '') {
            if (preg_match('#^https?://#i', $doc)) {
                return true;
            }
            if ($this->isImageRelativePath($doc)) {
                return true;
            }
        }

        return $hasImageDoc;
    }

    public function resolveCatalogImageUrl(int $inventoryId, array $row, bool $hasImageDoc = false): string
    {
        $doc = trim((string) ($row['document_path'] ?? ''));
        if ($doc !== '' && preg_match('#^https?://#i', $doc)) {
            return $doc;
        }
        if ($inventoryId > 0 && $this->hasImage($row, $hasImageDoc)) {
            return $this->imageUrl($inventoryId);
        }

        return '';
    }

    public function sendImage(int $inventoryId): void
    {
        $row = (new Inventory())->find($inventoryId);
        if (!$row) {
            $this->notFound();
        }
        if (!$this->canViewImage($row)) {
            http_response_code(403);
            echo __('access_denied');
            return;
        }

        $relative = trim((string) ($row['document_path'] ?? ''));
        if ($relative === '' || !$this->isImageRelativePath($relative)) {
            $companyId = (int) ($row['company_id'] ?? 0);
            $doc = (new DocumentService())->latestImageForEntity($companyId, 'inventory', $inventoryId);
            $relative = trim((string) ($doc['file_path'] ?? ''));
        }
        if ($relative === '' || strpos($relative, '..') !== false) {
            $this->notFound();
        }

        $full = StorageHelper::resolveFilePath($relative);
        if ($full === '' || !is_file($full)) {
            http_response_code(404);
            echo 'File missing';
            return;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($full) ?: 'application/octet-stream';
        if (!in_array($mime, self::IMAGE_MIMES, true)) {
            http_response_code(403);
            echo __('file_type_not_allowed');
            return;
        }

        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="product-' . $inventoryId . '"');
        header('Content-Length: ' . (string) filesize($full));
        header('Cache-Control: private, max-age=86400');
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        readfile($full);
        exit;
    }

    /** @param array<string, mixed> $row */
    private function canViewImage(array $row): bool
    {
        if (!Auth::check()) {
            return false;
        }
        if (TenantContext::isSuperAdmin()) {
            return true;
        }
        $sessionCompany = (int) SessionManager::get('rateb_company_id', 0);
        if ($sessionCompany < 1 || $sessionCompany !== (int) ($row['company_id'] ?? 0)) {
            $opsCompany = function_exists('rateb_resolve_ops_company_id') ? (int) rateb_resolve_ops_company_id() : 0;
            if ($opsCompany < 1 || $opsCompany !== (int) ($row['company_id'] ?? 0)) {
                return false;
            }
        }
        if (function_exists('rateb_can_view_entity') && rateb_can_view_entity('inventory')) {
            return true;
        }
        if (function_exists('rateb_can_view_entity') && rateb_can_view_entity('pos/register')) {
            return true;
        }

        return function_exists('rateb_can') && rateb_can('pos.register');
    }

    private function isImageRelativePath(string $relative): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || strpos($relative, '..') !== false) {
            return false;
        }
        $ext = strtolower(pathinfo($relative, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo 'Not found';
        exit;
    }
}
