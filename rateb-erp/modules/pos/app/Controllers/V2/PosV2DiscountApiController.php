<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Controllers\V2;

use Rateb\App\Pos\Application\V2\PosV2ApplicationFactory;
use Rateb\App\Pos\Application\V2\PosV2DiscountExceptionHandler;
use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Controllers\PosBaseController;
use Rateb\App\Pos\DTO\V2\Discount\DiscountRequest;
use Rateb\App\Pos\UseCases\V2\Discount\DiscountUseCaseFactory;
use Throwable;

/** V2 discount API (T11). */
final class PosV2DiscountApiController extends PosBaseController
{
    public function __construct(
        private readonly PosV2ApplicationFactory $applicationFactory = new PosV2ApplicationFactory(),
        private readonly DiscountUseCaseFactory $useCaseFactory = new DiscountUseCaseFactory(),
        private readonly PosV2DiscountExceptionHandler $exceptionHandler = new PosV2DiscountExceptionHandler(
            new PosV2ResponseFactory(),
        ),
    ) {
    }

    public function applyLineDiscount(): void
    {
        $this->requireSessionCsrfOrAbort();
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $payload = $this->decodeJsonBody();
            $request = DiscountRequest::fromPayload($payload);
            if ($request->lineId === null || $request->lineId === '') {
                throw new \Rateb\App\Pos\Domain\V2\Discount\Exceptions\PosV2DiscountValidationException(
                    'LINE_ID_REQUIRED',
                    'line_id is required.',
                );
            }
            $result = $this->useCaseFactory->createApplyLine()->execute($context, $request->lineId, $request);
            $application->responses()->cartSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function removeLineDiscount(string $lineId): void
    {
        $this->requireSessionCsrfOrAbort();
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $result = $this->useCaseFactory->createRemoveLine()->execute($context, $lineId);
            $application->responses()->cartSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function applyCartDiscount(): void
    {
        $this->requireSessionCsrfOrAbort();
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $payload = $this->decodeJsonBody();
            $request = DiscountRequest::fromPayload($payload);
            $result = $this->useCaseFactory->createApplyCart()->execute($context, $request);
            $application->responses()->cartSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function removeCartDiscount(): void
    {
        $this->requireSessionCsrfOrAbort();
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $result = $this->useCaseFactory->createRemoveCart()->execute($context);
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
