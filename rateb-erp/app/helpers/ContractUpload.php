<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

use Rateb\App\Core\TenantContext;
use Rateb\App\Services\DocumentService;

final class ContractUpload
{
    /** @return array{success:bool,path?:string,error?:string} */
    public static function handleOptionalFile(int $companyId, int $contractId): array
    {
        if (!isset($_FILES['contract_file']) || ($_FILES['contract_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['success' => true];
        }

        $prev = TenantContext::companyId();
        TenantContext::setCompanyId($companyId);
        try {
            return (new DocumentService())->storeUpload($_FILES['contract_file'], 'contract', $contractId, 'Contract attachment');
        } finally {
            TenantContext::setCompanyId($prev);
        }
    }
}
