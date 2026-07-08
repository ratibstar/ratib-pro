<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

final class S3CompatibleAdapter implements StorageAdapterInterface
{
    private readonly AwsSigV4Client $client;

    public function __construct(
        private readonly S3Config $config
    ) {
        $this->config->validate();
        $this->client = new AwsSigV4Client($this->config);
    }

    public static function fromConfig(?S3Config $config = null): self
    {
        return new self($config ?? S3Config::fromEnvironment());
    }

    public function put(string $relativePath, mixed $content, array $meta = []): StoredObject
    {
        $normalized = $this->normalizeKey($relativePath);
        $mimeType = (string) ($meta['mime_type'] ?? 'application/octet-stream');

        $headers = [
            'Content-Type' => $mimeType,
        ];

        if (isset($meta['checksum_sha256']) && is_string($meta['checksum_sha256']) && $meta['checksum_sha256'] !== '') {
            $headers['x-amz-checksum-sha256'] = $meta['checksum_sha256'];
        }

        foreach ($meta as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                continue;
            }
            if (str_starts_with($name, 'x-amz-meta-')) {
                $headers[$name] = (string) $value;
            }
        }

        if (is_resource($content)) {
            $payload = stream_get_contents($content);
            if ($payload === false) {
                throw new \RuntimeException('Unable to read upload stream');
            }
            rewind($content);
            $this->client->request('PUT', $normalized, $payload, $headers);
            $size = strlen($payload);
        } else {
            $payload = (string) $content;
            $this->client->request('PUT', $normalized, $payload, $headers);
            $size = strlen($payload);
        }

        return new StoredObject($normalized, $size, $mimeType, $meta);
    }

    public function get(string $relativePath)
    {
        $normalized = $this->normalizeKey($relativePath);
        $response = $this->client->request('GET', $normalized);
        $stream = fopen('php://temp', 'wb+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to open memory stream');
        }
        fwrite($stream, $response['body']);
        rewind($stream);

        return $stream;
    }

    public function delete(string $relativePath): void
    {
        $this->client->request('DELETE', $this->normalizeKey($relativePath));
    }

    public function exists(string $relativePath): bool
    {
        try {
            $this->client->request('HEAD', $this->normalizeKey($relativePath));

            return true;
        } catch (\RuntimeException $e) {
            if ((int) $e->getCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    public function publicUrl(string $relativePath): string
    {
        return $this->config->objectUrl($this->normalizeKey($relativePath));
    }

    public function signedUrl(string $relativePath, int $ttlSeconds): string
    {
        if (!$this->config->signedUrlsEnabled) {
            return $this->publicUrl($relativePath);
        }

        return $this->client->presignedUrl($this->normalizeKey($relativePath), $ttlSeconds);
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
