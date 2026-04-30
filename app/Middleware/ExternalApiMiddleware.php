<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\ApiTokenService;
use App\Services\RateLimiterService;
use RuntimeException;

final class ExternalApiMiddleware
{
    public function __construct(
        private readonly ApiTokenService $tokens,
        private readonly RateLimiterService $limits
    ) {
    }

    public function enforce(string $action): string
    {
        $token = $this->tokens->resolveBearerToken();
        if (!$this->tokens->validate($token)) {
            throw new RuntimeException('Unauthorized API token', 401);
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $clientKey = substr(hash('sha256', $token), 0, 24);
        $this->limits->enforceExternal($action, $ip, $clientKey);

        return $clientKey;
    }
}
