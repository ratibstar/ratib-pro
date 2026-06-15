<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

use Rateb\App\Core\TenantContext;
use Rateb\App\Services\DocumentService;

final class EntityAttachment
{
    /** @return array{success:bool,path?:string,error?:string} */
    public static function handleOptionalFile(
        string $inputName,
        int $companyId,
        string $entityType,
        int $entityId,
        string $title = ''
    ): array {
        if (!isset($_FILES[$inputName]) || ($_FILES[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => true];
        }
        if ((int) ($_FILES[$inputName]['size'] ?? 0) < 1) {
            return ['success' => true];
        }
        if ($companyId < 1) {
            return ['success' => false, 'error' => __('billing_company_required')];
        }

        $prev = TenantContext::companyId();
        TenantContext::setCompanyId($companyId);
        try {
            return (new DocumentService())->storeUpload($_FILES[$inputName], $entityType, $entityId, $title);
        } finally {
            TenantContext::setCompanyId($prev);
        }
    }

    /**
     * @return array{success:bool,uploaded:int,error?:string}
     */
    public static function handleMultipleFiles(
        string $inputName,
        int $companyId,
        string $entityType,
        int $entityId,
        int $maxFiles = 5,
        string $title = ''
    ): array {
        if (!isset($_FILES[$inputName])) {
            return ['success' => true, 'uploaded' => 0];
        }
        $files = $_FILES[$inputName];
        if (!is_array($files['name'] ?? null)) {
            $single = self::handleOptionalFile($inputName, $companyId, $entityType, $entityId, $title);
            return [
                'success' => (bool) ($single['success'] ?? false),
                'uploaded' => !empty($single['path']) ? 1 : 0,
                'error' => $single['error'] ?? null,
            ];
        }
        $existing = (new DocumentService())->countForEntity($entityType, $entityId, $companyId);
        $names = $files['name'];
        $uploaded = 0;
        $prev = TenantContext::companyId();
        TenantContext::setCompanyId($companyId);
        try {
            $svc = new DocumentService();
            foreach (array_keys($names) as $i) {
                if ($existing + $uploaded >= $maxFiles) {
                    break;
                }
                if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $file = [
                    'name' => $files['name'][$i] ?? '',
                    'type' => $files['type'][$i] ?? '',
                    'tmp_name' => $files['tmp_name'][$i] ?? '',
                    'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $files['size'][$i] ?? 0,
                ];
                $result = $svc->storeUpload($file, $entityType, $entityId, $title !== '' ? $title : (string) ($file['name'] ?? ''));
                if (!($result['success'] ?? false)) {
                    return ['success' => false, 'uploaded' => $uploaded, 'error' => (string) ($result['error'] ?? __('upload_failed'))];
                }
                if (!empty($result['path'])) {
                    $uploaded++;
                }
            }
        } finally {
            TenantContext::setCompanyId($prev);
        }
        return ['success' => true, 'uploaded' => $uploaded];
    }

    /** @return array{success:bool,path?:string,error?:string} */
    public static function saveForEntity(
        string $inputName,
        int $companyId,
        string $entityType,
        int $entityId,
        string $title,
        callable $onPath
    ): array {
        $upload = self::handleOptionalFile($inputName, $companyId, $entityType, $entityId, $title);
        if (!($upload['success'] ?? false)) {
            return $upload;
        }
        if (!empty($upload['path'])) {
            $onPath((string) $upload['path']);
        }
        return $upload;
    }
}
