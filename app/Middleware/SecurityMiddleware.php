<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\IpRestrictionService;
use App\Services\RateLimiterService;
use App\Services\RequestSigningService;
use App\Services\TwoFactorService;
use RuntimeException;

final class SecurityMiddleware
{
    public function __construct(
        private readonly IpRestrictionService $ipRestrictions,
        private readonly RateLimiterService $rateLimiter,
        private readonly TwoFactorService $twoFactor,
        private readonly RequestSigningService $requestSigning
    ) {
    }

    /** @param array<string, mixed> $user */
    public function enforce(array $user, string $action, string $rawBody): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $this->ipRestrictions->enforce($ip);
        $this->rateLimiter->enforce($action, $ip, (int) ($user['id'] ?? 0));
        $this->requestSigning->enforceGovernmentSigning($action, $rawBody);
        if ($this->twoFactor->roleRequires2FA($user) && !$this->twoFactor->verifyFromRequest($user)) {
            throw new RuntimeException('Two-factor authentication required or invalid', 401);
        }
    }
}
