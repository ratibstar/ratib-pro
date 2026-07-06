<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Controllers\V2;

use Rateb\App\Pos\Application\V2\PosV2ApplicationFactory;
use Rateb\App\Pos\Application\V2\PosV2PaymentExceptionHandler;
use Rateb\App\Pos\Application\V2\PosV2ResponseFactory;
use Rateb\App\Pos\Controllers\PosBaseController;
use Rateb\App\Pos\DTO\V2\Payment\CashPaymentRequest;
use Rateb\App\Pos\UseCases\V2\Payment\PaymentUseCaseFactory;
use Throwable;

final class PosV2PaymentApiController extends PosBaseController
{
    public function __construct(
        private readonly PosV2ApplicationFactory $applicationFactory = new PosV2ApplicationFactory(),
        private readonly PaymentUseCaseFactory $useCaseFactory = new PaymentUseCaseFactory(),
        private readonly PosV2PaymentExceptionHandler $exceptionHandler = new PosV2PaymentExceptionHandler(
            new PosV2ResponseFactory(),
        ),
    ) {
    }

    public function index(): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $result = $this->useCaseFactory->createGet()->execute($context);
            $application->responses()->paymentSummarySuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function addCash(): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $request = CashPaymentRequest::fromPayload($this->decodeJsonBody());
            $result = $this->useCaseFactory->createCash()->execute($context, $request);
            $application->responses()->cartSuccess($result)->send();
        } catch (Throwable $throwable) {
            $this->exceptionHandler->handle($throwable)->send();
        }
    }

    public function remove(string $paymentId): void
    {
        try {
            $application = $this->applicationFactory->create();
            $context = $application->bootstrapRegister('api');
            $result = $this->useCaseFactory->createRemove()->execute($context, $paymentId);
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
