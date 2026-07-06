<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Controllers\V2;

use Rateb\App\Pos\Application\V2\PosV2ApplicationFactory;
use Rateb\App\Pos\Application\V2\PosV2CustomerExceptionHandler;
use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Controllers\PosBaseController;
use Rateb\App\Pos\DTO\V2\Customer\AttachCustomerRequest;
use Rateb\App\Pos\DTO\V2\Customer\CustomerSearchRequest;
use Rateb\App\Pos\UseCases\V2\Customer\CustomerUseCaseFactory;
use Throwable;

/** V2 customer API (T10). */
final class PosV2CustomerApiController extends PosBaseController
{
    public function __construct(
        private readonly PosV2ApplicationFactory $applicationFactory = new PosV2ApplicationFactory(),
        private readonly CustomerUseCaseFactory $useCaseFactory = new CustomerUseCaseFactory(),
        private readonly PosV2CustomerExceptionHandler $exceptionHandler = new PosV2CustomerExceptionHandler(
            new PosV2ResponseFactory(),
        ),
    ) {
    }

    public function search(): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $request = CustomerSearchRequest::fromQueryParams($_GET);
            $result = $this->useCaseFactory->createSearch()->execute($context, $request);
            $application->responses()->customerSearchSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function get(int $customerId): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $result = $this->useCaseFactory->createGet()->execute($context, $customerId);
            $application->responses()->customerDetailSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function attachToCart(): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $payload = $this->decodeJsonBody();
            $request = AttachCustomerRequest::fromPayload($payload);
            $result = $this->useCaseFactory->createAttach()->execute($context, $request);
            $application->responses()->cartSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function removeFromCart(): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $result = $this->useCaseFactory->createRemove()->execute($context);
            $application->responses()->cartSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    /** @return array<string, mixed> */
    private function decodeJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
