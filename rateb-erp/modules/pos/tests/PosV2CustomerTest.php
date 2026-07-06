<?php

declare(strict_types=1);

/**
 * POS V2 customer tests (T10).
 *
 * Run: php modules/pos/tests/run-customer-tests.php
 */

use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Domain\V2\Customer\Exceptions\PosV2CustomerNotFoundException;
use Rateb\App\Pos\Domain\V2\Customer\Exceptions\PosV2CustomerValidationException;
use Rateb\App\Pos\Domain\V2\Customer\PosV2CustomerScope;
use Rateb\App\Pos\Domain\V2\Cart\PosV2CartScope;
use Rateb\App\Pos\DTO\V2\Cart\CartResponse;
use Rateb\App\Pos\DTO\V2\Cart\PosV2CartTotalsDto;
use Rateb\App\Pos\DTO\V2\Catalog\PosV2MoneyDto;
use Rateb\App\Pos\DTO\V2\Context\PosV2CashierContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2FeatureFlagsContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;
use Rateb\App\Pos\DTO\V2\Customer\AttachCustomerRequest;
use Rateb\App\Pos\DTO\V2\Customer\CustomerSearchRequest;
use Rateb\App\Pos\DTO\V2\Customer\CustomerSearchResponse;
use Rateb\App\Pos\DTO\V2\Customer\PosV2CustomerSummaryDto;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CartPortInterface;
use Rateb\App\Pos\Repositories\V2\Contracts\PosV2CustomerPortInterface;
use Rateb\App\Pos\Services\V2\Cart\PosV2CartAccessValidator;
use Rateb\App\Pos\Services\V2\Customer\PosV2CustomerAccessValidator;
use Rateb\App\Pos\Services\V2\Customer\PosV2CustomerMapper;
use Rateb\App\Pos\UseCases\V2\Cart\GetCartUseCase;
use Rateb\App\Pos\UseCases\V2\Customer\AttachCustomerUseCase;
use Rateb\App\Pos\UseCases\V2\Customer\GetAttachedCustomerUseCase;
use Rateb\App\Pos\UseCases\V2\Customer\GetCustomerUseCase;
use Rateb\App\Pos\UseCases\V2\Customer\RemoveCustomerUseCase;
use Rateb\App\Pos\UseCases\V2\Customer\SearchCustomerUseCase;

final class PosV2CustomerTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testCustomerSearchRequestValidation();
        $this->testCustomerMapper();
        $this->testSearchCustomerUseCase();
        $this->testAttachCustomerUseCase();
        $this->testReplaceCustomerUseCase();
        $this->testRemoveCustomerUseCase();
        $this->testBootstrapCustomerShape();
        $this->testInvalidCustomerRequest();
        $this->testCustomerNotFound();
        $this->testCustomerSearchSuccessEnvelope();

        return $this->results;
    }

    private function testCustomerSearchRequestValidation(): void
    {
        try {
            CustomerSearchRequest::fromQueryParams(['query' => 'a']);
            $this->record('customer search rejects short query', false, 'expected exception');
        } catch (PosV2CustomerValidationException) {
            $this->record('customer search rejects short query', true, '');
        }

        $request = CustomerSearchRequest::fromQueryParams(['query' => 'ahmed', 'limit' => '15']);
        $ok = $request->query === 'ahmed' && $request->limit === 15;
        $this->record('customer search parses query params', $ok, 'expected parsed request');
    }

    private function testCustomerMapper(): void
    {
        $mapper = new PosV2CustomerMapper();
        $dto = $mapper->fromV1Customer([
            'id' => 12,
            'name' => 'Ahmed Ali',
            'phone' => '0500000000',
            'price_group_id' => 3,
        ]);

        $ok = $dto !== null
            && $dto->id === 12
            && $dto->name === 'Ahmed Ali'
            && $dto->phone === '0500000000';

        $this->record('customer mapper strips V1-only fields', $ok, 'expected summary dto');
    }

    private function testSearchCustomerUseCase(): void
    {
        $port = new InMemoryCustomerPort();
        $port->seed([
            new PosV2CustomerSummaryDto(1, 'Walk-in A', '0501111111'),
            new PosV2CustomerSummaryDto(2, 'Walk-in B', '0502222222'),
        ]);

        $result = (new SearchCustomerUseCase(
            new PosV2CustomerAccessValidator(),
            $port,
        ))->execute(
            $this->requestContext(),
            new CustomerSearchRequest('walk', 10),
        );

        $ok = count($result->customers) === 2;
        $this->record('search customer use case', $ok, 'expected two customers');
    }

    private function testAttachCustomerUseCase(): void
    {
        $customerPort = new InMemoryCustomerPort();
        $customerPort->seed([new PosV2CustomerSummaryDto(5, 'Sara', '0503333333')]);
        $cartPort = new InMemoryCustomerCartPort($customerPort);

        $useCase = new AttachCustomerUseCase(
            new PosV2CustomerAccessValidator(),
            $customerPort,
            new GetCartUseCase(new PosV2CartAccessValidator(), $cartPort),
        );

        $result = $useCase->execute(
            $this->requestContext(),
            new AttachCustomerRequest(5),
        );

        $ok = $customerPort->attachedId() === 5
            && $result->customer?->id === 5
            && $result->customer->name === 'Sara';

        $this->record('attach customer use case', $ok, 'expected attached customer on cart');
    }

    private function testReplaceCustomerUseCase(): void
    {
        $customerPort = new InMemoryCustomerPort();
        $customerPort->seed([
            new PosV2CustomerSummaryDto(5, 'Sara', '0503333333'),
            new PosV2CustomerSummaryDto(8, 'Noura', '0504444444'),
        ]);
        $customerPort->attach(PosV2CustomerScope::fromRequestContext($this->requestContext()), 5);
        $cartPort = new InMemoryCustomerCartPort($customerPort);

        $useCase = new AttachCustomerUseCase(
            new PosV2CustomerAccessValidator(),
            $customerPort,
            new GetCartUseCase(new PosV2CartAccessValidator(), $cartPort),
        );

        $result = $useCase->execute(
            $this->requestContext(),
            new AttachCustomerRequest(8),
        );

        $ok = $customerPort->attachedId() === 8
            && $result->customer?->id === 8;

        $this->record('replace customer use case', $ok, 'expected replaced customer');
    }

    private function testRemoveCustomerUseCase(): void
    {
        $customerPort = new InMemoryCustomerPort();
        $customerPort->seed([new PosV2CustomerSummaryDto(5, 'Sara', '0503333333')]);
        $customerPort->attach(PosV2CustomerScope::fromRequestContext($this->requestContext()), 5);
        $cartPort = new InMemoryCustomerCartPort($customerPort);

        $result = (new RemoveCustomerUseCase(
            new PosV2CustomerAccessValidator(),
            $customerPort,
            new GetCartUseCase(new PosV2CartAccessValidator(), $cartPort),
        ))->execute($this->requestContext());

        $ok = $customerPort->attachedId() === null && $result->customer === null;
        $this->record('remove customer use case', $ok, 'expected detached customer');
    }

    private function testBootstrapCustomerShape(): void
    {
        $customerPort = new InMemoryCustomerPort();
        $customerPort->seed([new PosV2CustomerSummaryDto(3, 'Khalid', '0505555555')]);
        $customerPort->attach(PosV2CustomerScope::fromRequestContext($this->requestContext()), 3);

        $attached = (new GetAttachedCustomerUseCase(
            new PosV2CustomerAccessValidator(),
            $customerPort,
        ))->execute($this->requestContext());

        $array = $attached?->toArray() ?? [];
        $ok = ($array['id'] ?? null) === 3
            && ($array['name'] ?? '') === 'Khalid'
            && ($array['phone'] ?? '') === '0505555555';

        $this->record('bootstrap customer shape', $ok, 'expected customer summary in bootstrap');
    }

    private function testInvalidCustomerRequest(): void
    {
        try {
            AttachCustomerRequest::fromPayload([]);
            $this->record('attach rejects missing customer_id', false, 'expected exception');
        } catch (PosV2CustomerValidationException) {
            $this->record('attach rejects missing customer_id', true, '');
        }

        try {
            AttachCustomerRequest::fromPayload(['customer_id' => 0]);
            $this->record('attach rejects invalid customer_id', false, 'expected exception');
        } catch (PosV2CustomerValidationException) {
            $this->record('attach rejects invalid customer_id', true, '');
        }
    }

    private function testCustomerNotFound(): void
    {
        $port = new InMemoryCustomerPort();

        try {
            (new GetCustomerUseCase(
                new PosV2CustomerAccessValidator(),
                $port,
            ))->execute($this->requestContext(), 404);
            $this->record('get customer throws when missing', false, 'expected exception');
        } catch (PosV2CustomerNotFoundException) {
            $this->record('get customer throws when missing', true, '');
        }

        try {
            (new AttachCustomerUseCase(
                new PosV2CustomerAccessValidator(),
                $port,
                new GetCartUseCase(new PosV2CartAccessValidator(), new InMemoryCustomerCartPort($port)),
            ))->execute($this->requestContext(), new AttachCustomerRequest(404));
            $this->record('attach throws when customer missing', false, 'expected exception');
        } catch (PosV2CustomerNotFoundException) {
            $this->record('attach throws when customer missing', true, '');
        }
    }

    private function testCustomerSearchSuccessEnvelope(): void
    {
        $response = (new PosV2ResponseFactory())->customerSearchSuccess(
            new CustomerSearchResponse([
                new PosV2CustomerSummaryDto(1, 'Ali', '0500000001'),
            ]),
        );

        $body = $response->body;
        $ok = ($body['success'] ?? null) === true
            && is_array($body['data']['customers'] ?? null)
            && ($body['data']['customers'][0]['name'] ?? '') === 'Ali';

        $this->record('customer search success envelope', $ok, 'expected success/data envelope');
    }

    private function requestContext(): PosV2RequestContext
    {
        return new PosV2RequestContext(
            httpMethod: 'GET',
            requestPath: '/api/v2/pos/customers/search',
            channel: 'api',
            register: new PosV2RegisterContext(
                companyId: 1,
                branchId: 2,
                warehouseId: 3,
                sessionId: 9,
                terminal: null,
                shift: null,
                branch: null,
                cashier: new PosV2CashierContext(7, 'Cashier'),
                locale: 'ar',
                timezone: 'Asia/Riyadh',
                currency: 'SAR',
                rtl: true,
                featureFlags: new PosV2FeatureFlagsContext(true, 'retail', false, false, false),
                permissions: ['pos.register'],
                registerReady: true,
            ),
        );
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}

final class InMemoryCustomerPort implements PosV2CustomerPortInterface
{
    /** @var array<int, PosV2CustomerSummaryDto> */
    private array $customers = [];

    private ?int $attachedCustomerId = null;

    /** @param list<PosV2CustomerSummaryDto> $customers */
    public function seed(array $customers): void
    {
        foreach ($customers as $customer) {
            $this->customers[$customer->id] = $customer;
        }
    }

    public function search(PosV2CustomerScope $scope, CustomerSearchRequest $request): CustomerSearchResponse
    {
        $matches = array_values($this->customers);

        return new CustomerSearchResponse($matches);
    }

    public function findById(PosV2CustomerScope $scope, int $customerId): ?PosV2CustomerSummaryDto
    {
        return $this->customers[$customerId] ?? null;
    }

    public function getAttached(): ?PosV2CustomerSummaryDto
    {
        if ($this->attachedCustomerId === null) {
            return null;
        }

        return $this->customers[$this->attachedCustomerId] ?? null;
    }

    public function attach(PosV2CustomerScope $scope, int $customerId): void
    {
        if (!isset($this->customers[$customerId])) {
            throw new PosV2CustomerNotFoundException(
                'CUSTOMER_NOT_FOUND',
                sprintf('Customer %d was not found.', $customerId),
                $customerId,
            );
        }

        $this->attachedCustomerId = $customerId;
    }

    public function detach(): void
    {
        $this->attachedCustomerId = null;
    }

    public function attachedId(): ?int
    {
        return $this->attachedCustomerId;
    }
}

final class InMemoryCustomerCartPort implements PosV2CartPortInterface
{
    public function __construct(
        private readonly ?InMemoryCustomerPort $customers = null,
    ) {
    }

    public function load(PosV2CartScope $scope): CartResponse
    {
        $zero = new PosV2MoneyDto('0.00', $scope->currency);
        $customer = $this->customers?->getAttached();

        return new CartResponse(
            [],
            new PosV2CartTotalsDto($zero, $zero, $zero, $zero),
            0,
            $customer,
        );
    }

    public function addLine(PosV2CartScope $scope, int $productId, string $qty): CartResponse
    {
        return $this->load($scope);
    }

    public function updateLine(PosV2CartScope $scope, string $lineId, string $qty): CartResponse
    {
        return $this->load($scope);
    }

    public function removeLine(PosV2CartScope $scope, string $lineId): CartResponse
    {
        return $this->load($scope);
    }

    public function clear(PosV2CartScope $scope): CartResponse
    {
        return $this->load($scope);
    }
}
