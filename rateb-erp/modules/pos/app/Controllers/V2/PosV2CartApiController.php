<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Controllers\V2;

use Rateb\App\Pos\Application\V2\PosV2ApplicationFactory;
use Rateb\App\Pos\Application\V2\PosV2CartExceptionHandler;
use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Controllers\PosBaseController;
use Rateb\App\Pos\DTO\V2\Cart\AddLineRequest;
use Rateb\App\Pos\DTO\V2\Cart\UpdateLineRequest;
use Rateb\App\Pos\UseCases\V2\Cart\CartUseCaseFactory;
use Throwable;

/** V2 cart API (T09). */
final class PosV2CartApiController extends PosBaseController
{
    public function __construct(
        private readonly PosV2ApplicationFactory $applicationFactory = new PosV2ApplicationFactory(),
        private readonly CartUseCaseFactory $useCaseFactory = new CartUseCaseFactory(),
        private readonly PosV2CartExceptionHandler $exceptionHandler = new PosV2CartExceptionHandler(
            new PosV2ResponseFactory(),
        ),
    ) {
    }

    public function addLine(): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $payload = $this->decodeJsonBody();
            $request = AddLineRequest::fromPayload($payload);
            $result = $this->useCaseFactory->createAddLine()->execute($context, $request);
            $application->responses()->cartSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function updateLine(string $lineId): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $payload = $this->decodeJsonBody();
            $request = UpdateLineRequest::fromPayload($payload);
            $result = $this->useCaseFactory->createUpdateLine()->execute($context, $lineId, $request);
            $application->responses()->cartSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function removeLine(string $lineId): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $result = $this->useCaseFactory->createRemoveLine()->execute($context, $lineId);
            $application->responses()->cartSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function clear(): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $result = $this->useCaseFactory->createClear()->execute($context);
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
