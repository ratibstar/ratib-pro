<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Application\Services;

use Rateb\PlatformCatalog\Support\Request;

final class ConcurrencyService
{
    public function formatEtag(int $lockVersion): string
    {
        return 'W/"' . $lockVersion . '"';
    }

    public function parseIfMatch(?string $header): ?int
    {
        if ($header === null || $header === '') {
            return null;
        }

        if (preg_match('/W\/"(\d+)"|"(\d+)"/', $header, $matches) !== 1) {
            return null;
        }

        $version = $matches[1] !== '' ? $matches[1] : ($matches[2] ?? '');

        return is_numeric($version) ? (int) $version : null;
    }

    public function resolveExpectedLockVersion(?int $bodyLockVersion): ?int
    {
        if ($bodyLockVersion !== null) {
            return $bodyLockVersion;
        }

        return $this->parseIfMatch(Request::header('If-Match'));
    }

    public function assertLockVersion(int $expected, int $actual): void
    {
        if ($expected !== $actual) {
            throw new ProductVersionConflictException($actual);
        }
    }

    public function requireLockVersion(?int $bodyLockVersion): int
    {
        $resolved = $this->resolveExpectedLockVersion($bodyLockVersion);
        if ($resolved === null) {
            throw new \InvalidArgumentException('lock_version or If-Match header is required');
        }

        return $resolved;
    }
}
