<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Middleware;

use Rateb\PlatformCatalog\Application\Support\CorrelationIdContext;
use Rateb\PlatformCatalog\Support\Request;

final class CorrelationIdMiddleware
{
    public function handle(string $method, string $path): bool
    {
        unset($method, $path);
        $header = Request::header('X-Correlation-Id');
        $correlationId = CorrelationIdContext::resolveFromHeader($header);
        header('X-Correlation-Id: ' . $correlationId);

        return true;
    }
}
