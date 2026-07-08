<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Storage;

final class AwsSigV4Client
{
    public function __construct(
        private readonly S3Config $config
    ) {
    }

    /**
     * @param resource|string $body
     * @param array<string, string> $headers
     */
    public function request(string $method, string $key, $body = '', array $headers = [], bool $unsignedPayload = false): array
    {
        $normalizedKey = $this->normalizeKey($key);
        $payload = is_resource($body) ? stream_get_contents($body) : (string) $body;
        if (is_resource($body)) {
            rewind($body);
        }

        $payloadHash = $unsignedPayload ? 'UNSIGNED-PAYLOAD' : hash('sha256', $payload);
        $host = $this->config->hostForSigning($normalizedKey);
        $uri = $this->config->uriForObject($normalizedKey);
        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);

        $defaultHeaders = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];

        $allHeaders = array_change_key_case(array_merge($defaultHeaders, $headers), CASE_LOWER);
        ksort($allHeaders);

        $canonicalHeaders = '';
        $signedHeaderNames = [];
        foreach ($allHeaders as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim((string) $value) . "\n";
            $signedHeaderNames[] = $name;
        }
        $signedHeaders = implode(';', $signedHeaderNames);

        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $uri,
            '',
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $scope = $date . '/' . $this->config->region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $now,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->signingKey($date);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->config->accessKey,
            $scope,
            $signedHeaders,
            $signature
        );

        $scheme = 'https';
        if ($this->config->endpoint !== '') {
            $parts = parse_url($this->config->endpoint);
            $scheme = (string) ($parts['scheme'] ?? 'https');
        }

        $url = $scheme . '://' . $host . $uri;

        $curlHeaders = [];
        foreach ($allHeaders as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }
        $curlHeaders[] = 'Authorization: ' . $authorization;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialize S3 request');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $curlHeaders,
            CURLOPT_HEADER => true,
        ]);

        if (in_array(strtoupper($method), ['PUT', 'POST'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('S3 request failed: ' . $error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaders = substr((string) $response, 0, $headerSize);
        $bodyContent = substr((string) $response, $headerSize);

        if ($status >= 400) {
            throw new \RuntimeException('S3 request failed with status ' . $status . ': ' . trim($bodyContent), $status);
        }

        return [
            'status' => $status,
            'headers' => $this->parseHeaders($rawHeaders),
            'body' => $bodyContent,
        ];
    }

    public function presignedUrl(string $key, int $ttlSeconds, string $method = 'GET'): string
    {
        $normalizedKey = $this->normalizeKey($key);
        $expires = max(1, $ttlSeconds);
        $host = $this->config->hostForSigning($normalizedKey);
        $uri = $this->config->uriForObject($normalizedKey);
        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);

        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->config->accessKey . '/' . $date . '/' . $this->config->region . '/s3/aws4_request',
            'X-Amz-Date' => $now,
            'X-Amz-Expires' => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ];

        ksort($query);
        $canonicalPairs = [];
        foreach ($query as $name => $value) {
            $canonicalPairs[] = rawurlencode($name) . '=' . rawurlencode($value);
        }
        $canonicalQuery = implode('&', $canonicalPairs);

        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $uri,
            $canonicalQuery,
            'host:' . $host . "\n",
            'host',
            'UNSIGNED-PAYLOAD',
        ]);

        $scope = $date . '/' . $this->config->region . '/s3/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $now,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signature = hash_hmac('sha256', $stringToSign, $this->signingKey($date));
        $canonicalQuery .= '&' . rawurlencode('X-Amz-Signature') . '=' . $signature;

        $scheme = 'https';
        if ($this->config->endpoint !== '') {
            $parts = parse_url($this->config->endpoint);
            $scheme = (string) ($parts['scheme'] ?? 'https');
        }

        return $scheme . '://' . $host . $uri . '?' . $canonicalQuery;
    }

    private function signingKey(string $date): string
    {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $this->config->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->config->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (explode("\r\n", $raw) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
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
