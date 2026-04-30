<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class IpRestrictionService
{
    public function enforce(string $ipAddress): void
    {
        $ipAddress = trim($ipAddress);
        if ($ipAddress === '') {
            throw new RuntimeException('IP address missing', 401);
        }
        $denyList = $this->parseList((string) (getenv('SEC_IP_DENY_LIST') ?: ''));
        if (in_array($ipAddress, $denyList, true)) {
            throw new RuntimeException('IP blocked', 403);
        }
        $allowList = $this->parseList((string) (getenv('SEC_IP_ALLOW_LIST') ?: ''));
        if ($allowList !== [] && !in_array($ipAddress, $allowList, true)) {
            throw new RuntimeException('IP not allowed', 403);
        }
    }

    /** @return list<string> */
    private function parseList(string $csv): array
    {
        $parts = array_map('trim', explode(',', $csv));
        $parts = array_filter($parts, static fn (string $x): bool => $x !== '');
        return array_values(array_unique($parts));
    }
}
