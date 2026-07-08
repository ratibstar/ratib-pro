<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

final class SignedUrlGenerator
{
    public function __construct(
        private readonly string $secret,
        private readonly string $baseUrl,
        private readonly string $servePath = '/catalog/signed-storage'
    ) {
    }

    public function generate(string $relativePath, int $ttlSeconds): string
    {
        $key = $this->normalizeKey($relativePath);
        $expires = time() + max(1, $ttlSeconds);
        $signature = $this->sign($key, $expires);

        $query = http_build_query([
            'key' => $key,
            'expires' => (string) $expires,
            'sig' => $signature,
        ]);

        $base = rtrim($this->baseUrl, '/');
        if ($base === '') {
            return $this->servePath . '?' . $query;
        }

        return $base . $this->servePath . '?' . $query;
    }

    public function sign(string $normalizedKey, int $expires): string
    {
        if ($this->secret === '') {
            throw new \RuntimeException('Signed URL secret is not configured');
        }

        $payload = $normalizedKey . "\n" . $expires;

        return hash_hmac('sha256', $payload, $this->secret);
    }

    private function normalizeKey(string $relativePath): string
    {
        $key = str_replace('\\', '/', trim($relativePath));
        $key = ltrim($key, '/');
        if ($key === '' || str_contains($key, '..')) {
            throw new \InvalidArgumentException('Invalid storage key');
        }

        return $key;
    }
}
