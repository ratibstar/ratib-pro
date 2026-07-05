<?php

declare(strict_types=1);

namespace Rateb\App\Pos\Application\V2;

use Rateb\App\Helpers\Request;
use Rateb\App\Pos\DTO\V2\Context\PosV2RegisterContext;
use Rateb\App\Pos\DTO\V2\Context\PosV2RequestContext;

/** Builds immutable V2 contexts for controllers. */
final class PosV2ContextFactory
{
    public function __construct(
        private readonly PosV2ContextResolver $resolver,
    ) {
    }

    public function createRegisterContext(): PosV2RegisterContext
    {
        return $this->resolver->resolveRegisterContext();
    }

    public function createRequestContext(?string $channel = null): PosV2RequestContext
    {
        return $this->resolver->resolveRequestContext(
            channel: $channel ?? $this->resolveChannel(),
            httpMethod: $this->resolveHttpMethod(),
            requestPath: $this->resolveRequestPath(),
        );
    }

    private function resolveChannel(): string
    {
        $path = $this->resolveRequestPath();

        return str_starts_with($path, '/api/v2/pos') ? 'api' : 'web';
    }

    private function resolveHttpMethod(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    private function resolveRequestPath(): string
    {
        return Request::resolvePath();
    }
}
