<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Infrastructure\Http;

final class NativeHttpClient implements HttpClientInterface
{
    /**
     * @param array<int, string> $headers Full header lines: "Header-Name: value"
     * @return array{status: int, body: ?string}
     */
    public function postRaw(
        string $url,
        string $body,
        array $headers,
        int $timeoutSeconds = 30,
        int $connectTimeoutSeconds = 10
    ): array {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                return ['status' => 0, 'body' => 'Unable to initialize HTTP client'];
            }

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => $connectTimeoutSeconds,
            ]);

            $responseBody = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);

            return [
                'status' => $status > 0 ? $status : 0,
                'body' => is_string($responseBody) ? $responseBody : null,
            ];
        }

        $headerLines = implode("\r\n", $headers);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => $headerLines,
                'content' => $body,
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string) $http_response_header[0], $matches) === 1) {
            $status = (int) $matches[1];
        }

        return [
            'status' => $status,
            'body' => is_string($responseBody) ? $responseBody : null,
        ];
    }
}

