<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\Integration;

use Rateb\App\Logistics\Contracts\ApiEmployeeResolver;
use Rateb\App\Services\HrEssEmployeeResolverService;

final class ErpApiEmployeeResolver implements ApiEmployeeResolver
{
    public function __construct(private HrEssEmployeeResolverService $resolver = new HrEssEmployeeResolverService())
    {
    }

    public function resolveCurrentEmployee(?int $userId, ?int $companyId): array
    {
        return $this->resolver->resolveCurrentEmployee($userId, $companyId);
    }
}
