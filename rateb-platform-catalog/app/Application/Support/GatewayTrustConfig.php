<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Support;

use Rateb\PlatformCatalog\Support\Request;

final class GatewayTrustConfig
{
    public function trustedGatewayEnabled(): bool
    {
        $env = getenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED');
        if ($env !== false && $env !== '') {
            return filter_var($env, FILTER_VALIDATE_BOOLEAN);
        }

        return defined('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED')
            && RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_ENABLED;
    }

    public function trustedGatewaySecret(): string
    {
        $env = getenv('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET');
        if ($env !== false && $env !== '') {
            return (string) $env;
        }

        return defined('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET')
            ? (string) RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_SECRET
            : '';
    }

    public function trustedGatewayTokenHeader(): string
    {
        return defined('RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_TOKEN_HEADER')
            ? (string) RATEB_PLATFORM_CATALOG_TRUSTED_GATEWAY_TOKEN_HEADER
            : 'X-Platform-Gateway-Token';
    }

    public function platformUserIdHeader(): string
    {
        return defined('RATEB_PLATFORM_CATALOG_PLATFORM_USER_ID_HEADER')
            ? (string) RATEB_PLATFORM_CATALOG_PLATFORM_USER_ID_HEADER
            : 'X-Platform-User-Id';
    }

    public function isTrustedGatewayRequest(): bool
    {
        if (!$this->trustedGatewayEnabled()) {
            return false;
        }

        $secret = $this->trustedGatewaySecret();
        if ($secret === '') {
            return false;
        }

        $token = Request::header($this->trustedGatewayTokenHeader());
        if ($token === null || $token === '') {
            return false;
        }

        return hash_equals($secret, $token);
    }
}
