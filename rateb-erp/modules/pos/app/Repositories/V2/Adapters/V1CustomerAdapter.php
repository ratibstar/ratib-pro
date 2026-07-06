<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Repositories\V2\Adapters;

use Rateb\App\Pos\Domain\V2\Customer\Exceptions\PosV2CustomerNotFoundException;
use Rateb\App\Pos\Domain\V2\Customer\PosV2CustomerScope;
use Rateb\App\Pos\DTO\V2\Customer\CustomerSearchRequest;
use Rateb\App\Pos\DTO\V2\Customer\CustomerSearchResponse;
use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CustomerPortInterface;
use Rateb\App\Pos\Services\Bridge\PosCustomerBridgeService;
use Rateb\App\Pos\Services\PosSessionService;
use Rateb\App\Pos\Services\V2\Customer\PosV2CustomerMapper;

/** V1 customer bridge + session adapter (no V1 modifications, T10). */
final class V1CustomerAdapter implements PosV2CustomerPortInterface
{
    public function __construct(
        private readonly PosCustomerBridgeService $bridge = new PosCustomerBridgeService(),
        private readonly PosSessionService $session = new PosSessionService(),
        private readonly PosV2CustomerMapper $mapper = new PosV2CustomerMapper(),
    ) {
    }

    public function search(PosV2CustomerScope $scope, CustomerSearchRequest $request): CustomerSearchResponse
    {
        if ($scope->companyId < 1) {
            return new CustomerSearchResponse([]);
        }

        $rows = $this->bridge->search($request->query, $request->limit);
        $customers = [];

        foreach ($rows as $row) {
            $dto = $this->mapper->fromV1Customer($row);
            if ($dto !== null) {
                $customers[] = $dto;
            }
        }

        return new CustomerSearchResponse($customers);
    }

    public function findById(PosV2CustomerScope $scope, int $customerId): ?PosV2CustomerSummaryDto
    {
        if ($scope->companyId < 1 || $customerId < 1) {
            return null;
        }

        $row = $this->bridge->findById($customerId);

        return $row !== null ? $this->mapper->fromV1Customer($row) : null;
    }

    public function getAttached(): ?PosV2CustomerSummaryDto
    {
        $customer = $this->session->getCustomer();

        return $customer !== null ? $this->mapper->fromV1Customer($customer) : null;
    }

    public function attach(PosV2CustomerScope $scope, int $customerId): void
    {
        $row = $this->bridge->findById($customerId);
        if ($row === null) {
            throw new PosV2CustomerNotFoundException(
                'CUSTOMER_NOT_FOUND',
                sprintf('Customer %d was not found.', $customerId),
                $customerId,
            );
        }

        $this->session->setCustomer($row);
    }

    public function detach(): void
    {
        $this->session->setCustomer(null);
    }
}
