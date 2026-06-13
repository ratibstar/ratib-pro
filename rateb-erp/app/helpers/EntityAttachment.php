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
