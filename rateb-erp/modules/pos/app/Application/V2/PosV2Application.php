<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Pos\Application\V2\Http\PosV2JsonResponse;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/**
 * V2 application entry point — single runtime layer for controllers.
 */
final class PosV2Application
{
    public function __construct(
        private readonly PosV2Bootstrap $bootstrap,
        private readonly PosV2ResponseFactory $responses,
    ) {
    }

    public function bootstrap(): PosV2Bootstrap
    {
        return $this->bootstrap;
    }

    public function responses(): PosV2ResponseFactory
    {
        return $this->responses;
    }

    public function bootstrapRegister(?string $channel = null): PosV2RequestContext
    {
        return $this->bootstrap->bootstrapRegister($channel);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function jsonSuccess(array $data, int $statusCode = 200): PosV2JsonResponse
    {
        return $this->responses->success($data, $statusCode);
    }

    /**
     * @param array<string, mixed> $details
     */
    public function jsonError(
        string $code,
        string $message,
        int $statusCode = 422,
        ?string $field = null,
        array $details = [],
    ): PosV2JsonResponse {
        return $this->responses->error($code, $message, $statusCode, $field, $details);
    }
}
