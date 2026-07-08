<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

final class SignedUrlVerifier
{
    public function __construct(
        private readonly SignedUrlGenerator $generator
    ) {
    }

    public function verify(string $relativePath, int $expires, string $signature): bool
    {
        if ($expires < time()) {
            return false;
        }

        $key = str_replace('\\', '/', ltrim(trim($relativePath), '/'));
        if ($key === '' || str_contains($key, '..')) {
            return false;
        }

        $expected = $this->generator->sign($key, $expires);

        return hash_equals($expected, $signature);
    }
}
