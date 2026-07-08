<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

final class S3Config
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $endpoint,
        public readonly string $bucket,
        public readonly string $accessKey,
        public readonly string $secretKey,
        public readonly string $region,
        public readonly bool $usePathStyle,
        public readonly bool $signedUrlsEnabled
    ) {
    }

    public static function fromEnvironment(): self
    {
        $path = defined('RATEB_CATALOG_ROOT') ? RATEB_CATALOG_ROOT . '/config/s3.php' : dirname(__DIR__, 3) . '/config/s3.php';
        $config = is_file($path) ? require $path : [];

        if (!is_array($config)) {
            $config = [];
        }

        return new self(
            enabled: (bool) ($config['enabled'] ?? false),
            endpoint: (string) ($config['endpoint'] ?? ''),
            bucket: (string) ($config['bucket'] ?? ''),
            accessKey: (string) ($config['key'] ?? ''),
            secretKey: (string) ($config['secret'] ?? ''),
            region: (string) ($config['region'] ?? 'us-east-1'),
            usePathStyle: (bool) ($config['use_path_style'] ?? false),
            signedUrlsEnabled: (bool) ($config['signed_urls_enabled'] ?? false)
        );
    }

    public function validate(): void
    {
        if ($this->bucket === '' || $this->accessKey === '' || $this->secretKey === '') {
            throw new \RuntimeException('S3 configuration is incomplete');
        }
    }

    public function objectUrl(string $key): string
    {
        $encodedKey = implode('/', array_map('rawurlencode', explode('/', $key)));

        if ($this->endpoint !== '') {
            $base = rtrim($this->endpoint, '/');
            if ($this->usePathStyle) {
                return $base . '/' . rawurlencode($this->bucket) . '/' . $encodedKey;
            }

            return $base . '/' . $encodedKey;
        }

        if ($this->usePathStyle) {
            return sprintf('https://s3.%s.amazonaws.com/%s/%s', $this->region, rawurlencode($this->bucket), $encodedKey);
        }

        return sprintf('https://%s.s3.%s.amazonaws.com/%s', rawurlencode($this->bucket), $this->region, $encodedKey);
    }

    public function hostForSigning(string $key): string
    {
        if ($this->endpoint !== '') {
            $parts = parse_url($this->endpoint);
            if (!is_array($parts) || !isset($parts['host'])) {
                throw new \RuntimeException('Invalid S3 endpoint');
            }

            $host = (string) $parts['host'];
            if ($this->usePathStyle) {
                return $host;
            }

            return $host;
        }

        if ($this->usePathStyle) {
            return sprintf('s3.%s.amazonaws.com', $this->region);
        }

        return sprintf('%s.s3.%s.amazonaws.com', $this->bucket, $this->region);
    }

    public function uriForObject(string $key): string
    {
        $encodedKey = '/' . implode('/', array_map('rawurlencode', explode('/', $key)));

        if ($this->usePathStyle) {
            return '/' . rawurlencode($this->bucket) . $encodedKey;
        }

        return $encodedKey;
    }
}
