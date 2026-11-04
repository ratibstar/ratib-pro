<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use Rateb\App\Core\TenantContext;

/** Permission and tenant checks for Automotive EPC. Deny by default. */
final class AutomotiveGate
{
    public function assert(string $slug): void
    {
        if (!function_exists('rateb_can') || !rateb_can($slug)) {
            throw new \RuntimeException('forbidden');
        }
    }

    public function companyId(): int
    {
        $companyId = (int) (TenantContext::companyId() ?? 0);
        if ($companyId < 1 && isset($_SESSION['rateb_company_id'])) {
            $companyId = (int) $_SESSION['rateb_company_id'];
        }
        if ($companyId < 1) {
            throw new \RuntimeException('forbidden');
        }
        return $companyId;
    }
}
