<?php
declare(strict_types=1);

namespace Rateb\App\Helpers;

final class ContractUpload
{
    /** @return array{success:bool,path?:string,error?:string} */
    public static function handleOptionalFile(int $companyId, int $contractId): array
    {
        return EntityAttachment::handleOptionalFile('contract_file', $companyId, 'contract', $contractId, __('contract_attachment'));
    }
}
