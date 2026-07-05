<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services\Bridge;

use Rateb\App\Services\ZatcaService;

/** VAT/ZATCA bridge — receipt QR via ERP ZatcaService. */
final class PosZatcaBridgeService
{
    public function zatcaService(): ZatcaService
    {
        return new ZatcaService();
    }

    /** @param array<string, mixed> $receipt */
    public function receiptQrBase64(int $companyId, array $receipt): string
    {
        // Phase 5+: ZatcaService::generateQrBase64()
        return '';
    }
}
