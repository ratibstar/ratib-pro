<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Contracts;

use Rateb\App\Pos\Domain\V2\Customer\PosV2CustomerScope;
use Rateb\App\Pos\DTO\V2\Customer\CustomerSearchRequest;
use Rateb\App\Pos\DTO\V2\Customer\CustomerSearchResponse;
use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;

/** Customer read/write port (V1 session + CRM bridge, T10). */
interface PosV2CustomerPortInterface
{
    public function search(PosV2CustomerScope $scope, CustomerSearchRequest $request): CustomerSearchResponse;

    public function findById(PosV2CustomerScope $scope, int $customerId): ?PosV2CustomerSummaryDto;

    public function getAttached(): ?PosV2CustomerSummaryDto;

    public function attach(PosV2CustomerScope $scope, int $customerId): void;

    public function detach(): void;
}
